<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SyncFirestore;
use App\Models\Delivery;
use App\Services\FcmService;
use Illuminate\Http\Request;

/**
 * Driver outcomes besides "completed":
 *   POST /v1/deliveries/{id}/fail    {reason, note?} — could not hand it over
 *   POST /v1/deliveries/{id}/return                  — Finish: package brought back to ROTEH
 *                                                      (ROTEH hands it back to the shop at the partner close)
 * A failed package can still be completed if the receiver is reached later.
 */
class DeliveryOutcomeController extends ApiController
{
    public function fail(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $delivery->driver_id !== $user->id) return $this->unauthorized();

        $data = $request->validate([
            'reason' => 'required|in:' . implode(',', Delivery::FAILURE_REASONS),
            'note'   => 'nullable|string|max:500',
        ]);

        if ($delivery->status === 'delivery_failed') {
            return $this->success(['delivery' => $delivery->load('sender', 'driver', 'vehicle')]);
        }
        if (! in_array($delivery->status, ['in_progress', 'picked_up', 'in_transit'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only a package you have picked up can be marked as not delivered.',
            ], 422);
        }
        if ($delivery->onPickupLeg()) {
            return response()->json([
                'success' => false, 'code' => 'pickup_leg',
                'message' => 'This package is on its way to the ROTEH office, not to the receiver. Bring it to the office.',
            ], 422);
        }

        $delivery->update([
            'status'         => 'delivery_failed',
            'failure_reason' => $data['reason'],
            'failure_note'   => $data['note'] ?? null,
            'failed_at'      => now(),
        ]);
        $this->sync($delivery);
        $this->notifySender($delivery, 'delivery_failed_sender', [
            'reason' => \App\Support\LocalizedText::trans('push.failure_reasons.' . $data['reason']),
        ]);

        return $this->success(['delivery' => $delivery->fresh(['sender', 'driver', 'vehicle'])]);
    }

    public function returned(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $delivery->driver_id !== $user->id) return $this->unauthorized();

        if (in_array($delivery->status, ['at_roteh', 'returned'], true)) {
            return $this->success(['delivery' => $delivery->load('sender', 'driver', 'vehicle')]);
        }
        if ($delivery->status !== 'delivery_failed') {
            return response()->json([
                'success' => false,
                'message' => 'Mark the package as not delivered before returning it.',
            ], 422);
        }

        // Partner packages come back to ROTEH; other deliveries go straight back to the sender.
        $delivery->update($delivery->partner_id
            ? ['status' => 'at_roteh', 'hub_returned_at' => now()]
            : ['status' => 'returned', 'returned_at' => now(), 'hub_returned_at' => now()]);
        $this->sync($delivery);
        $this->notifySender($delivery, $delivery->partner_id ? 'delivery_at_roteh_sender' : 'delivery_returned_sender', []);

        return $this->success(['delivery' => $delivery->fresh(['sender', 'driver', 'vehicle'])]);
    }

    private function sync(Delivery $delivery): void
    {
        try {
            SyncFirestore::dispatch('syncDelivery', [$delivery->fresh(['sender', 'driver', 'vehicle'])]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifySender(Delivery $delivery, string $key, array $replace): void
    {
        try {
            $sender = $delivery->partner ?? $delivery->sender;
            if (! $sender) return;
            [$title, $body] = FcmService::message($key, $replace + [
                'code' => 'PKG-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT),
            ]);
            app(FcmService::class)->notifyUser($sender, $title, $body, [
                'type' => $delivery->partner_id ? 'partner_package' : 'delivery_update',
                'delivery_id' => (string) $delivery->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
