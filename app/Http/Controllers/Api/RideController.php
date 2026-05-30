<?php

namespace App\Http\Controllers\Api;

use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class RideController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        if ($user->role === 'driver') {
            $rides = Ride::with(['passenger', 'vehicle'])->where('driver_id', $user->id)->paginate(20);
        } else {
            $rides = Ride::with(['driver', 'vehicle'])->where('passenger_id', $user->id)->paginate(20);
        }

        return $this->success(['rides' => $rides]);
    }

    public function history(Request $request)
    {
        return $this->index($request);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'pickup_address' => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'scheduled_at' => 'nullable|date',
            'service_type' => 'required|string|in:standard,premium,shared',
            'notes' => 'nullable|string',
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]);

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $data['driver_id'] = $vehicle?->user_id;
        }

        $fare = $this->calculateFare($data['pickup_address'], $data['dropoff_address'], $data['service_type']);

        $ride = Ride::create(array_merge($data, [
            'passenger_id' => $user->id,
            'status' => 'requested',
            'fare' => $fare,
        ]));

        return $this->success(['ride' => $ride->load('driver', 'vehicle')], 201);
    }

    public function available(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user || $user->role !== 'driver') {
            return $this->unauthorized();
        }

        $rides = Ride::with(['passenger', 'vehicle'])
            ->where('status', 'requested')
            ->whereNull('driver_id')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->success(['rides' => $rides]);
    }

    public function accept(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);

        if (! $user || $user->role !== 'driver') {
            return $this->unauthorized();
        }

        if ($ride->driver_id && $ride->driver_id !== $user->id) {
            return response()->json(['message' => 'Ride already claimed'], 422);
        }

        $ride->update([
            'driver_id' => $user->id,
            'status' => 'accepted',
        ]);

        return $this->success(['ride' => $ride->fresh()->load('driver', 'vehicle')]);
    }

    public function complete(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);

        if (! $user || $user->role !== 'driver' || $ride->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        if ($ride->status !== 'accepted') {
            return response()->json(['message' => 'Ride is not in accepted state'], 422);
        }

        $ride->update(['status' => 'completed']);

        return $this->success(['ride' => $ride->fresh()->load('driver', 'vehicle')]);
    }

    public function estimate(Request $request)
    {
        $data = $request->validate([
            'distance' => 'nullable|numeric|min:0',
            'service_type' => 'nullable|in:standard,premium,shared',
        ]);

        $fare = $this->calculateFare(null, null, $data['service_type'] ?? 'standard', $data['distance'] ?? 5);

        return $this->success(['estimate' => $fare]);
    }

    public function nearbyDrivers(Request $request)
    {
        $this->authUserOrFail($request);

        $drivers = User::where('role', 'driver')
            ->where('available', true)
            ->take(20)
            ->get(['id', 'name', 'phone', 'status_note']);

        return $this->success(['drivers' => $drivers]);
    }

    public function cancel(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        if (in_array($ride->status, ['completed', 'cancelled', 'canceled'], true)) {
            return response()->json(['message' => 'Ride cannot be cancelled'], 422);
        }

        $ride->update(['status' => 'cancelled']);

        return $this->success(['ride' => $ride]);
    }

    public function rate(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $ride->update([
            'rating' => $data['rating'],
            'rating_comment' => $data['comment'] ?? null,
        ]);

        return $this->success(['ride' => $ride]);
    }

    /**
     * Calculate ride fare in Khmer Riel (KHR ៛).
     *
     * Rates (Cambodia market):
     *   Standard : 4,000 base + 1,500/km
     *   Premium  : 8,000 base + 3,000/km
     *   Shared   : 2,500 base + 1,000/km
     *
     * Result is rounded up to the nearest 100 ៛.
     */
    protected function calculateFare(?string $pickup, ?string $dropoff, string $serviceType = 'standard', float $distance = 5): int
    {
        $base  = config('delivery.ride_base_standard',  4000);
        $perKm = config('delivery.ride_perkm_standard', 1500);

        if ($serviceType === 'premium') {
            $base  = config('delivery.ride_base_premium',  8000);
            $perKm = config('delivery.ride_perkm_premium', 3000);
        } elseif ($serviceType === 'shared') {
            $base  = config('delivery.ride_base_shared',  2500);
            $perKm = config('delivery.ride_perkm_shared', 1000);
        }

        $raw = $base + ($perKm * max(1, $distance));

        // Round up to nearest 100 ៛ — standard practice in Cambodia.
        return (int) (ceil($raw / 100) * 100);
    }

    protected function authUserOrFail(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        return $user;
    }
}
