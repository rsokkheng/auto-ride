<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\Request;

class VehicleController extends ApiController
{
    public function types()
    {
        $types = VehicleType::orderBy('sort_order')->get();

        return $this->success(['vehicle_types' => $types]);
    }

    public function storeType(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'admin') {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'name'         => 'required|string|max:64',
            'slug'         => 'required|string|max:64|unique:vehicle_types,slug|alpha_dash',
            'icon'         => 'nullable|string|max:128',
            'description'  => 'nullable|string|max:255',
            'capacity'     => 'required|integer|min:1|max:255',
            'base_fare'    => 'required|numeric|min:0',
            'per_km_fare'  => 'required|numeric|min:0',
            'is_active'    => 'boolean',
            'sort_order'   => 'integer|min:0',
        ]);

        $type = VehicleType::create($data);

        return $this->success(['vehicle_type' => $type], 201);
    }

    public function updateType(Request $request, VehicleType $vehicleType)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'admin') {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'name'         => 'sometimes|string|max:64',
            'slug'         => 'sometimes|string|max:64|alpha_dash|unique:vehicle_types,slug,' . $vehicleType->id,
            'icon'         => 'sometimes|nullable|string|max:128',
            'description'  => 'sometimes|nullable|string|max:255',
            'capacity'     => 'sometimes|integer|min:1|max:255',
            'base_fare'    => 'sometimes|numeric|min:0',
            'per_km_fare'  => 'sometimes|numeric|min:0',
            'is_active'    => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer|min:0',
        ]);

        $vehicleType->update($data);

        return $this->success(['vehicle_type' => $vehicleType->fresh()]);
    }

    public function destroyType(Request $request, VehicleType $vehicleType)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'admin') {
            return $this->unauthorized();
        }

        $vehicleType->delete();

        return $this->success(['message' => 'Vehicle type deleted.']);
    }

    public function index()
    {
        return $this->success([
            'vehicles' => Vehicle::with('driver')->paginate(10),
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
