<?php

namespace App\Services;

use App\Models\PricingSetting;

/**
 * Pricing engine for the Moving service.
 *
 * Total = base_fee + distance_fee + truck_fee + helper_fee + floor_fee
 *
 * All amounts are in KHR. Rates are managed via the admin Moving Fare page
 * and stored in the pricing_settings table (keys prefixed with moving_).
 */
class MovingFareService
{
    private function cfg(string $key, float $default): float
    {
        return (float) PricingSetting::get($key, $default);
    }

    private function floorFeeTiers(): array
    {
        return [
            1           => (int) $this->cfg('moving_floor_fee_tier_1',     4000),
            3           => (int) $this->cfg('moving_floor_fee_tier_3',    12000),
            6           => (int) $this->cfg('moving_floor_fee_tier_6',    20000),
            PHP_INT_MAX => (int) $this->cfg('moving_floor_fee_tier_7plus', 40000),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate the full moving fare.
     *
     * @param float  $distanceKm     Road distance in km.
     * @param int    $floorPickup    Number of floors at origin (0 = ground).
     * @param int    $floorDropoff   Number of floors at destination.
     * @param bool   $hasElevator    Whether an elevator is available.
     * @param int    $requiresHelpers Number of helpers requested (0–4).
     * @param string $helperType     'normal_carry' | 'heavy_carry'
     * @return array{
     *     base_fee: int,
     *     distance_fee: int,
     *     truck_fee: int,
     *     helper_fee: int,
     *     floor_fee: int,
     *     total: int,
     *     breakdown: array,
     *     currency: string,
     * }
     */
    public function calculate(
        float  $distanceKm,
        int    $floorPickup    = 0,
        int    $floorDropoff   = 0,
        bool   $hasElevator    = false,
        int    $requiresHelpers = 0,
        string $helperType     = 'normal_carry',
    ): array {
        $baseFee     = (int) $this->cfg('moving_base_fee', 20000);
        $distanceFee = (int) round($distanceKm * $this->cfg('moving_distance_rate', 4000));
        $truckFee    = (int) $this->cfg('moving_truck_fee', 20000);
        $helperFee   = $this->calcHelperFee($requiresHelpers, $helperType);
        $floorFee    = $this->calcFloorFee($floorPickup, $floorDropoff, $hasElevator);

        $total = $baseFee + $distanceFee + $truckFee + $helperFee + $floorFee;

        return [
            'base_fee'     => $baseFee,
            'distance_fee' => $distanceFee,
            'truck_fee'    => $truckFee,
            'helper_fee'   => $helperFee,
            'floor_fee'    => $floorFee,
            'total'        => $total,
            'breakdown'    => [
                'base'     => $this->fmt($baseFee),
                'distance' => $this->fmt($distanceFee) . " ({$distanceKm} km)",
                'truck'    => $this->fmt($truckFee),
                'helpers'  => $this->fmt($helperFee) . " ({$requiresHelpers} × " . ucfirst(str_replace('_', ' ', $helperType)) . ')',
                'floor'    => $this->fmt($floorFee) . ($hasElevator ? ' (elevator)' : ' (stairs ×1.5)'),
            ],
            'currency'     => 'KHR',
        ];
    }

    /**
     * Quick estimate that accepts only lat/lng and building params.
     * Uses Haversine for distance (no Google Maps call needed for estimates).
     */
    public function estimate(
        float $pickupLat, float $pickupLng,
        float $dropoffLat, float $dropoffLng,
        int    $floorPickup    = 0,
        int    $floorDropoff   = 0,
        bool   $hasElevator    = false,
        int    $requiresHelpers = 0,
        string $helperType     = 'normal_carry',
    ): array {
        $distanceKm = $this->haversine($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        $fare = $this->calculate(
            $distanceKm, $floorPickup, $floorDropoff,
            $hasElevator, $requiresHelpers, $helperType,
        );

        $fare['distance_km']  = round($distanceKm, 2);
        $fare['distance_src'] = 'haversine';

        return $fare;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calcHelperFee(int $helpers, string $type): int
    {
        if ($helpers <= 0) return 0;

        $ratePerPerson = $type === 'heavy_carry'
            ? (int) $this->cfg('moving_helper_rate_heavy', 16000)
            : (int) $this->cfg('moving_helper_rate_normal', 8000);

        return $helpers * $ratePerPerson;
    }

    private function calcFloorFee(int $floorPickup, int $floorDropoff, bool $hasElevator): int
    {
        $maxFloor = max($floorPickup, $floorDropoff);
        if ($maxFloor <= 0) return 0;

        $fee = 0;
        foreach ($this->floorFeeTiers() as $threshold => $amount) {
            if ($maxFloor <= $threshold) {
                $fee = $amount;
                break;
            }
        }

        if (! $hasElevator) {
            $fee = (int) round($fee * $this->cfg('moving_no_elevator_mult', 1.5));
        }

        return $fee;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function fmt(int $khr): string
    {
        return number_format($khr) . ' ៛';
    }
}
