<?php

namespace App\Http\Controllers\Api;

use App\Models\ChargingStation;
use Illuminate\Http\Request;

class ChargingStationController extends ApiController
{
    public function index(Request $request)
    {
        return $this->success([
            'charging_stations' => ChargingStation::orderBy('name')->get(),
        ]);
    }
}
