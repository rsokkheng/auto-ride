<?php

namespace App\Http\Controllers\Api;

use App\Services\SurgeZoneService;
use Illuminate\Http\Request;

class SurgeZoneController extends ApiController
{
    public function __construct(private SurgeZoneService $surge) {}

    /**
     * GET /v1/surge/zones?type=rides|deliveries|both
     *
     * Returns all currently active surge zones for map display.
     * Drivers use this to see where surge pricing is active.
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $type = in_array($request->query('type'), ['rides', 'deliveries', 'delivery', 'moving', 'both'], true)
            ? $request->query('type')
            : null;

        $lat = $request->query('lat') !== null ? (float) $request->query('lat') : null;
        $lng = $request->query('lng') !== null ? (float) $request->query('lng') : null;

        $zones = $this->surge->getAllActive($type)->map(function ($z) use ($lat, $lng) {
            $distanceKm = null;
            $insideZone = false;

            if ($lat !== null && $lng !== null) {
                $distanceKm = $this->haversineKm($lat, $lng, $z->center_lat, $z->center_lng);
                $insideZone = $distanceKm <= $z->radius_km;
            }

            return [
                'id'          => $z->id,
                'name'        => $z->name,
                'description' => $z->description,
                'center_lat'  => $z->center_lat,
                'center_lng'  => $z->center_lng,
                'radius_km'   => $z->radius_km,
                'multiplier'  => $z->multiplier,
                'type'        => $z->type,
                'ends_at'     => $z->ends_at?->toIso8601String(),
                'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
                'you_are_inside' => $insideZone,
            ];
        });

        // Sort nearest first when coords provided
        if ($lat !== null && $lng !== null) {
            $zones = $zones->sortBy('distance_km')->values();
        }

        return $this->success([
            'zones' => $zones,
            'total' => $zones->count(),
        ]);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R  = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * GET /v1/surge/check?lat=...&lng=...&type=rides
     *
     * Check surge multiplier at a specific location.
     * Call this before booking to show the surge warning to the passenger.
     */
    public function check(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'lat'  => 'required|numeric|between:-90,90',
            'lng'  => 'required|numeric|between:-180,180',
            'type' => 'nullable|in:rides,deliveries,delivery,moving,both',
        ]);

        $type = $data['type'] ?? 'both';
        $zone = $this->surge->getActiveZone((float) $data['lat'], (float) $data['lng'], $type);
        $multiplier = $zone ? $zone->multiplier : 1.0;

        return $this->success([
            'lat'          => (float) $data['lat'],
            'lng'          => (float) $data['lng'],
            'active'       => $multiplier > 1.0,
            'surge_active' => $multiplier > 1.0,
            'multiplier'   => $multiplier,
            'surge_pct'    => round(($multiplier - 1) * 100),
            'zone'         => $zone ? $zone->name : null,
            'message'      => $multiplier > 1.0
                ? "High demand in your area. Fares are {$zone->multiplier}x the normal rate."
                : 'Normal pricing is in effect.',
            'zone_detail'  => $zone ? [
                'id'        => $zone->id,
                'name'      => $zone->name,
                'radius_km' => $zone->radius_km,
                'ends_at'   => $zone->ends_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
