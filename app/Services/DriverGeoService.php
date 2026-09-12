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
     *
     * Uses GEORADIUS, not GEOSEARCH — GEOSEARCH needs Redis >= 6.2 and
     * production runs 6.0.16, where it's an unknown command. phpredis
     * returns `false` for an unknown command rather than throwing, which
     * used to reach the foreach below and crash every dispatch job with
     * zero candidates ever found (self-serve, then auto-cancel, every time).
     * GEORADIUS is deprecated upstream but Redis keeps it working for
     * exactly this reason; switch to GEOSEARCH once the server is upgraded.
     *
     * Response shape differs from GEOSEARCH too: phpredis returns a plain
     * indexed list of [memberId, distance] pairs here, not an associative
     * memberId => [distance] map.
     */
    public function nearby(float $lat, float $lng, float $radiusKm, int $limit): array
    {
        try {
            $rows = Redis::georadius(
                self::KEY,
                $lng,
                $lat,
                $radiusKm,
                'km',
                ['withdist', 'asc', 'count' => $limit],
            );
        } catch (Throwable $e) {
            Log::error('DriverGeoService::nearby georadius failed', ['error' => $e->getMessage()]);
            return [];
        }

        // A Redis error (e.g. an unsupported command on an older server) can
        // surface as `false` here instead of an exception (the empty-key
        // case returns [], not false). A bare foreach over that crashed
        // every dispatch job with no nearby drivers ever found.
        if (! is_array($rows)) {
            Log::error('DriverGeoService::nearby georadius returned non-array', ['result' => $rows]);
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            [$driverId, $distance] = $row;
            $result[(int) $driverId] = (float) $distance;
        }

        return $result;
    }
}
