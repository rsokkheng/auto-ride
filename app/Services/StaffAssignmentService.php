<?php

namespace App\Services;

use App\Events\RealtimeUpdate;
use App\Jobs\SyncFirestore;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Admin assigns Partner packages to ROTEH staff drivers (no broadcast, no
 * online/offline). The package goes straight onto the driver's list as
 * "driver assigned" — they scan it at the shop to pick it up.
 */
class StaffAssignmentService
{
    /** Statuses a package can be (re)assigned from — before the pickup scan. */
    public const ASSIGNABLE = ['created', 'requested', 'pending', 'assigned', 'accepted'];

    public function assign(Delivery $delivery, User $staff): Delivery
    {
        if (! $delivery->partner_id) {
            throw ValidationException::withMessages(['delivery' => "#{$delivery->id} is not a Partner package."]);
        }
        if (! $staff->isSalariedStaff()) {
            throw ValidationException::withMessages(['driver_id' => 'Partner packages can only be assigned to ROTEH staff drivers.']);
        }
        if (! in_array($delivery->status, self::ASSIGNABLE, true) || $delivery->pickup_scanned_at) {
            throw ValidationException::withMessages(['delivery' => "PKG-{$delivery->id} is already picked up or closed ({$delivery->status})."]);
        }

        $previous = $delivery->driver_id && $delivery->driver_id !== $staff->id ? $delivery->driver_id : null;

        $delivery->update([
            'driver_id'       => $staff->id,
            'status'          => 'accepted',            // shows as "driver assigned"
            'assigned_at'     => now(),
            'confirmed_at'    => $delivery->confirmed_at ?? now(),
            'assignment_type' => 'manual',
        ]);

        try {
            SyncFirestore::dispatch('syncDelivery', [$delivery->fresh(['sender', 'driver', 'vehicle'])]);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->notify($delivery, $staff, $previous);

        return $delivery->fresh();
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
                    'pickup' => (string) $delivery->pickup_address,
                ]),
                data: ['type' => 'staff_package_assigned', 'delivery_id' => (string) $delivery->id]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
