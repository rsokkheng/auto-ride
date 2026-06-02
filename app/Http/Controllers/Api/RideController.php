<?php

namespace App\Http\Controllers\Api;

use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FareService;
use App\Services\FirestoreService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class RideController extends ApiController
{
    public function __construct(
        private FareService $fare,
        private FirestoreService $firestore,
    ) {}

    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $rides = $user->role === 'driver'
            ? Ride::with(['passenger', 'vehicle'])->where('driver_id', $user->id)->paginate(20)
            : Ride::with(['driver', 'vehicle'])->where('passenger_id', $user->id)->paginate(20);

        return $this->success(['rides' => $rides]);
    }

    public function history(Request $request)
    {
        return $this->index($request);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'pickup_address'  => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'pickup_lat'      => 'required|numeric|between:-90,90',
            'pickup_lng'      => 'required|numeric|between:-180,180',
            'dropoff_lat'     => 'required|numeric|between:-90,90',
            'dropoff_lng'     => 'required|numeric|between:-180,180',
            'service_type'    => 'required|in:motorcycle,tuk_tuk,standard,premium,shared,van',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
        ]);

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $data['driver_id'] = $vehicle?->user_id;
        }

        // Calculate fare using real road distance.
        $route      = $this->fare->getRoute(
            (float) $data['pickup_lat'],  (float) $data['pickup_lng'],
            (float) $data['dropoff_lat'], (float) $data['dropoff_lng'],
        );
        $fareResult = $this->fare->calculateRideFare(
            $data['service_type'],
            $route,
            (float) $data['pickup_lat'],
            (float) $data['pickup_lng'],
        );

        $ride = Ride::create(array_merge($data, [
            'passenger_id'    => $user->id,
            'status'          => 'requested',
            'fare'            => $fareResult['total'],
            'surge_multiplier'=> $fareResult['surge_multiplier'],
            'surge_zone_id'   => $fareResult['surge_zone']['id'] ?? null,
        ]));

        $ride->load('driver', 'vehicle');
        $this->firestore->syncRide($ride);

        return $this->success([
            'ride' => $ride,
            'fare' => $fareResult,
        ], 201);
    }

    public function show(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if (! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success(['ride' => $ride->load('passenger', 'driver', 'vehicle')]);
    }

    public function available(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

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
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        // Only accept rides that are still open.
        if (! in_array($ride->status, ['requested', 'pending'], true)) {
            return response()->json([
                'message' => "Ride cannot be accepted — current status is \"{$ride->status}\".",
            ], 422);
        }

        // Block if another driver already claimed it.
        if ($ride->driver_id && $ride->driver_id !== $user->id) {
            return response()->json(['message' => 'Ride already claimed by another driver.'], 422);
        }

        $ride->update([
            'driver_id' => $user->id,
            'status'    => 'accepted',
        ]);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');

        // Sync booking + driver location to Firestore — Flutter passenger listens here.
        $this->firestore->syncRide($fresh);

        return $this->success([
            'ride'    => $fresh,
            'message' => 'Ride accepted. Head to pickup location.',
        ]);
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

        $transaction = null;
        if ($ride->fare > 0) {
            $transaction = app(PaymentService::class)->processRide($ride->fresh());
        }

        $fresh = $ride->fresh()->load('driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        return $this->success([
            'ride'        => $fresh,
            'transaction' => $transaction,
        ]);
    }

    /**
     * POST /v1/rides/estimate
     *
     * Returns fare estimates for ALL service types (like Grab's bottom sheet).
     *
     * Body:
     *   pickup_lat, pickup_lng   required  Pickup coordinates
     *   dropoff_lat, dropoff_lng required  Dropoff coordinates
     *   service_type             optional  Return single type only
     */
    public function estimate(Request $request)
    {
        $data = $request->validate([
            'pickup_lat'   => 'required|numeric|between:-90,90',
            'pickup_lng'   => 'required|numeric|between:-180,180',
            'dropoff_lat'  => 'required|numeric|between:-90,90',
            'dropoff_lng'  => 'required|numeric|between:-180,180',
            'service_type' => 'nullable|in:motorcycle,tuk_tuk,standard,premium,shared,van',
        ]);

        $route = $this->fare->getRoute(
            (float) $data['pickup_lat'],  (float) $data['pickup_lng'],
            (float) $data['dropoff_lat'], (float) $data['dropoff_lng'],
        );

        // Single type or all types.
        if (! empty($data['service_type'])) {
            $fares = $this->fare->calculateRideFare(
                $data['service_type'], $route,
                (float) $data['pickup_lat'], (float) $data['pickup_lng'],
            );
        } else {
            $fares = $this->fare->allRideFares(
                $route,
                (float) $data['pickup_lat'],
                (float) $data['pickup_lng'],
            );
        }

        return $this->success([
            'route'  => [
                'distance_km'   => $route['distance_km'],
                'duration_min'  => $route['duration_min'],
                'distance_text' => $route['distance_text'],
                'duration_text' => $route['duration_text'],
                'source'        => $route['source'],
            ],
            'fares'  => $fares,
            'currency' => 'KHR',
        ]);
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

        if (in_array($ride->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Ride cannot be cancelled'], 422);
        }

        $ride->update(['status' => 'cancelled']);
        $this->firestore->syncRide($ride->fresh()->load('driver', 'vehicle'));

        return $this->success(['ride' => $ride]);
    }

    public function rate(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'rating'  => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $ride->update([
            'rating'         => $data['rating'],
            'rating_comment' => $data['comment'] ?? null,
        ]);

        return $this->success(['ride' => $ride]);
    }

    // ── Fare Calculation ──────────────────────────────────────────────────────

    protected function authUserOrFail(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) abort(401, 'Unauthorized');
        return $user;
    }
}
