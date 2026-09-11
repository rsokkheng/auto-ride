<?php

namespace App\Console\Commands;

use App\Models\Ride;
use App\Models\User;
use App\Services\FirestoreService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rides:auto-cancel-timed-out')]
#[Description('Auto-cancel rides where driver arrived but passenger did not board within timeout period.')]
class AutoCancelTimedOutRides extends Command
{
    public function handle(FirestoreService $firestore): void
    {
        $timedOut = Ride::where('status', Ride::STATUS_DRIVER_ARRIVED)
            ->whereNotNull('pickup_timeout_at')
            ->where('pickup_timeout_at', '<', now())
            ->get();

        foreach ($timedOut as $ride) {
            $ride->update([
                'status'              => Ride::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => 'passenger_no_show_timeout',
                'cancellation_fee'    => (int) config('ride.cancellation_fee', 2000),
            ]);

            // Ride reached DRIVER_ARRIVED, so the driver was marked busy at
            // accept() and was never freed by this bypass of cancel() — free
            // them now (User::booted() re-adds them to Redis GEO).
            if ($ride->driver_id) {
                User::find($ride->driver_id)?->update(['available' => true]);
            }

            $firestore->syncRide($ride->fresh()->load('driver', 'vehicle'));
        }

        $this->info("Auto-cancelled {$timedOut->count()} timed-out ride(s).");
    }
}
