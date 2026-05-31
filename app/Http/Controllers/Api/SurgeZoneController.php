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

        $type  = in_array($request->query('type'), ['rides', 'deliveries', 'both'], true)
            ? $request->query('type')
            : null;

        $zones = $this->surge->getAllActive($type)->map(fn($z) => [
            'id'          => $z->id,
            'name'        => $z->name,
            'description' => $z->description,
            'center_lat'  => $z->center_lat,
            'center_lng'  => $z->center_lng,
            'radius_km'   => $z->radius_km,
            'multiplier'  => $z->multiplier,
            'type'        => $z->type,
            'ends_at'     => $z->ends_at?->toIso8601String(),
        ]);

        return $this->success([
            'zones' => $zones,
            'total' => $zones->count(),
        ]);
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
            'type' => 'nullable|in:rides,deliveries,both',
        ]);

        $type = $data['type'] ?? 'both';
        $zone = $this->surge->getActiveZone((float) $data['lat'], (float) $data['lng'], $type);
        $multiplier = $zone ? $zone->multiplier : 1.0;

        return $this->success([
            'lat'          => (float) $data['lat'],
            'lng'          => (float) $data['lng'],
            'surge_active' => $multiplier > 1.0,
            'multiplier'   => $multiplier,
            'surge_pct'    => round(($multiplier - 1) * 100),
            'zone'         => $zone ? [
                'id'        => $zone->id,
                'name'      => $zone->name,
                'radius_km' => $zone->radius_km,
                'ends_at'   => $zone->ends_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
