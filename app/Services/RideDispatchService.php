<?php

namespace App\Services;

use App\Jobs\AdvanceRideDispatch;
use App\Models\PricingSetting;
use App\Models\Ride;
use App\Models\User;

/**
 * Grab-style sequential ride dispatch: rank nearby drivers once, then offer
 * the ride to them one at a time (best match first). If a driver doesn't
 * accept within the offer window, the next-best driver in the queue is
 * offered automatically.
 */
class RideDispatchService
{
    public function __construct(
        private DriverMatchingService $matcher,
        private FcmService $fcm,
    ) {}

    /**
     * Rank nearby drivers for a newly created ride and offer it to the best match.
     */
    public function start(Ride $ride): void
    {
        // Admin-managed via PricingSetting (Admin > Pricing Settings), falls back to config/.env.
        $radiusKm = (float) PricingSetting::get('ride_match_radius_km', config('ride.match_radius_km', 8));
        $limit    = (int) PricingSetting::get('ride_dispatch_limit', config('ride.dispatch_limit', 10));

        $ranked = $this->matcher->findDrivers(
            (float) $ride->pickup_lat,
            (float) $ride->pickup_lng,
            $limit,
            $radiusKm
        );

        $ride->update([
            'dispatch_queue'    => $ranked->pluck('id')->values()->all(),
            'dispatch_position' => 0,
        ]);

        $this->offerAt($ride, 0);
    }

    /**
     * Move past the driver currently offered the ride and offer the next one in the queue.
     *
     * $fromPosition guards against double-advancing when a driver explicitly rejects
     * right before their offer window times out — only one of the two should proceed.
     */
    public function advance(Ride $ride, ?int $fromPosition = null): void
    {
        if ($fromPosition !== null && (int) $ride->dispatch_position !== $fromPosition) {
            return;
        }

        $this->offerAt($ride, ((int) $ride->dispatch_position) + 1);
    }

    private function offerAt(Ride $ride, int $position): void
    {
        $queue = $ride->dispatch_queue ?? [];

        while ($position < count($queue)) {
            $driver = User::where('id', $queue[$position])->where('available', true)->first();

            if ($driver) {
                $ride->update([
                    'dispatch_position'   => $position,
                    'dispatch_offered_at' => now(),
                ]);

                $this->fcm->rideRequested(
                    $driver,
                    $ride->id,
                    $ride->pickup_address,
                    $ride->dropoff_address ?? 'Destination TBD',
                    $ride->passenger->name ?? '',
                    (int) $ride->fare
                );

                $timeoutSeconds = (int) PricingSetting::get('ride_offer_timeout_seconds', config('ride.offer_timeout_seconds', 15));

                AdvanceRideDispatch::dispatch($ride->id, $position)
                    ->delay(now()->addSeconds($timeoutSeconds));

                return;
            }

            $position++;
        }

        // Ranked queue exhausted — ride stays open; drivers can still self-serve via GET /v1/rides/available.
        $ride->update(['dispatch_position' => $position]);
    }
}
