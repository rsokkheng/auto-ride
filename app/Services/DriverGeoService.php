<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Redis GEO-backed index of available drivers' live positions, replacing a
 * full-table MySQL scan for "who is near this pickup". Kept in sync by
 * User::booted() on every write to a driver's available/location/penalty
 * fields — see that model, not individual controllers.
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
        try {
            $rows = Redis::geosearch(
                self::KEY,
                [$lng, $lat],
                $radiusKm,
                'km',
                ['WITHDIST', 'ASC', 'COUNT' => $limit],
            );
        } catch (Throwable $e) {
            Log::error('DriverGeoService::nearby geosearch failed', ['error' => $e->getMessage()]);
            return [];
        }

        // A Redis error/timeout can surface as `false` here instead of an
        // exception (the empty-key case returns [], not false — this is
        // specifically a connection/command failure). A bare foreach over
        // that crashed every dispatch job with no nearby drivers ever found.
        if (! is_array($rows)) {
            Log::error('DriverGeoService::nearby geosearch returned non-array', ['result' => $rows]);
            return [];
        }

        $result = [];
        foreach ($rows as $driverId => $data) {
            $result[(int) $driverId] = (float) $data[0];
        }

        return $result;
    }
}
