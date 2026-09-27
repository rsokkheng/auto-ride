<?php

namespace App\Services;

use App\Models\Ride;

/**
 * Measures a "book without destination" (metered) trip from the GPS track the
 * driver app posts to ride_locations every ~10 s, instead of a straight line
 * from pickup to wherever the trip ended — which underpaid any trip that
 * wound around or came back near its start.
 *
 * Track hygiene, so GPS noise can't inflate the fare:
 *  - points outside the service area are dropped (simulators, stale fixes);
 *  - a point implying > MAX_SPEED_KMH from the last kept point is a spike;
 *  - the meter only advances once the car is MIN_STEP_M from the last counted
 *    point, so a parked car's jitter never accumulates (slow real movement
 *    still counts — it just moves the anchor a bit later).
 *
 * Floor: the result is never below the shortest possible pickup → end distance
 * (Google road route, else straight line). Covers track gaps, e.g. the app was
 * backgrounded and stopped posting for a few minutes.
 */
class TripMeterService
{
    private const MAX_SPEED_KMH = 130;
    private const MIN_STEP_M    = 15;

    public function __construct(private FareService $fare) {}

    /**
     * @return array{distance_km: float, duration_min: int, distance_text: string,
     *               duration_text: string, source: string, track_points: int, track_km: float, floor_km: float}
     */
    public function measure(Ride $ride, ?float $endLat = null, ?float $endLng = null): array
    {
        $startedAt = $ride->started_at ?? $ride->updated_at;

        $points = [];
        if ($ride->pickup_lat !== null && $ride->pickup_lng !== null) {
            $points[] = [(float) $ride->pickup_lat, (float) $ride->pickup_lng, $startedAt->getTimestamp()];
        }
        foreach ($ride->locations()->where('created_at', '>=', $startedAt)->orderBy('created_at')->orderBy('id')
                     ->get(['latitude', 'longitude', 'created_at']) as $loc) {
            $points[] = [(float) $loc->latitude, (float) $loc->longitude, $loc->created_at->getTimestamp()];
        }
        if ($endLat !== null && $endLng !== null) {
            $points[] = [$endLat, $endLng, now()->getTimestamp()];
        }

        $trackKm = $this->trackDistanceKm($points);

        // Lower bound: nobody can drive pickup → end shorter than this.
        $floorKm = 0.0;
        $end = $this->lastInArea($points);
        if ($end && $ride->pickup_lat !== null) {
            $route   = $this->fare->getRoute((float) $ride->pickup_lat, (float) $ride->pickup_lng, $end[0], $end[1]);
            $floorKm = $route['source'] === 'google_maps'
                ? (float) $route['distance_km']
                : $this->haversineKm((float) $ride->pickup_lat, (float) $ride->pickup_lng, $end[0], $end[1]);
        }

        $distanceKm  = round(max($trackKm, $floorKm), 2);
        $durationMin = max(1, (int) ceil(($startedAt->diffInSeconds(now(), true)) / 60));

        return [
            'distance_km'   => $distanceKm,
            'duration_min'  => $durationMin,
            'distance_text' => round($distanceKm, 1) . ' km',
            'duration_text' => $durationMin . ' mins',
            'source'        => $trackKm >= $floorKm ? 'gps_track' : 'route_floor',
            'track_points'  => count($points),
            'track_km'      => round($trackKm, 2),
            'floor_km'      => round($floorKm, 2),
        ];
    }

    /** @param array<array{0: float, 1: float, 2: int}> $points [lat, lng, unix_ts] in time order */
    private function trackDistanceKm(array $points): float
    {
        $meters = 0.0;
        $anchor = null; // last counted point

        foreach ($points as $p) {
            if (! FareService::inServiceArea($p[0], $p[1])) {
                continue;
            }
            if ($anchor === null) {
                $anchor = $p;
                continue;
            }

            $step = $this->haversineKm($anchor[0], $anchor[1], $p[0], $p[1]) * 1000;
            if ($step < self::MIN_STEP_M) {
                continue; // jitter / stationary — keep the anchor where it is
            }

            $seconds = max(1, $p[2] - $anchor[2]);
            if (($step / 1000) / ($seconds / 3600) > self::MAX_SPEED_KMH) {
                continue; // GPS spike
            }

            $meters += $step;
            $anchor  = $p;
        }

        return $meters / 1000;
    }

    private function lastInArea(array $points): ?array
    {
        for ($i = count($points) - 1; $i >= 0; $i--) {
            if (FareService::inServiceArea($points[$i][0], $points[$i][1])) {
                return $points[$i];
            }
        }
        return null;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
