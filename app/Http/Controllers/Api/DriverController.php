<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\RideLocation;
use App\Models\Vehicle;
use App\Services\DriverMatchingService;
use App\Services\FirestoreService;
use App\Services\SurgeZoneService;
use Illuminate\Http\Request;

class DriverController extends ApiController
{
    public function __construct(
        private FirestoreService $firestore,
        private DriverMatchingService $matcher,
        private SurgeZoneService $surge,
    ) {}

    /**
     * GET /v1/drivers/nearby
     *
     * Returns available drivers near a location for map display.
     *
     * Query params:
     *   lat      float  required  Passenger/user latitude
     *   lng      float  required  Passenger/user longitude
     *   type     string optional  rides | deliveries | both  (default: both)
     *   radius   float  optional  Search radius in km (default: config)
     *   limit    int    optional  Max results (default: 20, max: 50)
     */
    public function nearby(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'lat'    => 'required|numeric|between:-90,90',
            'lng'    => 'required|numeric|between:-180,180',
            'type'   => 'nullable|in:rides,deliveries,both',
            'radius' => 'nullable|numeric|min:0.5|max:100',
            'limit'  => 'nullable|integer|min:1|max:50',
        ]);

        $lat    = (float) $data['lat'];
        $lng    = (float) $data['lng'];
        $type   = $data['type'] ?? 'both';
        $radius = isset($data['radius']) ? (float) $data['radius'] : null;
        $limit  = (int) ($data['limit'] ?? 20);

        // Check if pickup location is in a surge zone.
        $surgeZone       = $this->surge->getActiveZone($lat, $lng, $type === 'both' ? 'both' : $type);
        $surgeMultiplier = $surgeZone ? $surgeZone->multiplier : 1.0;

        $drivers = $this->matcher->findDrivers($lat, $lng, $limit, $radius);

        $result = $drivers->map(fn($driver) => [
            'id'              => $driver->id,
            'name'            => $driver->name,
            'phone'           => $driver->phone,
            'avatar_url'      => $driver->avatar_url,
            'rating'          => round((float) $driver->rating, 1),
            'total_ratings'   => (int) $driver->total_ratings,
            'distance_km'     => $driver->distance_km,
            'eta_minutes'     => $driver->eta_minutes,
            'distance_source' => $driver->distance_source,
            'lat'             => $driver->current_latitude  ? (float) $driver->current_latitude  : null,
            'lng'             => $driver->current_longitude ? (float) $driver->current_longitude : null,
            'vehicle'         => $driver->vehicles->first() ? [
                'id'            => $driver->vehicles->first()->id,
                'make'          => $driver->vehicles->first()->make,
                'model'         => $driver->vehicles->first()->model,
                'year'          => $driver->vehicles->first()->year,
                'type'          => $driver->vehicles->first()->type,
                'license_plate' => $driver->vehicles->first()->license_plate,
                'primary_image' => $driver->vehicles->first()->primary_image_url,
            ] : null,
        ]);

        return $this->success([
            'drivers'          => $result,
            'total'            => $result->count(),
            'search_radius_km' => $radius ?? config('delivery.match_radius_km', 30),
            'surge'            => [
                'active'     => $surgeMultiplier > 1.0,
                'multiplier' => $surgeMultiplier,
                'zone'       => $surgeZone ? ['id' => $surgeZone->id, 'name' => $surgeZone->name] : null,
            ],
        ]);
    }

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

        // 2. Push to drivers_live (raw GPS tick for smooth map animation).
        $this->firestore->syncDriverLive($user, $lat, $lng, $speed, $heading);

        // 3. Patch driver location inside active ride booking document.
        if (! empty($data['ride_id'])) {
            $this->firestore->updateRideDriverLocation((int) $data['ride_id'], $lat, $lng, $heading);
        }

        // 4. Patch driver location inside active delivery booking document.
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

    /**
     * GET /v1/drivers/{driver}
     * Public driver profile with stats for the passenger-facing UI.
     */
    public function profile(Request $request, \App\Models\User $driver)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if ($driver->role !== 'driver') {
            return response()->json(['data' => null, 'message' => 'User is not a driver.'], 404);
        }

        $driver->load(['vehicles' => fn($q) => $q->where('status', 'active')->latest()->limit(1)]);
        $vehicle = $driver->vehicles->first();

        $totalTrips = Ride::where('driver_id', $driver->id)
            ->where('status', Ride::STATUS_COMPLETED)
            ->count();

        return $this->success([
            'driver' => [
                'id'          => $driver->id,
                'name'        => $driver->name,
                'phone'       => $driver->phone,
                'photo_url'   => $driver->avatar_url,
                'rating'      => round((float) $driver->rating, 1),
                'total_trips' => $totalTrips,
                'available'   => (bool) $driver->available,
                'lat'         => $driver->current_latitude  ? (float) $driver->current_latitude  : null,
                'lng'         => $driver->current_longitude ? (float) $driver->current_longitude : null,
                'vehicle'     => $vehicle ? [
                    'id'            => $vehicle->id,
                    'make'          => $vehicle->make,
                    'model'         => $vehicle->model,
                    'year'          => $vehicle->year,
                    'type'          => $vehicle->type,
                    'license_plate' => $vehicle->license_plate,
                    'primary_image' => $vehicle->primary_image_url,
                ] : null,
            ],
        ]);
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
