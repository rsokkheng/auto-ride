<?php

namespace App\Services;

use App\Models\PricingSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DriverMatchingService
{
    private float $radiusKm;
    private float $distanceWeight;
    private float $etaWeight;
    private float $ratingWeight;
    private int $googleCandidates;

    public function __construct(
        private DriverGeoService $geo,
    ) {
        // Admin-managed via PricingSetting (Admin > Pricing Settings), falls back to config/.env.
        $this->radiusKm        = (float) PricingSetting::get('delivery_match_radius_km', config('delivery.match_radius_km', 30));
        $this->distanceWeight  = (float) PricingSetting::get('driver_match_distance_weight', config('delivery.match_distance_weight', 6));
        $this->etaWeight       = (float) PricingSetting::get('driver_match_eta_weight', config('delivery.match_eta_weight', 1.5));
        $this->ratingWeight    = (float) PricingSetting::get('driver_match_rating_weight', config('delivery.match_rating_weight', 4));
        $this->googleCandidates = (int) config('delivery.match_google_candidates', 15);
    }

    /**
     * Return available drivers ranked by a weighted score of distance, ETA and rating.
     *
     * Score = 100 - (distance_km * distanceWeight) - (eta_minutes * etaWeight)
     *             - ((5.0 - rating) * ratingWeight), clamped to [0, 100].
     * Higher score = better match (Grab-style: 0-100, best driver first).
     */
    public function findDrivers(float $pickupLat, float $pickupLng, int $limit = 10, ?float $radiusKm = null): Collection
    {
        $radius = $radiusKm ?? $this->radiusKm;

        // Redis GEO does the radius search + distance calc, so we only ever
        // hydrate the drivers that are actually in range from MySQL (instead
        // of scanning every available driver row on every dispatch attempt).
        // Over-fetch a bit before ranking, since penalty/rating filtering
        // below can drop some of these candidates.
        $nearby = $this->geo->nearby($pickupLat, $pickupLng, $radius, $limit * 3);

        if (empty($nearby)) {
            return collect();
        }

        $drivers = User::whereIn('id', array_keys($nearby))
            ->where('role', 'driver')
            ->where('available', true)
            ->where(fn ($q) => $q->whereNull('penalty_until')->orWhere('penalty_until', '<=', now()))
            ->with(['vehicles' => fn($q) => $q->where('status', 'active')->latest()->limit(1)])
            ->get();

        if ($drivers->isEmpty()) {
            return collect();
        }

        // Only refine the closest candidates via Google — beyond that, extra API
        // calls rarely change who ranks in the top $limit, so cap regardless of
        // how many drivers Redis GEO returned in range.
        $googleCandidates = $drivers
            ->sortBy(fn (User $d) => $nearby[$d->id])
            ->take($this->googleCandidates);

        $googleDistances = $this->fetchGoogleDistances($googleCandidates, $pickupLat, $pickupLng);

        return $drivers
            ->map(function (User $driver) use ($nearby, $googleDistances) {
                $distanceKm = $googleDistances[$driver->id] ?? $nearby[$driver->id];

                $etaMinutes = (int) ceil(($distanceKm / 30) * 60); // avg 30 km/h city speed

                $rawScore = 100
                    - ($distanceKm * $this->distanceWeight)
                    - ($etaMinutes * $this->etaWeight)
                    - ((5.0 - (float) $driver->rating) * $this->ratingWeight);

                $driver->distance_km     = round($distanceKm, 2);
                $driver->eta_minutes     = $etaMinutes;
                $driver->score           = (int) round(max(0, min(100, $rawScore)));
                $driver->distance_source = isset($googleDistances[$driver->id]) ? 'google_maps' : 'redis_geo';

                return $driver;
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Haversine great-circle distance between two coordinates (in km).
     */
    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Fetch actual road distances via Google Maps Distance Matrix API.
     * Returns a map of driver_id => distance_km, or null on failure.
     * Falls back transparently — callers receive null and use Haversine instead.
     *
     * Google allows up to 25 origins per request; we batch accordingly.
     */
    private function fetchGoogleDistances(Collection $drivers, float $destLat, float $destLng): array
    {
        $apiKey = config('services.google_maps.key');

        if (empty($apiKey)) {
            return [];
        }

        $result = [];
        $destination = "{$destLat},{$destLng}";

        foreach ($drivers->chunk(25) as $chunk) {
            $origins = $chunk->map(fn(User $d) => "{$d->current_latitude},{$d->current_longitude}")->implode('|');

            try {
                $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins'      => $origins,
                    'destinations' => $destination,
                    'mode'         => 'driving',
                    'key'          => $apiKey,
                ]);

                if (! $response->ok()) {
                    continue;
                }

                $data = $response->json();

                if (($data['status'] ?? '') !== 'OK') {
                    Log::warning('Google Maps Distance Matrix error', ['status' => $data['status'] ?? 'unknown']);
                    continue;
                }

                foreach ($chunk->values() as $index => $driver) {
                    $element = $data['rows'][$index]['elements'][0] ?? null;

                    if ($element && ($element['status'] ?? '') === 'OK') {
                        $result[$driver->id] = $element['distance']['value'] / 1000.0;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Google Maps Distance Matrix request failed: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Recalculate and persist a driver's aggregate rating after a new score is submitted.
     */
    public function recalculateDriverRating(User $driver): void
    {
        $total = $driver->total_ratings + 1;
        $newRating = round(
            (($driver->rating * $driver->total_ratings) + 0) / $total,
            2
        );

        // Callers pass the actual new_score; this helper is called after the delivery
        // rating is already saved, so we recompute from the raw average in the DB.
        // We keep this method as a no-op placeholder; actual update is in DeliveryController::rate().
    }
}
