<?php

namespace App\Services;

use App\Events\RealtimeUpdate;
use App\Jobs\SyncFirestore;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Admin assigns Partner packages to ROTEH staff drivers (no broadcast, no
 * online/offline). Every package goes through the ROTEH office in two legs:
 *   pickup leg   — not at the office yet: the driver scans it at the shop and brings it in;
 *   delivery leg — at the office: the driver scans it there and takes it to the customer.
 * The leg follows from where the package is, so admin uses the same Assign for both.
 */
class StaffAssignmentService
{
    /**
     * Statuses a package can be (re)assigned from: before the shop pickup scan
     * (pickup leg), or at / out from the office until the handover (delivery leg).
     */
    public const ASSIGNABLE = ['created', 'requested', 'pending', 'assigned', 'accepted', 'at_office', 'in_transit'];

    public function assign(Delivery $delivery, User $staff): Delivery
    {
        if (! $delivery->partner_id) {
            throw ValidationException::withMessages(['delivery' => "#{$delivery->id} is not a Partner package."]);
        }
        if (! $staff->isSalariedStaff()) {
            throw ValidationException::withMessages(['driver_id' => 'Partner packages can only be assigned to ROTEH staff drivers.']);
        }
        if (! self::canAssign($delivery)) {
            throw ValidationException::withMessages(['delivery' => "PKG-{$delivery->id} is already picked up or closed ({$delivery->status})."]);
        }

        $leg      = $delivery->office_received_at ? Delivery::LEG_DELIVERY : Delivery::LEG_PICKUP;
        $previous = $delivery->driver_id && $delivery->driver_id !== $staff->id && $delivery->status !== 'at_office' ? $delivery->driver_id : null;

        $delivery->update([
            'driver_id'       => $staff->id,
            'leg'             => $leg,
            'assigned_at'     => now(),
            'confirmed_at'    => $delivery->confirmed_at ?? now(),
            'assignment_type' => 'manual',
        ] + ($leg === Delivery::LEG_PICKUP
            // Pickup leg: driver assigned → scans the package at the shop.
            ? ['status' => 'accepted', 'pickup_driver_id' => $staff->id]
            // Delivery leg: the office hands the package over with the
            // assignment — no scan at the office; straight on the way to the
            // receiver (scan + confirm money at handover).
            : ['status' => 'in_transit', 'office_dispatched_at' => now(), 'started_at' => $delivery->started_at ?? now()]));

        try {
            SyncFirestore::dispatch('syncDelivery', [$delivery->fresh(['sender', 'driver', 'vehicle'])]);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->notify($delivery, $staff, $previous);

        return $delivery->fresh();
    }

    /** Assignable now: not yet scanned out on its current leg, and not closed. */
    public static function canAssign(Delivery $delivery): bool
    {
        if (! in_array($delivery->status, self::ASSIGNABLE, true)) return false;
        if ($delivery->status === 'at_office') return true;

        // Delivery leg: reassignable until the handover (scan / money recorded).
        if ($delivery->office_received_at) {
            return ! $delivery->delivery_scanned_at && ! $delivery->collection()->exists();
        }
        return $delivery->status !== 'in_transit' && ! $delivery->pickup_scanned_at;
    }

    /** Where the driver collects the package on this leg: the shop, or the ROTEH office. */
    public static function collectFrom(Delivery $delivery): string
    {
        return $delivery->leg === Delivery::LEG_DELIVERY ? (string) config('ride.office.address') : (string) $delivery->pickup_address;
    }

    private function notify(Delivery $delivery, User $staff, ?int $previous): void
    {
        try {
            RealtimeUpdate::toDriver($staff->id, 'staff.assigned', ['delivery_id' => $delivery->id]);
            if ($previous) RealtimeUpdate::toDriver($previous, 'staff.unassigned', ['delivery_id' => $delivery->id]);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            app(FcmService::class)->sendToUser($staff,
                ...FcmService::message('staff_package_assigned', [
                    'code'   => 'PKG-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT),
                    'pickup' => self::collectFrom($delivery),
                ]),
                data: ['type' => 'staff_package_assigned', 'delivery_id' => (string) $delivery->id]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
