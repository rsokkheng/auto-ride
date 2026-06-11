<?php

namespace App\Http\Controllers\Api;

use App\Models\ChargingStation;
use Illuminate\Http\Request;

class ChargingStationController extends ApiController
{
    public function index(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        $query = ChargingStation::query();

        if (is_numeric($lat) && is_numeric($lng)) {
            // Haversine distance in km, sort nearest first
            $query->selectRaw("*, ( 6371 * acos( cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)) ) ) AS distance_km", [$lat, $lng, $lat])
                  ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        $stations = $query->get();

        return $this->success(['charging_stations' => $stations]);
    }
}
