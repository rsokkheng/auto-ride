<?php

namespace App\Http\Controllers\Api;

use App\Models\ChargingStation;
use App\Models\ChargingStationFavorite;
use Illuminate\Http\Request;

class ChargingStationController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        $lat  = $request->query('lat');
        $lng  = $request->query('lng');

        $query = ChargingStation::query();

        if (is_numeric($lat) && is_numeric($lng)) {
            $query->selectRaw("*, ( 6371 * acos( cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)) ) ) AS distance_km", [$lat, $lng, $lat])
                  ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        $stations = $query->get();

        // Annotate is_favorite when user is authenticated
        if ($user) {
            $favIds = ChargingStationFavorite::where('user_id', $user->id)
                ->pluck('charging_station_id')
                ->flip();
            $stations = $stations->map(function ($s) use ($favIds) {
                $s->is_favorite = $favIds->has($s->id);
                return $s;
            });
        }

        return $this->success(['charging_stations' => $stations]);
    }

    /**
     * GET /v1/charging-stations/favorites
     */
    public function favorites(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $stations = ChargingStation::whereHas('favorites', fn($q) => $q->where('user_id', $user->id))
            ->orderBy('name')
            ->get()
            ->map(function ($s) {
                $s->is_favorite = true;
                return $s;
            });

        return $this->success(['charging_stations' => $stations]);
    }

    /**
     * POST /v1/charging-stations/{station}/favorite
     */
    public function addFavorite(Request $request, ChargingStation $station)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        ChargingStationFavorite::firstOrCreate([
            'user_id'            => $user->id,
            'charging_station_id'=> $station->id,
        ]);

        return $this->success(['favorited' => true, 'station_id' => $station->id]);
    }

    /**
     * DELETE /v1/charging-stations/{station}/favorite
     */
    public function removeFavorite(Request $request, ChargingStation $station)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        ChargingStationFavorite::where('user_id', $user->id)
            ->where('charging_station_id', $station->id)
            ->delete();

        return $this->success(['favorited' => false, 'station_id' => $station->id]);
    }
}
