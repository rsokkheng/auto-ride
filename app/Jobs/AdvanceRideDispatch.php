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
 * Fires after a ride's offer window expires. If the ride is still unclaimed
 * and no one has already advanced past this offer, moves on to the next
 * driver in the ranked dispatch queue.
 */
class AdvanceRideDispatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $rideId,
        public int $offeredAtPosition,
    ) {}

    public function handle(RideDispatchService $dispatcher): void
    {
        $ride = Ride::find($this->rideId);

        if (! $ride || ! in_array($ride->status, Ride::OPEN_STATUSES, true) || $ride->driver_id) {
            return;
        }

        $dispatcher->advance($ride, $this->offeredAtPosition);
    }
}
