<?php

namespace App\Services;

use App\Jobs\AdvanceRideDispatch;
use App\Jobs\CancelUnclaimedRide;
use App\Models\PricingSetting;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Grab-style sequential ride dispatch with expanding search radius: rank
 * drivers within the first (narrowest) radius tier and offer the ride to
 * them one at a time — best match first. If a driver doesn't accept within
 * the offer window, the next-best driver in that tier's queue is offered.
 * Once a tier's queue is exhausted, the search widens to the next radius
 * tier (never re-offering a driver already tried) — e.g. 2km, then 4km,
 * 6km, 8km — before finally falling through to the untargeted self-serve
 * window.
 */
class RideDispatchService
{
    public function __construct(
        private DriverMatchingService $matcher,
        private FcmService $fcm,
        private FirestoreService $firestore,
    ) {}

    /**
     * Rank nearby drivers within the first (narrowest) radius tier and offer
     * the ride to the best match.
     */
    public function start(Ride $ride): void
    {
        $ride->update([
            'dispatch_tier'    => 0,
            'tried_driver_ids' => [],
        ]);

        $this->dispatchAtTier($ride->fresh(), 0);
    }

    /**
     * Move past the driver currently offered the ride and offer the next one
     * in the current tier's queue (widening to the next radius tier if that
     * queue is now exhausted).
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

    /** [2.0, 4.0, 6.0, 8.0] km, admin-managed via PricingSetting, falls back to config/.env. */
    private function radiusTiers(): array
    {
        $raw   = (string) PricingSetting::get('ride_radius_tiers_km', config('ride.radius_tiers_km', '2,4,6,8'));
        $tiers = array_values(array_filter(array_map(
            fn($v) => (float) trim($v),
            explode(',', $raw)
        ), fn($v) => $v > 0));

        return $tiers ?: [8.0];
    }

    /**
     * Rank drivers within the given radius tier (excluding anyone already tried in
     * an earlier tier) and start offering that tier's queue. If every tier has been
     * exhausted, opens the untargeted self-serve window instead.
     */
    private function dispatchAtTier(Ride $ride, int $tier): void
    {
        $tiers = $this->radiusTiers();

        if ($tier >= count($tiers)) {
            $this->openSelfServeWindow($ride);
            return;
        }

        $limit  = (int) PricingSetting::get('ride_dispatch_limit', config('ride.dispatch_limit', 10));
        $radius = $tiers[$tier];
        $tried  = $ride->tried_driver_ids ?? [];

        $ranked = $this->matcher
            ->findDrivers((float) $ride->pickup_lat, (float) $ride->pickup_lng, $limit, $radius)
            ->reject(fn(User $d) => in_array($d->id, $tried, true))
            ->values();

        Log::info('radius_tier_started', [
            'ride_id' => $ride->id, 'tier' => $tier, 'radius_km' => $radius, 'candidates' => $ranked->count(),
        ]);

        $ride->update([
            'dispatch_queue'    => $ranked->pluck('id')->values()->all(),
            'dispatch_position' => 0,
            'dispatch_tier'     => $tier,
        ]);

        $this->offerAt($ride->fresh(), 0);
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

        // This tier's ranked queue is exhausted — remember everyone tried so the next
        // (wider) tier never re-offers them, then escalate to that tier.
        $tried = array_values(array_unique(array_merge($ride->tried_driver_ids ?? [], $queue)));

        Log::info('radius_tier_exhausted', [
            'ride_id' => $ride->id, 'tier' => $ride->dispatch_tier, 'queue_size' => count($queue),
        ]);

        $ride->update(['dispatch_position' => $position, 'tried_driver_ids' => $tried]);

        $this->dispatchAtTier($ride->fresh(), $ride->dispatch_tier + 1);
    }

    /**
     * Every radius tier has been exhausted — no nearby driver accepted (or none
     * were available at any tier). Ride stays "requested" so any driver can still
     * self-serve it via GET /v1/rides/available, but only for a grace window: if
     * nobody claims it in that time, auto-cancel rather than leaving it stuck
     * forever with no driver ever notified again.
     */
    private function openSelfServeWindow(Ride $ride): void
    {
        Log::info('ranked_queue_exhausted', ['ride_id' => $ride->id]);

        $windowSeconds = (int) PricingSetting::get('ride_self_serve_window_seconds', config('ride.self_serve_window_seconds', 60));
        $expiresAt     = now()->addSeconds($windowSeconds);

        $ride->update(['self_serve_expires_at' => $expiresAt]);

        Log::info('self_serve_started', ['ride_id' => $ride->id, 'expires_at' => $expiresAt->toIso8601String(), 'window_seconds' => $windowSeconds]);

        CancelUnclaimedRide::dispatch($ride->id)
            ->delay($expiresAt);
    }

    /**
     * Cancels a ride nobody ever claimed — the ranked queue was exhausted and the
     * self-serve grace window expired with no driver picking it up.
     *
     * Atomic: the status/driver_id guard is enforced in the same UPDATE statement
     * (not a separate read-then-write) so a driver's concurrent accept() can never
     * be silently clobbered by this cancellation, and vice versa — only one wins.
     */
    public function autoCancelNoDriver(Ride $ride): void
    {
        if ($ride->self_serve_expires_at && $ride->self_serve_expires_at->isFuture()) {
            return; // window was extended/reset since this job was scheduled
        }

        Log::info('self_serve_expired', ['ride_id' => $ride->id]);

        $affected = Ride::where('id', $ride->id)
            ->where('status', Ride::STATUS_REQUESTED)
            ->whereNull('driver_id')
            ->update([
                'status'              => Ride::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => 'no_driver_available',
                'cancellation_fee'    => 0,
            ]);

        if ($affected === 0) {
            // A driver claimed it in the same instant this job ran — nothing to cancel.
            return;
        }

        Log::info('auto_cancelled', ['ride_id' => $ride->id, 'reason' => 'no_driver_available']);

        try {
            $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');
            if ($fresh->passenger) {
                $this->fcm->rideRejected($fresh->passenger, $fresh->id);
            }
            $this->firestore->syncRide($fresh);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
