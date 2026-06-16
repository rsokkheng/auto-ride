<?php

namespace App\Services;

use App\Models\SurgeZone;
use Carbon\Carbon;
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
     * All active zones that contain the given coordinates, sorted by
     * multiplier descending (highest surge first).
     *
     * Applies both the one-time date window AND the recurring schedule.
     */
    public function getZonesAt(float $lat, float $lng, string $type = 'both'): Collection
    {
        $now = now();

        return $this->candidateZones($type, $now)
            ->filter(fn($zone) =>
                $this->distanceKm($lat, $lng, $zone->center_lat, $zone->center_lng) <= $zone->radius_km
                && $zone->isActiveNow($now)
            )
            ->sortByDesc('multiplier')
            ->values();
    }

    /** Highest surge multiplier at a location (1.0 = no surge). */
    public function getMultiplier(float $lat, float $lng, string $type = 'both'): float
    {
        return (float) ($this->getZonesAt($lat, $lng, $type)->first()?->multiplier ?? 1.0);
    }

    /** Best surge zone at a location, or null. */
    public function getActiveZone(float $lat, float $lng, string $type = 'both'): ?SurgeZone
    {
        return $this->getZonesAt($lat, $lng, $type)->first();
    }

    /**
     * All currently active zones (for map overlay / driver display).
     */
    public function getAllActive(?string $type = null): Collection
    {
        $now = now();

        return $this->candidateZones($type, $now)
            ->filter(fn($zone) => $zone->isActiveNow($now))
            ->sortByDesc('multiplier')
            ->values();
    }

    /**
     * Apply surge multiplier to a base fare and return full breakdown.
     *
     * @return array{base:int, multiplier:float, surge_amount:int, total:int, zone:?SurgeZone}
     */
    public function applyTo(int $baseFare, float $lat, float $lng, string $type = 'both'): array
    {
        $zone       = $this->getActiveZone($lat, $lng, $type);
        $multiplier = $zone ? $zone->multiplier : 1.0;
        $raw        = (int) round($baseFare * $multiplier);
        $total      = (int) (ceil($raw / 100) * 100);

        return [
            'base'         => $baseFare,
            'multiplier'   => $multiplier,
            'surge_amount' => $total - $baseFare,
            'total'        => $total,
            'zone'         => $zone,
        ];
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Pre-filter zones from DB: active flag + one-time date window + type.
     * Recurring schedule is checked in PHP via isActiveNow().
     */
    private function candidateZones(?string $type, Carbon $now): Collection
    {
        $query = SurgeZone::where('active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));

        if ($type && $type !== 'both') {
            $query->where(function ($q) use ($type) {
                $q->where('type', 'both');

                match ($type) {
                    // All delivery types: match zones targeting any delivery variant
                    'deliveries' => $q->orWhereIn('type', ['deliveries', 'delivery', 'moving']),
                    // Package delivery: matches "all deliveries" zone or specific "delivery" zone
                    'delivery'   => $q->orWhereIn('type', ['deliveries', 'delivery']),
                    // Moving service: matches "all deliveries" zone or specific "moving" zone
                    'moving'     => $q->orWhereIn('type', ['deliveries', 'moving']),
                    // Rides: exact match
                    default      => $q->orWhere('type', $type),
                };
            });
        }

        return $query->get();
    }
}
