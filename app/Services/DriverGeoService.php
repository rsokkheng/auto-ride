<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Redis GEO-backed index of available drivers' live positions, replacing a
 * full-table MySQL scan for "who is near this pickup". Kept in sync by the
 * driver-availability and location-update endpoints (DriverController) and
 * the admin penalty action (AdminController::penalizeDriver).
 */
class DriverGeoService
{
    private const KEY = 'drivers:geo';

    /** Add/update a driver's position in the geo index (call whenever they're available). */
    public function add(int $driverId, float $lat, float $lng): void
    {
        Redis::geoadd(self::KEY, $lng, $lat, (string) $driverId);
    }

    /** Drop a driver from the geo index (offline, penalized, or unavailable). */
    public function remove(int $driverId): void
    {
        Redis::zrem(self::KEY, (string) $driverId);
    }

    /**
     * Nearby available drivers within $radiusKm, nearest first.
     * Returns [driver_id => distance_km].
     */
    public function nearby(float $lat, float $lng, float $radiusKm, int $limit): array
    {
        $rows = Redis::geosearch(
            self::KEY,
            [$lng, $lat],
            $radiusKm,
            'km',
            ['WITHDIST', 'ASC', 'COUNT' => $limit],
        );

        $result = [];
        foreach ($rows as $driverId => $data) {
            $result[(int) $driverId] = (float) $data[0];
        }

        return $result;
    }
}
