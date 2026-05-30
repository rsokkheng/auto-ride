<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends ApiController
{
    public function index(Request $request)
    {
        return $this->success([
            'vehicles' => Vehicle::with('driver')->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user || $user->role !== 'driver') {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'license_plate' => 'required|string|max:32',
            'make' => 'required|string|max:64',
            'model' => 'required|string|max:64',
            'year' => 'required|integer|min:1900|max:2100',
            'type' => 'required|string|max:32',
            'capacity' => 'nullable|integer|min:1',
            'details' => 'nullable|string',
        ]);

        $vehicle = $user->vehicles()->create(array_merge($data, ['status' => 'active']));

        return $this->success(['vehicle' => $vehicle], 201);
    }

    public function show(Vehicle $vehicle)
    {
        return $this->success(['vehicle' => $vehicle->load('driver')]);
    }
}
