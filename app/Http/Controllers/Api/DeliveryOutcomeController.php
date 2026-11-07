<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SyncFirestore;
use App\Models\Delivery;
use App\Services\FcmService;
use Illuminate\Http\Request;

/**
 * Driver outcomes besides "completed":
 *   POST /v1/deliveries/{id}/fail    {reason, note?} — could not hand it over
 *   POST /v1/deliveries/{id}/return                  — package is back at the shop
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

        if ($delivery->status === 'returned') {
            return $this->success(['delivery' => $delivery->load('sender', 'driver', 'vehicle')]);
        }
        if ($delivery->status !== 'delivery_failed') {
            return response()->json([
                'success' => false,
                'message' => 'Mark the package as not delivered before returning it.',
            ], 422);
        }

        $delivery->update(['status' => 'returned', 'returned_at' => now()]);
        $this->sync($delivery);
        $this->notifySender($delivery, 'delivery_returned_sender', []);

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
