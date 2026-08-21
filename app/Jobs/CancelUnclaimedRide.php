<?php

namespace App\Jobs;

use App\Models\Ride;
use App\Services\RideDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fires after a ride's self-serve grace window expires (ranked dispatch queue
 * was exhausted with no driver accepting). If the ride is still unclaimed,
 * auto-cancels it rather than leaving it stuck in "requested" forever.
 */
class CancelUnclaimedRide implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $rideId,
    ) {}

    public function handle(RideDispatchService $dispatcher): void
    {
        $ride = Ride::find($this->rideId);

        if (! $ride || ! in_array($ride->status, Ride::OPEN_STATUSES, true) || $ride->driver_id) {
            return;
        }

        $dispatcher->autoCancelNoDriver($ride);
    }
}
