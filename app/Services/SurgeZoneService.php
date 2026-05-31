<?php

namespace App\Services;

use App\Models\SurgeZone;
use Illuminate\Support\Collection;

class SurgeZoneService
{
    /** Haversine great-circle distance in km. */
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * All currently active surge zones that contain the given coordinates,
     * sorted by multiplier descending (highest surge first).
     *
     * @param  string  $type  'rides' | 'deliveries' | 'both'
     */
    public function getZonesAt(float $lat, float $lng, string $type = 'both'): Collection
    {
        $now = now();

        return SurgeZone::where('active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(fn($q) => $q->where('type', 'both')->orWhere('type', $type))
            ->get()
            ->filter(fn($zone) =>
                $this->distanceKm($lat, $lng, $zone->center_lat, $zone->center_lng) <= $zone->radius_km
            )
            ->sortByDesc('multiplier')
            ->values();
    }

    /**
     * Highest surge multiplier at the given location.
     * Returns 1.0 (no surge) when outside all active zones.
     */
    public function getMultiplier(float $lat, float $lng, string $type = 'both'): float
    {
        $zone = $this->getZonesAt($lat, $lng, $type)->first();
        return $zone ? (float) $zone->multiplier : 1.0;
    }

    /**
     * The highest-priority active surge zone at the given location, or null.
     */
    public function getActiveZone(float $lat, float $lng, string $type = 'both'): ?SurgeZone
    {
        return $this->getZonesAt($lat, $lng, $type)->first();
    }

    /**
     * All zones that are currently active (for map/driver display).
     * When $type is not 'both', also includes zones of type 'both'.
     */
    public function getAllActive(?string $type = null): Collection
    {
        $now = now();

        $query = SurgeZone::where('active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));

        if ($type && $type !== 'both') {
            $query->where(fn($q) => $q->where('type', 'both')->orWhere('type', $type));
        }

        return $query->orderByDesc('multiplier')->get();
    }

    /**
     * Apply surge to a base fare/fee and return breakdown.
     *
     * @return array{base: int, multiplier: float, surge_amount: int, total: int, zone: ?SurgeZone}
     */
    public function applyTo(int $baseFare, float $lat, float $lng, string $type = 'both'): array
    {
        $zone       = $this->getActiveZone($lat, $lng, $type);
        $multiplier = $zone ? $zone->multiplier : 1.0;
        $raw        = (int) round($baseFare * $multiplier);
        $total      = (int) (ceil($raw / 100) * 100); // round up to nearest 100 ៛

        return [
            'base'         => $baseFare,
            'multiplier'   => $multiplier,
            'surge_amount' => $total - $baseFare,
            'total'        => $total,
            'zone'         => $zone,
        ];
    }
}
