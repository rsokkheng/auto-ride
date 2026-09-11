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
 * Queued off the back of the ride-created Kafka event: finds nearby drivers
 * and starts the ranked dispatch offer queue for the ride.
 */
class ProcessRideCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $rideId,
    ) {}

    public function handle(RideDispatchService $dispatcher): void
    {
        $ride = Ride::find($this->rideId);

        if (! $ride || $ride->status !== Ride::STATUS_REQUESTED) {
            return;
        }

        $dispatcher->start($ride);
    }
}
