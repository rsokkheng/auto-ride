<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DriverMatchingService
{
    private float $radiusKm;
    private float $distanceWeight;
    private float $ratingWeight;

    public function __construct()
    {
        $this->radiusKm      = (float) config('delivery.match_radius_km', 30);
        $this->distanceWeight = (float) config('delivery.match_distance_weight', 0.7);
        $this->ratingWeight   = (float) config('delivery.match_rating_weight', 1.5);
    }

    /**
     * Return available drivers ranked by a weighted score of distance and rating.
     *
     * Score = distance_km * distanceWeight + (5.0 - rating) * ratingWeight
     * Lower score = better match.
     */
    public function findDrivers(float $pickupLat, float $pickupLng, int $limit = 10): Collection
    {
        $drivers = User::where('role', 'driver')
            ->where('available', true)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->with(['vehicles' => fn($q) => $q->where('status', 'active')])
            ->get();

        if ($drivers->isEmpty()) {
            return collect();
        }

        $googleDistances = $this->fetchGoogleDistances($drivers, $pickupLat, $pickupLng);

        return $drivers
            ->map(function (User $driver) use ($pickupLat, $pickupLng, $googleDistances) {
                $distanceKm = $googleDistances[$driver->id]
                    ?? $this->haversineDistance(
                        (float) $driver->current_latitude,
                        (float) $driver->current_longitude,
                        $pickupLat,
                        $pickupLng
                    );

                $driver->distance_km   = round($distanceKm, 2);
                $driver->score         = round(
                    $distanceKm * $this->distanceWeight + (5.0 - (float) $driver->rating) * $this->ratingWeight,
                    4
                );
                $driver->distance_source = isset($googleDistances[$driver->id]) ? 'google_maps' : 'haversine';

                return $driver;
            })
            ->filter(fn(User $d) => $d->distance_km <= $this->radiusKm)
            ->sortBy('score')
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
