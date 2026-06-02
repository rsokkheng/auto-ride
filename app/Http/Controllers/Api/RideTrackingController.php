<?php

namespace App\Http\Controllers\Api;

use App\Events\RideLocationUpdated;
use App\Models\Ride;
use App\Models\RideLocation;
use App\Services\FirestoreService;
use Illuminate\Http\Request;

class RideTrackingController extends ApiController
{
    public function __construct(private FirestoreService $firestore) {}

    public function show(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success([
            'track' => $ride->locations()->orderByDesc('created_at')->take(10)->get(),
        ]);
    }

    public function update(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);

        if (! $user || $user->role !== 'driver' || $ride->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|min:0|max:360',
            'status' => 'nullable|string|max:64',
        ]);

        $location = RideLocation::create(array_merge($data, [
            'ride_id' => $ride->id,
        ]));

        event(new RideLocationUpdated($ride, $location));

        // Patch driver location in the Firestore booking document so Flutter
        // real-time listeners receive the update without polling.
        $this->firestore->updateRideDriverLocation(
            $ride->id,
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['heading']) ? (float) $data['heading'] : null,
        );

        return $this->success(['location' => $location], 201);
    }
}
