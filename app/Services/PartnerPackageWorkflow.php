<?php

namespace App\Services;

use App\Jobs\SyncFirestore;
use App\Mail\TripReceipt;
use App\Models\Delivery;
use App\Models\DeliveryCollection;
use App\Models\User;
use App\Services\PartnerStatementService as PS;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Partner package steps shared by the admin board and the driver app:
 *   receiveAtOffice() — end of the pickup leg (admin click or driver QR scan at the office)
 *   collect()         — COD money received at handover (driver app or admin)
 *   finish()          — complete after the money is recorded
 * Assigning drivers to either leg is StaffAssignmentService.
 */
class PartnerPackageWorkflow
{
    public const BANKS = ['ABA', 'ACLEDA', 'Wing', 'Other'];

    /** Statuses a package can be handed over (and its COD collected) from. */
    public const HANDOVER_STATUSES = ['assigned', 'accepted', 'picked_up', 'in_progress', 'in_transit', 'delivery_failed'];

    /** Pickup leg → at the office. Returns false when the package isn't on its way to the office. */
    public function receiveAtOffice(Delivery $delivery, ?User $by = null): bool
    {
        if (! $delivery->onPickupLeg()) return false;

        $delivery->update([
            'status'             => 'at_office',
            'pickup_scanned_at'  => $delivery->pickup_scanned_at ?? now(),
            'office_received_at' => now(),
            'office_received_by' => $by?->id,
            'pickup_driver_id'   => $delivery->pickup_driver_id ?? $delivery->driver_id,
        ]);
        $this->sync($delivery);
        $this->notifySender($delivery, 'partner_package_at_office');

        return true;
    }

    /**
     * Record the money received at handover — any mix of cash ៛ / $ and bank ៛ / $.
     * Returns ['collection' => DeliveryCollection] or ['error' => [code, message, …extra]].
     */
    public function collect(Delivery $delivery, int $driverId, array $data): array
    {
        if ($existing = $delivery->collection) return ['collection' => $existing];

        $cashKhr = (int) ($data['cash_khr'] ?? 0);
        $cashUsd = round((float) ($data['cash_usd'] ?? 0), 2);
        $bankKhr = (int) ($data['bank_khr'] ?? 0);
        $bankUsd = round((float) ($data['bank_usd'] ?? 0), 2);

        if (($bankKhr > 0 || $bankUsd > 0) && empty($data['bank_name'])) {
            return ['error' => ['code' => 'bank_required', 'message' => 'Choose the bank the receiver paid to.']];
        }

        $rate     = max(1, (int) config('ride.khr_per_usd', 4100));
        $expected = (int) $delivery->package_amount;
        $total    = $cashKhr + $bankKhr + (int) round(($cashUsd + $bankUsd) * $rate);
        $diff     = $total - $expected;

        // Small rounding (< 100 ៛) is fine; anything else needs a reason.
        if (abs($diff) >= 100 && trim((string) ($data['note'] ?? '')) === '') {
            return ['error' => [
                'code' => 'note_required', 'difference_khr' => $diff, 'expected_khr' => $expected, 'total_khr' => $total,
                'message' => 'The amount received does not match the COD. Add a note explaining the difference.',
            ]];
        }

        return ['collection' => DB::transaction(fn () => DeliveryCollection::create([
            'delivery_id'    => $delivery->id,
            'driver_id'      => $driverId,
            'partner_id'     => $delivery->partner_id,
            'business_date'  => now(PS::tz())->toDateString(),
            'expected_khr'   => $expected,
            'cash_khr'       => $cashKhr,
            'cash_usd'       => $cashUsd,
            'bank_khr'       => $bankKhr,
            'bank_usd'       => $bankUsd,
            'bank_name'      => $data['bank_name'] ?? null,
            'bank_ref'       => $data['bank_ref'] ?? null,
            'khr_per_usd'    => $rate,
            'total_khr'      => $total,
            'difference_khr' => $diff,
            'note'           => $data['note'] ?? null,
        ]))];
    }

    /** Complete a package whose money is recorded: settle, sync, tell the partner. */
    public function finish(Delivery $delivery): Delivery
    {
        if (! $delivery->isFinished()) {
            $delivery->update([
                'status'              => 'completed',
                'completed_at'        => now(),
                'delivery_scanned_at' => $delivery->delivery_scanned_at ?? now(),
            ]);
        }
        $fresh = $delivery->fresh(['sender', 'driver', 'vehicle']);

        try {
            app(PaymentService::class)->settleDelivery($fresh);  // no-op when already paid
            $fresh->refresh();
        } catch (\Throwable $e) {
            report($e);
        }
        $this->sync($fresh);
        try {
            if ($fresh->sender) app(FcmService::class)->deliveryCompleted($fresh->sender, $fresh->id);
        } catch (\Throwable $e) {
            report($e);
        }
        if ($fresh->sender?->email) {
            try {
                Mail::to($fresh->sender->email)->queue(TripReceipt::fromDelivery($fresh));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $fresh;
    }

    private function sync(Delivery $delivery): void
    {
        try {
            SyncFirestore::dispatch('syncDelivery', [$delivery->fresh(['sender', 'driver', 'vehicle'])]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifySender(Delivery $delivery, string $key): void
    {
        try {
            if ($sender = $delivery->partner ?? $delivery->sender) {
                app(FcmService::class)->sendToUser($sender,
                    ...FcmService::message($key, ['code' => 'PKG-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT)]),
                    data: ['type' => 'partner_package', 'delivery_id' => (string) $delivery->id]
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
