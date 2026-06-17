<?php

namespace App\Http\Controllers\Api;

use App\Models\AirportZone;
use App\Models\PricingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportTripController extends ApiController
{
    public function zones(): JsonResponse
    {
        $zones = AirportZone::active()
            ->select('id', 'name', 'iata_code', 'latitude', 'longitude', 'radius_meters', 'surcharge_khr', 'luggage_fee_khr')
            ->get();

        return response()->json(['data' => $zones]);
    }

    public function estimateFare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pickup_lat'    => 'required|numeric',
            'pickup_lng'    => 'required|numeric',
            'dropoff_lat'   => 'required|numeric',
            'dropoff_lng'   => 'required|numeric',
            'luggage_count' => 'integer|min:0|max:10',
            'service_type'  => 'nullable|string',
        ]);

        $luggage = $data['luggage_count'] ?? 0;

        // Detect airport zone from pickup or dropoff
        $zoneModel = new AirportZone();
        $pickupZone  = $zoneModel->detectFromCoords($data['pickup_lat'], $data['pickup_lng']);
        $dropoffZone = $zoneModel->detectFromCoords($data['dropoff_lat'], $data['dropoff_lng']);
        $zone = $pickupZone ?? $dropoffZone;

        if (! $zone) {
            return response()->json(['message' => 'No airport zone detected at the given coordinates.'], 422);
        }

        // Base fare calculation (simplified distance estimate)
        $distanceKm = $this->haversineKm(
            $data['pickup_lat'], $data['pickup_lng'],
            $data['dropoff_lat'], $data['dropoff_lng']
        );

        $baseFareKhr   = (int) PricingSetting::get('base_fare_khr', 4000);
        $perKmKhr      = (int) PricingSetting::get('per_km_khr', 1600);
        $estimatedFare = (int) ($baseFareKhr + $distanceKm * $perKmKhr);
        $surcharge     = $zone->surcharge_khr;
        $luggageFee    = $luggage * $zone->luggage_fee_khr;
        $total         = $estimatedFare + $surcharge + $luggageFee;

        return response()->json([
            'data' => [
                'airport_zone'    => ['id' => $zone->id, 'name' => $zone->name, 'iata_code' => $zone->iata_code],
                'distance_km'     => round($distanceKm, 2),
                'base_fare_khr'   => $estimatedFare,
                'surcharge_khr'   => $surcharge,
                'luggage_fee_khr' => $luggageFee,
                'luggage_count'   => $luggage,
                'total_khr'       => $total,
            ],
        ]);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlambda = deg2rad($lng2 - $lng1);
        $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlambda / 2) ** 2;

        return 2 * $R * asin(sqrt($a));
    }
}
