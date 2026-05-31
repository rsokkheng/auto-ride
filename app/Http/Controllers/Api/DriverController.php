<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\RideLocation;
use App\Models\Vehicle;
use App\Services\FirestoreService;
use Illuminate\Http\Request;

class DriverController extends ApiController
{
    public function __construct(private FirestoreService $firestore) {}

    public function status(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        return $this->success([
            'driver'            => $user,
            'active_rides'      => Ride::where('driver_id', $user->id)->whereIn('status', ['accepted', 'on_route', 'in_progress'])->get(),
            'active_deliveries' => Delivery::where('driver_id', $user->id)->whereIn('status', ['accepted', 'in_transit'])->get(),
        ]);
    }

    public function setAvailability(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $data = $request->validate([
            'available'   => 'required|boolean',
            'status_note' => 'nullable|string|max:255',
        ]);

        $user->update([
            'available'   => $data['available'],
            'status_note' => $data['status_note'] ?? null,
        ]);

        $this->firestore->syncDriver($user->fresh());

        return $this->success(['available' => $user->available, 'status_note' => $user->status_note]);
    }

    public function goOnline(Request $request)
    {
        return $this->setAvailability($request->merge(['available' => true]));
    }

    public function goOffline(Request $request)
    {
        return $this->setAvailability($request->merge(['available' => false]));
    }

    public function declineRide(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $ride->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        $ride->update(['status' => 'requested', 'driver_id' => null]);

        $this->firestore->syncRide($ride->fresh());

        return $this->success(['ride' => $ride]);
    }

    /**
     * POST /v1/driver/location
     *
     * Updates driver lat/lng in MySQL and pushes to Firestore.
     * Also patches the embedded driver location in any active ride/delivery document.
     */
    public function updateLocation(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $data = $request->validate([
            'ride_id'     => 'nullable|exists:rides,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'speed'       => 'nullable|numeric|min:0',
            'heading'     => 'nullable|numeric|min:0|max:360',
            'status'      => 'nullable|string|max:64',
        ]);

        $lat     = (float) $data['latitude'];
        $lng     = (float) $data['longitude'];
        $speed   = isset($data['speed'])   ? (float) $data['speed']   : null;
        $heading = isset($data['heading']) ? (float) $data['heading'] : null;

        // 1. Persist to MySQL.
        $user->update(['current_latitude' => $lat, 'current_longitude' => $lng]);

        $location = null;
        if (! empty($data['ride_id'])) {
            $location = RideLocation::create([
                'ride_id'   => $data['ride_id'],
                'latitude'  => $lat,
                'longitude' => $lng,
                'speed'     => $speed,
                'heading'   => $heading,
                'status'    => $data['status'] ?? null,
            ]);
        }

        // 2. Push driver doc to Firestore (for map markers).
        $this->firestore->syncDriver($user, $lat, $lng, $speed, $heading);

        // 3. Patch driver location inside active ride document.
        if (! empty($data['ride_id'])) {
            $this->firestore->updateRideDriverLocation((int) $data['ride_id'], $lat, $lng, $heading);
        }

        // 4. Patch driver location inside active delivery document.
        if (! empty($data['delivery_id'])) {
            $this->firestore->updateDeliveryDriverLocation((int) $data['delivery_id'], $lat, $lng, $heading);
        }

        return $this->success([
            'location' => $location,
            'current'  => ['latitude' => $lat, 'longitude' => $lng],
        ]);
    }

    public function getDriverStats(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        return $this->success([
            'driver_id'       => $user->id,
            'accepted_rides'  => Ride::where('driver_id', $user->id)->where('status', 'accepted')->count(),
            'completed_rides' => Ride::where('driver_id', $user->id)->where('status', 'completed')->count(),
            'available'       => (bool) $user->available,
        ]);
    }

    public function registerVehicle(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $data = $request->validate([
            'license_plate' => 'required|string|max:32',
            'make'          => 'required|string|max:64',
            'model'         => 'required|string|max:64',
            'year'          => 'required|integer|min:1900|max:2100',
            'type'          => 'required|string|max:32',
            'capacity'      => 'nullable|integer|min:1',
            'details'       => 'nullable|string',
        ]);

        $vehicle = $user->vehicles()->create(array_merge($data, ['status' => 'active']));

        return $this->success(['vehicle' => $vehicle], 201);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $vehicle->user_id !== $user->id) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'license_plate' => 'sometimes|string|max:32',
            'make'          => 'sometimes|string|max:64',
            'model'         => 'sometimes|string|max:64',
            'year'          => 'sometimes|integer|min:1900|max:2100',
            'type'          => 'sometimes|string|max:32',
            'capacity'      => 'nullable|integer|min:1',
            'details'       => 'nullable|string',
            'status'        => 'nullable|string|max:32',
        ]);

        $vehicle->update($data);

        return $this->success(['vehicle' => $vehicle]);
    }

    public function tasks(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        return $this->success([
            'rides'      => Ride::where('driver_id', $user->id)->orderBy('updated_at')->get(),
            'deliveries' => Delivery::where('driver_id', $user->id)->orderBy('updated_at')->get(),
        ]);
    }
}
