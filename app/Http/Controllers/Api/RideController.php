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

    // ── List / History ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $rides = $user->role === 'driver'
            ? Ride::with(['passenger', 'vehicle'])->where('driver_id', $user->id)->orderBy('created_at')->paginate(20)
            : Ride::with(['driver', 'vehicle'])->where('passenger_id', $user->id)->orderBy('created_at')->paginate(20);

        return $this->success(['rides' => $rides]);
    }

    // ── Single ride ───────────────────────────────────────────────────────────

    /**
     * GET /v1/rides/{ride}
     * Returns full ride with all coordinates so the app can restore the map
     * if killed and relaunched mid-trip.
     */
    public function show(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if (! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success(['ride' => $ride->load('passenger', 'driver', 'vehicle')]);
    }

    // ── Active ride ───────────────────────────────────────────────────────────

    /**
     * GET /v1/rides/active
     * Returns the single in-progress ride for the authenticated user.
     * Call this on app relaunch to resume tracking — never poll, use Firestore.
     */
    public function active(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $activeStatuses = [
            Ride::STATUS_ACCEPTED,
            Ride::STATUS_DRIVER_ARRIVED,
            Ride::STATUS_IN_PROGRESS,
        ];

        $ride = $user->role === 'driver'
            ? Ride::with(['passenger', 'vehicle'])
                ->where('driver_id', $user->id)
                ->whereIn('status', $activeStatuses)
                ->latest('updated_at')
                ->first()
            : Ride::with(['driver', 'vehicle'])
                ->where('passenger_id', $user->id)
                ->whereIn('status', $activeStatuses)
                ->latest('updated_at')
                ->first();

        if (! $ride) {
            return response()->json(['data' => null, 'message' => 'No active ride.'], 404);
        }

        return $this->success(['ride' => $ride]);
    }

    // ── Available (driver) ────────────────────────────────────────────────────

    public function available(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $rides = Ride::with(['passenger', 'vehicle'])
            ->where('status', Ride::STATUS_REQUESTED)
            ->whereNull('driver_id')
            ->orderBy('created_at')
            ->paginate(20);

        return $this->success(['rides' => $rides]);
    }

    // ── Estimate ──────────────────────────────────────────────────────────────

    /**
     * POST /v1/rides/estimate
     * Returns fares for all service types. Include surge_active in response
     * so Flutter can show surge confirmation before booking.
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

        $fares = ! empty($data['service_type'])
            ? $this->fare->calculateRideFare(
                $data['service_type'], $route,
                (float) $data['pickup_lat'], (float) $data['pickup_lng'],
            )
            : $this->fare->allRideFares(
                $route,
                (float) $data['pickup_lat'],
                (float) $data['pickup_lng'],
            );

        return $this->success([
            'route' => [
                'distance_km'   => $route['distance_km'],
                'duration_min'  => $route['duration_min'],
                'distance_text' => $route['distance_text'],
                'duration_text' => $route['duration_text'],
                'source'        => $route['source'],
            ],
            'fares'    => $fares,
            'currency' => 'KHR',
        ]);
    }

    // ── Create booking ────────────────────────────────────────────────────────

    /**
     * POST /v1/rides
     *
     * Requires surge_accepted: true when surge is active (multiplier > 1.0).
     * Returns 422 with surge details if passenger hasn't confirmed the surge price.
     */
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
            'payment_method'  => 'nullable|in:cash,wallet,aba,acleda,wing',
            'surge_accepted'  => 'nullable|boolean',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string|max:500',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
        ]);

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $data['driver_id'] = $vehicle?->user_id;
        }

        $route      = $this->fare->getRoute(
            (float) $data['pickup_lat'],  (float) $data['pickup_lng'],
            (float) $data['dropoff_lat'], (float) $data['dropoff_lng'],
        );
        $fareResult = $this->fare->calculateRideFare(
            $data['service_type'], $route,
            (float) $data['pickup_lat'], (float) $data['pickup_lng'],
        );

        // Require explicit surge confirmation when multiplier > 1.0.
        if ($fareResult['surge_multiplier'] > 1.0 && empty($data['surge_accepted'])) {
            return response()->json([
                'data' => null,
                'message'          => 'Surge pricing is active. Set surge_accepted: true to confirm.',
                'surge_active'     => true,
                'surge_multiplier' => $fareResult['surge_multiplier'],
                'surge_zone'       => $fareResult['surge_zone'],
                'total_fare'       => $fareResult['total'],
                'currency'         => 'KHR',
            ], 422);
        }

        $ride = Ride::create([
            'passenger_id'    => $user->id,
            'driver_id'       => $data['driver_id'] ?? null,
            'vehicle_id'      => $data['vehicle_id'] ?? null,
            'pickup_address'  => $data['pickup_address'],
            'dropoff_address' => $data['dropoff_address'],
            'pickup_lat'      => (float) $data['pickup_lat'],
            'pickup_lng'      => (float) $data['pickup_lng'],
            'dropoff_lat'     => (float) $data['dropoff_lat'],
            'dropoff_lng'     => (float) $data['dropoff_lng'],
            'service_type'    => $data['service_type'],
            'payment_method'  => $data['payment_method'] ?? 'cash',
            'payment_status'  => 'unpaid',
            'scheduled_at'    => $data['scheduled_at'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'status'          => Ride::STATUS_REQUESTED,
            'fare'            => $fareResult['total'],
            'surge_multiplier'=> $fareResult['surge_multiplier'],
            'surge_zone_id'   => $fareResult['surge_zone']['id'] ?? null,
            'surge_accepted'  => ! empty($data['surge_accepted']),
        ]);

        $ride->load('driver', 'vehicle');
        $this->firestore->syncRide($ride);

        return $this->success([
            'ride' => $ride,
            'fare' => $fareResult,
        ], 201);
    }

    // ── Status transitions ────────────────────────────────────────────────────

    /**
     * POST /v1/rides/{ride}/accept
     * Driver claims the ride and heads to pickup.
     */
    public function accept(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        if (! in_array($ride->status, Ride::OPEN_STATUSES, true)) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot accept — ride is \"{$ride->status}\".",
            ], 422);
        }

        if ($ride->driver_id && $ride->driver_id !== $user->id) {
            return response()->json(['data' => null, 'message' => 'Ride already claimed by another driver.'], 422);
        }

        $ride->update([
            'driver_id'   => $user->id,
            'status'      => Ride::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        return $this->success([
            'ride'    => $fresh,
            'message' => 'Ride accepted. Head to pickup location.',
        ]);
    }

    /**
     * POST /v1/rides/{ride}/arrive
     * Driver signals they have arrived at the pickup location.
     */
    public function arrive(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $ride->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        if ($ride->status !== Ride::STATUS_ACCEPTED) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot mark arrived — ride is \"{$ride->status}\".",
            ], 422);
        }

        $ride->update([
            'status'            => Ride::STATUS_DRIVER_ARRIVED,
            'driver_arrived_at' => now(),
        ]);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        return $this->success([
            'ride'    => $fresh,
            'message' => 'Arrival confirmed. Waiting for passenger.',
        ]);
    }

    /**
     * POST /v1/rides/{ride}/start
     * Driver starts the trip after passenger boards.
     */
    public function start(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $ride->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        if ($ride->status !== Ride::STATUS_DRIVER_ARRIVED) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot start — ride is \"{$ride->status}\". Must arrive first.",
            ], 422);
        }

        $ride->update([
            'status'     => Ride::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        return $this->success([
            'ride'    => $fresh,
            'message' => 'Trip started.',
        ]);
    }

    /**
     * POST /v1/rides/{ride}/complete
     * Driver completes the trip. Triggers payment processing.
     */
    public function complete(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $ride->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        if ($ride->status !== Ride::STATUS_IN_PROGRESS) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot complete — ride is \"{$ride->status}\". Trip must be in progress.",
            ], 422);
        }

        $ride->update([
            'status'       => Ride::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $transaction = null;
        if ($ride->fare > 0) {
            $transaction = app(PaymentService::class)->processRide($ride->fresh());
        }

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        return $this->success([
            'ride'        => $fresh,
            'transaction' => $transaction,
        ]);
    }

    /**
     * POST /v1/rides/{ride}/cancel
     * Passenger or driver cancels the ride.
     */
    public function cancel(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        if (! in_array($ride->status, Ride::CANCELLABLE_STATUSES, true)) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot cancel — ride is \"{$ride->status}\".",
            ], 422);
        }

        $ride->update([
            'status'       => Ride::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->firestore->syncRide($ride->fresh()->load('driver', 'vehicle'));

        return $this->success(['ride' => $ride->fresh()]);
    }

    // ── Rate ──────────────────────────────────────────────────────────────────

    public function rate(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        if ($ride->status !== Ride::STATUS_COMPLETED) {
            return response()->json(['data' => null, 'message' => 'Ride must be completed before rating.'], 422);
        }

        $data = $request->validate([
            'rating'  => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $ride->update([
            'rating'         => $data['rating'],
            'rating_comment' => $data['comment'] ?? null,
        ]);

        return $this->success(['ride' => $ride->fresh()]);
    }

    // ── Dispute ───────────────────────────────────────────────────────────────

    /**
     * POST /v1/rides/{ride}/dispute
     * Passenger or driver files a dispute after a completed ride.
     */
    public function dispute(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        if ($ride->status !== Ride::STATUS_COMPLETED) {
            return response()->json(['data' => null, 'message' => 'Disputes can only be filed after a completed ride.'], 422);
        }

        $data = $request->validate([
            'reason'      => 'required|in:wrong_route,overcharged,driver_behaviour,safety_concern,other',
            'description' => 'required|string|max:1000',
        ]);

        // Store as a support ticket so admin can manage it.
        \App\Models\SupportTicket::create([
            'user_id'  => $user->id,
            'subject'  => "Ride #{$ride->id} dispute: {$data['reason']}",
            'message'  => $data['description'] . "\n\nRide ID: {$ride->id}",
            'status'   => 'open',
            'priority' => 'high',
        ]);

        return $this->success([
            'message' => 'Dispute filed. Our support team will review it within 24 hours.',
        ]);
    }

    // ── Nearby drivers (legacy, prefer GET /v1/drivers/nearby) ───────────────

    public function nearbyDrivers(Request $request)
    {
        $this->authUserOrFail($request);

        $drivers = User::where('role', 'driver')
            ->where('available', true)
            ->take(20)
            ->get(['id', 'name', 'phone', 'status_note', 'rating', 'avatar']);

        return $this->success(['drivers' => $drivers]);
    }

    private function authUserOrFail(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) abort(401, 'Unauthorized');
        return $user;
    }
}
