<?php

namespace App\Services;

use App\Models\PricingSetting;
use App\Models\RidePricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FareService — Grab/PassApp-style fare calculation for Cambodia market.
 *
 * Route distance & duration:
 *   Primary  → Google Maps Directions API (real road distance + traffic ETA)
 *   Fallback → Haversine formula (straight-line × 1.35 road factor)
 *
 * Fare formula (rides):
 *   subtotal = booking_fee + base_fare + (per_km × distance) + (per_min × traffic_min)
 *   + night_surcharge (22:00–05:00, +20%)
 *   × surge_multiplier
 *   rounded up to nearest 100 ៛, floored at minimum_fare
 *
 * Fare formula (deliveries):
 *   subtotal = booking_fee + base_fare + (per_km × distance) + package_surcharge
 *   + night_surcharge (+15%)
 *   × surge_multiplier
 */
class FareService
{
    public function __construct(private SurgeZoneService $surge) {}

    // ── Route ─────────────────────────────────────────────────────────────────

    /**
     * Get route info between two coordinates.
     * Returns distance_km, duration_min, and the data source used.
     */
    public function getRoute(float $oLat, float $oLng, float $dLat, float $dLng): array
    {
        $apiKey = config('services.google_maps.key');

        if ($apiKey) {
            try {
                $res = Http::timeout(6)->get('https://maps.googleapis.com/maps/api/directions/json', [
                    'origin'               => "{$oLat},{$oLng}",
                    'destination'          => "{$dLat},{$dLng}",
                    'mode'                 => 'driving',
                    'departure_time'       => 'now',
                    'traffic_model'        => 'best_guess',
                    'key'                  => $apiKey,
                ]);

                if ($res->ok() && $res->json('status') === 'OK') {
                    $leg = $res->json('routes.0.legs.0');

                    // Use duration_in_traffic when available (more accurate).
                    $durationSec = $leg['duration_in_traffic']['value']
                                ?? $leg['duration']['value'];

                    return [
                        'distance_km'   => round($leg['distance']['value'] / 1000, 2),
                        'duration_min'  => (int) ceil($durationSec / 60),
                        'distance_text' => $leg['distance']['text'],
                        'duration_text' => $leg['duration_in_traffic']['text'] ?? $leg['duration']['text'],
                        'source'        => 'google_maps',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[FareService] Google Maps Directions failed: ' . $e->getMessage());
            }
        }

        // Haversine fallback — multiply by 1.35 road-factor for city roads.
        $distanceKm = round($this->haversine($oLat, $oLng, $dLat, $dLng) * 1.35, 2);
        $avgSpeed   = (int) $this->setting('avg_city_speed_kmh', 30);
        $durationMin = (int) ceil(($distanceKm / $avgSpeed) * 60);

        return [
            'distance_km'   => $distanceKm,
            'duration_min'  => $durationMin,
            'distance_text' => round($distanceKm, 1) . ' km',
            'duration_text' => $durationMin . ' mins',
            'source'        => 'haversine',
        ];
    }

    // ── Ride Fare ─────────────────────────────────────────────────────────────

    /**
     * Calculate ride fare — all service types.
     *
     * @param  string      $serviceType  motorcycle|tuk_tuk|standard|premium|shared|van
     * @param  array       $route        Output of getRoute()
     * @param  float|null  $pickupLat    For surge zone lookup
     * @param  float|null  $pickupLng
     * @return array
     */
    public function calculateRideFare(
        string $serviceType,
        array  $route,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): array {
        $p = $this->ridePricing($serviceType);

        $distanceKm  = max(1.0, (float) $route['distance_km']);
        $durationMin = max(1,   (int)   $route['duration_min']);

        // ── Component fares ──────────────────────────────────────────────────
        $baseFare    = (int) $p['base'];
        $bookingFee  = (int) $p['booking_fee'];
        $distanceFare = (int) ceil($distanceKm * $p['per_km']);

        // Time surcharge only applies in heavy traffic (below threshold speed).
        $trafficThreshold = (float) $this->setting('traffic_speed_threshold_kmh', 20);
        $avgSpeedKmh      = ($distanceKm / ($durationMin / 60));
        $timeFare         = $avgSpeedKmh < $trafficThreshold
            ? (int) ceil($durationMin * $p['per_min'])
            : 0;

        $subtotal = $baseFare + $bookingFee + $distanceFare + $timeFare;

        // ── Night surcharge ──────────────────────────────────────────────────
        $hour            = (int) now()->format('H');
        $isNight         = $hour >= 22 || $hour < 5;
        $nightRate       = (float) $this->setting('night_surcharge_rate', 0.20);
        $nightSurcharge  = $isNight ? (int) ceil($subtotal * $nightRate) : 0;
        $subtotalWithNight = $subtotal + $nightSurcharge;

        // ── Surge pricing ────────────────────────────────────────────────────
        $surgeZone       = null;
        $surgeMultiplier = 1.0;

        if ($pickupLat !== null && $pickupLng !== null) {
            $surgeZone       = $this->surge->getActiveZone($pickupLat, $pickupLng, 'rides');
            $surgeMultiplier = $surgeZone ? (float) $surgeZone->multiplier : 1.0;
        }

        $raw   = (int) round($subtotalWithNight * $surgeMultiplier);
        $total = (int) (ceil($raw / 100) * 100);

        // ── Minimum fare ─────────────────────────────────────────────────────
        $minimum = (int) $p['minimum'];
        $total   = max($total, $minimum);

        $surgeAmount = max(0, $total - $subtotalWithNight);

        return [
            'service_type'     => $serviceType,
            'service_label'    => $p['label'],
            'capacity'         => $p['capacity'],
            'distance_km'      => $distanceKm,
            'duration_min'     => $durationMin,
            'distance_text'    => $route['distance_text'],
            'duration_text'    => $route['duration_text'],
            'route_source'     => $route['source'],
            'breakdown'        => [
                'booking_fee'     => $bookingFee,
                'base_fare'       => $baseFare,
                'distance_fare'   => $distanceFare,
                'time_fare'       => $timeFare,
                'night_surcharge' => $nightSurcharge,
                'surge_amount'    => (int) $surgeAmount,
            ],
            'subtotal'         => $subtotal,
            'surge_multiplier' => $surgeMultiplier,
            'surge_active'     => $surgeMultiplier > 1.0,
            'surge_zone'       => $surgeZone
                ? ['id' => $surgeZone->id, 'name' => $surgeZone->name]
                : null,
            'night_rate'       => $isNight,
            'total'            => $total,
            'minimum_fare'     => $minimum,
            'currency'         => 'KHR',
        ];
    }

    /**
     * Return fare estimates for ALL service types at once.
     * Flutter uses this to show the service picker (like Grab's bottom sheet).
     */
    public function allRideFares(
        array  $route,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): array {
        $types = array_keys(config('pricing.rides', []));

        return collect($types)
            ->mapWithKeys(fn($type) => [
                $type => $this->calculateRideFare($type, $route, $pickupLat, $pickupLng),
            ])
            ->toArray();
    }

    // ── Delivery Fare ─────────────────────────────────────────────────────────

    /**
     * Calculate delivery fee.
     *
     * @param  string  $packageSize  small|medium|large
     * @param  array   $route        Output of getRoute()
     */
    public function calculateDeliveryFare(
        string $packageSize,
        array  $route,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): array {
        $distanceKm = max(1.0, (float) $route['distance_km']);

        $baseFare       = (int) config('delivery.fee_base', 3000);
        $perKm          = (int) config('delivery.fee_per_km', 1200);
        $pkgSurcharge   = (int) config("delivery.fee_surcharge_{$packageSize}", 0);
        $bookingFee     = (int) config('pricing.delivery.booking_fee', 500);
        $distanceFare   = (int) ceil($distanceKm * $perKm);
        $subtotal       = $bookingFee + $baseFare + $distanceFare + $pkgSurcharge;

        // Night surcharge.
        $hour           = (int) now()->format('H');
        $isNight        = $hour >= 22 || $hour < 5;
        $nightRate      = (float) $this->setting('delivery_night_surcharge_rate', 0.15);
        $nightSurcharge = $isNight ? (int) ceil($subtotal * $nightRate) : 0;
        $subtotalWithNight = $subtotal + $nightSurcharge;

        // Surge pricing.
        $surgeZone       = null;
        $surgeMultiplier = 1.0;

        if ($pickupLat !== null && $pickupLng !== null) {
            $surgeZone       = $this->surge->getActiveZone($pickupLat, $pickupLng, 'deliveries');
            $surgeMultiplier = $surgeZone ? (float) $surgeZone->multiplier : 1.0;
        }

        $raw   = (int) round($subtotalWithNight * $surgeMultiplier);
        $total = (int) (ceil($raw / 100) * 100);

        $surgeAmount = max(0, $total - $subtotalWithNight);

        return [
            'package_size'     => $packageSize,
            'distance_km'      => $distanceKm,
            'duration_min'     => (int) $route['duration_min'],
            'distance_text'    => $route['distance_text'],
            'duration_text'    => $route['duration_text'],
            'route_source'     => $route['source'],
            'breakdown'        => [
                'booking_fee'      => $bookingFee,
                'base_fare'        => $baseFare,
                'distance_fare'    => $distanceFare,
                'package_surcharge'=> $pkgSurcharge,
                'night_surcharge'  => $nightSurcharge,
                'surge_amount'     => (int) $surgeAmount,
            ],
            'subtotal'         => $subtotal,
            'surge_multiplier' => $surgeMultiplier,
            'surge_active'     => $surgeMultiplier > 1.0,
            'surge_zone'       => $surgeZone
                ? ['id' => $surgeZone->id, 'name' => $surgeZone->name]
                : null,
            'night_rate'       => $isNight,
            'total'            => $total,
            'currency'         => 'KHR',
        ];
    }

    // ── ETA ───────────────────────────────────────────────────────────────────

    /**
     * Estimate driver ETA to pickup from driver's current location.
     * Returns minutes.
     */
    public function etaToPickup(float $driverLat, float $driverLng, float $pickupLat, float $pickupLng): int
    {
        $route = $this->getRoute($driverLat, $driverLng, $pickupLat, $pickupLng);
        return $route['duration_min'];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ridePricing(string $serviceType): array
    {
        // Load from DB (cached for 10 minutes).
        $all = Cache::remember('ride_pricing_all', 600, function () {
            return RidePricing::where('active', true)
                ->get()
                ->keyBy('service_type')
                ->map(fn($p) => $p->toArray())
                ->toArray();
        });

        // Fall back to config if DB not seeded yet.
        if (empty($all)) {
            $all = config('pricing.rides', []);
        }

        return $all[$serviceType] ?? $all['standard'] ?? [];
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return Cache::remember("pricing_setting_{$key}", 600, function () use ($key, $default) {
            try {
                return PricingSetting::get($key, $default);
            } catch (\Throwable) {
                return $default;
            }
        });
    }

    /** Clear the pricing cache — call after admin updates pricing. */
    public static function clearCache(): void
    {
        Cache::forget('ride_pricing_all');
        foreach (['night_surcharge_rate','avg_city_speed_kmh','traffic_speed_threshold_kmh','delivery_night_surcharge_rate'] as $k) {
            Cache::forget("pricing_setting_{$k}");
        }
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
