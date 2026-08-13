<?php

namespace App\Http\Controllers\Api;

use App\Models\Ride;
use App\Models\RideStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Services\FareService;
use App\Services\FcmService;
use App\Services\FirestoreService;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Mail\TripReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RideController extends ApiController
{
    public function __construct(
        private FareService $fare,
        private FirestoreService $firestore,
        private FcmService $fcm,
        private WalletService $wallet,
    ) {}

    // ── List / History ────────────────────────────────────────────────────────

    /**
     * GET /v1/driver/rides
     * Full ride history for the authenticated driver with earnings summary.
     *
     * Query params:
     *   status    — completed|cancelled|accepted|... (default: all)
     *   from      — date Y-m-d
     *   to        — date Y-m-d
     *   per_page  — 1–100 (default 15)
     */
    public function driverHistory(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $query = Ride::with(['passenger:id,name,phone,avatar', 'vehicle:id,license_plate,make,model,type'])
            ->where('driver_id', $user->id)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $rides = $query->paginate($perPage)->appends($request->query());

        // Earnings summary scoped to same filters
        $summaryQuery = Ride::where('driver_id', $user->id)
            ->where('status', Ride::STATUS_COMPLETED);

        if ($request->filled('from')) {
            $summaryQuery->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $summaryQuery->whereDate('created_at', '<=', $request->query('to'));
        }

        $summary = $summaryQuery->selectRaw('
            COUNT(*)            as total_completed,
            COALESCE(SUM(fare), 0)          as total_earned,
            COALESCE(SUM(distance_km), 0)   as total_km,
            COALESCE(SUM(duration_min), 0)  as total_minutes,
            COALESCE(AVG(fare), 0)          as avg_fare
        ')->first();

        return $this->success([
            'summary' => [
                'total_completed' => (int) $summary->total_completed,
                'total_earned'    => (int) $summary->total_earned,
                'total_km'        => round((float) $summary->total_km, 2),
                'total_minutes'   => (int) $summary->total_minutes,
                'avg_fare'        => (int) $summary->avg_fare,
            ],
            'rides' => collect($rides->items())->map(fn ($ride) => [
                'id'             => $ride->id,
                'status'         => $ride->status,
                'pickup_address' => $ride->pickup_address,
                'dropoff_address'=> $ride->dropoff_address,
                'service_type'   => $ride->service_type,
                'fare'           => $ride->fare,
                'distance_km'    => $ride->distance_km,
                'duration_min'   => $ride->duration_min,
                'payment_method' => $ride->payment_method,
                'payment_status' => $ride->payment_status,
                'accepted_at'    => $ride->accepted_at,
                'completed_at'   => $ride->completed_at,
                'created_at'     => $ride->created_at,
                'passenger'      => $ride->passenger ? [
                    'id'         => $ride->passenger->id,
                    'name'       => $ride->passenger->name,
                    'phone'      => $ride->passenger->phone,
                    'avatar_url' => $ride->passenger->avatar_url,
                ] : null,
                'vehicle'        => $ride->vehicle ? [
                    'plate' => $ride->vehicle->license_plate,
                    'make'  => $ride->vehicle->make,
                    'model' => $ride->vehicle->model,
                    'type'  => $ride->vehicle->type,
                ] : null,
            ]),
            'pagination' => [
                'total'        => $rides->total(),
                'per_page'     => $rides->perPage(),
                'current_page' => $rides->currentPage(),
                'last_page'    => $rides->lastPage(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $status  = $request->query('status');
        $perPage = (int) ($request->query('per_page', 10));
        $perPage = min(max($perPage, 1), 100);

        $query = $user->role === 'driver'
            ? Ride::with(['passenger', 'vehicle'])->where('driver_id', $user->id)
            : Ride::with(['driver', 'vehicle'])->where('passenger_id', $user->id);

        if ($status) {
            $query->where('status', $status);
        }

        $rides = $query->orderByDesc('id')->paginate($perPage)->appends($request->query());

        return $this->success([
            'total' => $rides->total(),
            'rides' => $rides->items(),
            'pagination' => [
                'total'        => $rides->total(),
                'per_page'     => $rides->perPage(),
                'current_page' => $rides->currentPage(),
                'last_page'    => $rides->lastPage(),
            ],
        ]);
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

        $ride->load('passenger', 'driver', 'vehicle', 'stops');

        return $this->success([
            'ride'        => $ride,
            'driver_info' => $this->buildDriverInfo($ride->driver),
        ]);
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
            ? Ride::with(['passenger', 'vehicle', 'stops'])
                ->where('driver_id', $user->id)
                ->whereIn('status', $activeStatuses)
                ->latest('updated_at')
                ->first()
            : Ride::with(['driver', 'vehicle', 'stops'])
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
            ->paginate(10);

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
            // Multi-stop support so estimate matches the actual booking fare
            'stops'           => 'nullable|array|max:5',
            'stops.*.lat'     => 'required|numeric|between:-90,90',
            'stops.*.lng'     => 'required|numeric|between:-180,180',
        ]);

        $stops = $data['stops'] ?? [];

        // Build waypoint chain: pickup → [intermediate stops] → dropoff
        $allPoints = array_merge(
            [['lat' => (float) $data['pickup_lat'], 'lng' => (float) $data['pickup_lng']]],
            array_map(fn($s) => ['lat' => (float) $s['lat'], 'lng' => (float) $s['lng']], $stops),
            [['lat' => (float) $data['dropoff_lat'], 'lng' => (float) $data['dropoff_lng']]],
        );

        // Sum route across all consecutive segments
        $totalDistanceKm  = 0.0;
        $totalDurationMin = 0;
        for ($i = 0; $i < count($allPoints) - 1; $i++) {
            $seg = $this->fare->getRoute(
                $allPoints[$i]['lat'],     $allPoints[$i]['lng'],
                $allPoints[$i + 1]['lat'], $allPoints[$i + 1]['lng'],
            );
            $totalDistanceKm  += $seg['distance_km'];
            $totalDurationMin += $seg['duration_min'];
        }

        $route = [
            'distance_km'   => round($totalDistanceKm, 2),
            'duration_min'  => $totalDurationMin,
            'distance_text' => round($totalDistanceKm, 1) . ' km',
            'duration_text' => $totalDurationMin . ' mins',
            'source'        => count($stops) > 0 ? 'multi_stop' : ($seg['source'] ?? 'haversine'),
            'stop_count'    => count($allPoints) - 1,
        ];

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
            'route'    => $route,
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
            'dropoff_address' => 'nullable|string|max:255',
            'pickup_lat'      => 'required|numeric|between:-90,90',
            'pickup_lng'      => 'required|numeric|between:-180,180',
            'dropoff_lat'     => 'nullable|numeric|between:-90,90',
            'dropoff_lng'     => 'nullable|numeric|between:-180,180',
            'service_type'    => 'required|in:motorcycle,tuk_tuk,standard,premium,shared,van',
            'payment_method'  => 'nullable|in:cash,wallet,aba,acleda,wing',
            'surge_accepted'  => 'nullable|boolean',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string|max:500',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
            // Ride for someone else
            'passenger_name'  => 'nullable|string|max:100',
            'passenger_phone' => 'nullable|string|max:24',
            // Promo code
            'promo_code'      => 'nullable|string|max:32',
            // Multi-stop
            'stops'           => 'nullable|array|max:5',
            'stops.*.address' => 'required|string|max:255',
            'stops.*.lat'     => 'required|numeric|between:-90,90',
            'stops.*.lng'     => 'required|numeric|between:-180,180',
        ]);

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $data['driver_id'] = $vehicle?->user_id;
        }

        $stops = $data['stops'] ?? [];

        // If stops provided but no explicit dropoff, treat last stop as the final destination
        if (! empty($stops) && empty($data['dropoff_address'])) {
            $last = end($stops);
            $data['dropoff_address'] = $last['address'];
            $data['dropoff_lat']     = $last['lat'];
            $data['dropoff_lng']     = $last['lng'];
        }

        $hasDropoff = ! empty($data['dropoff_lat']) && ! empty($data['dropoff_lng']);

        // When no destination: fare is metered (calculated at completion by driver)
        if ($hasDropoff) {
            // Build waypoint chain: pickup → [intermediate stops] → dropoff
            $allPoints = array_merge(
                [['lat' => (float) $data['pickup_lat'], 'lng' => (float) $data['pickup_lng']]],
                array_map(fn($s) => ['lat' => (float) $s['lat'], 'lng' => (float) $s['lng']], $stops),
                [['lat' => (float) $data['dropoff_lat'], 'lng' => (float) $data['dropoff_lng']]],
            );

            // Remove duplicate final stop if last stop == dropoff
            if (count($stops) > 0) {
                $lastStop = end($stops);
                if ((float) $lastStop['lat'] === (float) $data['dropoff_lat']
                    && (float) $lastStop['lng'] === (float) $data['dropoff_lng']) {
                    // Last stop is already the dropoff — don't double-count it
                    array_pop($allPoints);
                }
            }

            // Sum route across all consecutive segments
            $totalDistanceKm  = 0.0;
            $totalDurationMin = 0;
            for ($i = 0; $i < count($allPoints) - 1; $i++) {
                $seg = $this->fare->getRoute(
                    $allPoints[$i]['lat'],     $allPoints[$i]['lng'],
                    $allPoints[$i + 1]['lat'], $allPoints[$i + 1]['lng'],
                );
                $totalDistanceKm  += $seg['distance_km'];
                $totalDurationMin += $seg['duration_min'];
            }

            $route = [
                'distance_km'   => round($totalDistanceKm, 2),
                'duration_min'  => $totalDurationMin,
                'distance_text' => round($totalDistanceKm, 1) . ' km',
                'duration_text' => $totalDurationMin . ' mins',
                'source'        => 'multi_stop',
                'stop_count'    => count($allPoints) - 1,
            ];

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
        } else {
            $fareResult = [
                'total'            => 0,
                'surge_multiplier' => 1.0,
                'surge_zone'       => null,
            ];
        }

        // Apply promo code if provided (only when fare is known upfront)
        $promoCodeId    = null;
        $discountAmount = 0;
        $finalFare      = $fareResult['total'];

        if ($hasDropoff && ! empty($data['promo_code'])) {
            $promo = PromoCode::where('code', strtoupper(trim($data['promo_code'])))->first();
            if ($promo && $promo->isValid('rides', $fareResult['total'], $user->id)) {
                $discountAmount = $promo->calculateDiscount($fareResult['total']);
                $finalFare      = max(0, $fareResult['total'] - $discountAmount);
                $promoCodeId    = $promo->id;
            }
        }

        $ride = Ride::create([
            'passenger_id'    => $user->id,
            'driver_id'       => $data['driver_id'] ?? null,
            'vehicle_id'      => $data['vehicle_id'] ?? null,
            'pickup_address'  => $data['pickup_address'],
            'dropoff_address' => $data['dropoff_address'] ?? null,
            'pickup_lat'      => (float) $data['pickup_lat'],
            'pickup_lng'      => (float) $data['pickup_lng'],
            'dropoff_lat'     => $hasDropoff ? (float) $data['dropoff_lat'] : null,
            'dropoff_lng'     => $hasDropoff ? (float) $data['dropoff_lng'] : null,
            'service_type'    => $data['service_type'],
            'payment_method'  => $data['payment_method'] ?? 'cash',
            'payment_status'  => 'unpaid',
            'scheduled_at'    => $data['scheduled_at'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'status'          => Ride::STATUS_REQUESTED,
            'fare'            => $finalFare,
            'distance_km'     => $hasDropoff ? ($route['distance_km'] ?? null) : null,
            'duration_min'    => $hasDropoff ? ($route['duration_min'] ?? null) : null,
            'surge_multiplier'=> $fareResult['surge_multiplier'],
            'surge_zone_id'   => $fareResult['surge_zone']['id'] ?? null,
            'surge_accepted'  => ! empty($data['surge_accepted']),
            'passenger_name'  => $data['passenger_name'] ?? null,
            'passenger_phone' => $data['passenger_phone'] ?? null,
            'promo_code_id'   => $promoCodeId,
            'discount_amount' => $discountAmount,
        ]);

        // Save intermediate stops (excluding the last stop when it equals the dropoff)
        if (! empty($stops)) {
            $stopsToSave = $stops;
            // If last stop duplicates the dropoff, it's already on the ride — skip it
            $lastStop = end($stops);
            if (count($stops) > 0
                && (float) $lastStop['lat'] === (float) ($data['dropoff_lat'] ?? 0)
                && (float) $lastStop['lng'] === (float) ($data['dropoff_lng'] ?? 0)) {
                array_pop($stopsToSave);
            }
            foreach ($stopsToSave as $i => $stop) {
                RideStop::create([
                    'ride_id'    => $ride->id,
                    'address'    => $stop['address'],
                    'lat'        => (float) $stop['lat'],
                    'lng'        => (float) $stop['lng'],
                    'sort_order' => $i,
                ]);
            }
        }

        if ($promoCodeId) {
            PromoCodeUsage::create([
                'promo_code_id'   => $promoCodeId,
                'user_id'         => $user->id,
                'bookable_type'   => Ride::class,
                'bookable_id'     => $ride->id,
                'discount_amount' => $discountAmount,
            ]);
            PromoCode::where('id', $promoCodeId)->increment('used_count');
        }

        $ride->load('driver', 'vehicle', 'stops');
        $this->firestore->syncRide($ride);

        $dropoffLabel = $data['dropoff_address'] ?? 'Destination TBD';
        try {
            $nearbyDrivers = User::where('role', 'driver')
                ->where('available', true)
                ->get();
            $this->fcm->rideRequestedToMany(
                $nearbyDrivers->all(),
                $ride->id,
                $data['pickup_address'],
                $dropoffLabel,
                $user->name,
                (int) $finalFare
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->success([
            'ride'         => $ride,
            'fare'         => $fareResult,
            'metered_ride' => ! $hasDropoff,
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

        // This driver already accepted — idempotent retry
        if ($ride->driver_id === $user->id && $ride->status === Ride::STATUS_ACCEPTED) {
            $loaded = $ride->load('passenger', 'driver', 'vehicle');
            return $this->success([
                'ride'        => $loaded,
                'driver_info' => $this->buildDriverInfo($loaded->driver),
                'message'     => 'Ride already accepted.',
            ]);
        }

        if (! in_array($ride->status, Ride::OPEN_STATUSES, true)) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot accept — ride is \"{$ride->status}\".",
            ], 422);
        }

        if ($ride->driver_id && $ride->driver_id !== $user->id) {
            return response()->json(['data' => null, 'message' => 'Ride already claimed by another driver.'], 422);
        }

        $vehicle = $user->vehicles()->where('status', 'active')->latest()->first();

        $ride->update([
            'driver_id'   => $user->id,
            'vehicle_id'  => $vehicle?->id,
            'status'      => Ride::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle', 'stops');
        $this->firestore->syncRide($fresh);

        // Notify passenger: driver is on the way
        if ($fresh->passenger) {
            $this->fcm->rideAccepted($fresh->passenger, $fresh->id, $fresh->driver->name ?? 'Your driver');
        }

        return $this->success([
            'ride'        => $fresh,
            'driver_info' => $this->buildDriverInfo($fresh->driver),
            'message'     => 'Ride accepted. Head to pickup location.',
        ]);
    }

    /**
     * POST /v1/rides/{ride}/reject
     * Driver explicitly rejects a ride request (ride stays pending for other drivers).
     */
    public function reject(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        if ($ride->status !== Ride::STATUS_PENDING) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot reject — ride is \"{$ride->status}\".",
            ], 422);
        }

        // Notify passenger only if no other driver has taken it
        try {
            if ($ride->passenger) {
                $this->fcm->rideRejected($ride->passenger, $ride->id);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->success(['message' => 'Ride rejected.']);
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

        // Already past this step — return current state (idempotent retry)
        if (in_array($ride->status, [Ride::STATUS_DRIVER_ARRIVED, Ride::STATUS_IN_PROGRESS, Ride::STATUS_COMPLETED], true)) {
            return $this->success([
                'ride'    => $ride->load('passenger', 'driver', 'vehicle'),
                'message' => 'Already arrived.',
            ]);
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

        if ($fresh->passenger) {
            $this->fcm->driverArrived($fresh->passenger, $fresh->id, $fresh->driver->name ?? 'Your driver');
        }

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

        // Already past this step — return current state (idempotent retry)
        if (in_array($ride->status, [Ride::STATUS_IN_PROGRESS, Ride::STATUS_COMPLETED], true)) {
            return $this->success([
                'ride'    => $ride->load('passenger', 'driver', 'vehicle'),
                'message' => 'Trip already started.',
            ]);
        }

        if ($ride->status !== Ride::STATUS_DRIVER_ARRIVED) {
            return response()->json([
                'data'    => null,
                'message' => "Cannot start — ride is \"{$ride->status}\". Must arrive first.",
            ], 422);
        }

        // Waiting time surcharge: free minutes then charge per minute
        $waitingFee      = 0;
        $waitingMinutes  = 0;
        $freeWaitMinutes = (int) \App\Models\PricingSetting::get('waiting_free_minutes', 3);
        $waitingRateKhr  = (int) \App\Models\PricingSetting::get('waiting_rate_per_min', 500);

        if ($ride->driver_arrived_at) {
            $waitingMinutes = (int) now()->diffInMinutes($ride->driver_arrived_at);
            $chargeMinutes  = max(0, $waitingMinutes - $freeWaitMinutes);
            $waitingFee     = $chargeMinutes * $waitingRateKhr;
        }

        $ride->update([
            'status'      => Ride::STATUS_IN_PROGRESS,
            'started_at'  => now(),
            'fare'        => $ride->fare + $waitingFee,
            'waiting_fee' => $waitingFee,
        ]);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        if ($fresh->passenger) {
            $this->fcm->rideStarted($fresh->passenger, $fresh->id);
        }

        return $this->success([
            'waiting_minutes' => $waitingMinutes,
            'waiting_fee'     => $waitingFee,
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

        $completionData = $request->validate([
            'dropoff_address' => 'nullable|string|max:255',
            'dropoff_lat'     => 'nullable|numeric|between:-90,90',
            'dropoff_lng'     => 'nullable|numeric|between:-180,180',
            'final_fare'      => 'nullable|integer|min:0',
        ]);

        $updates = [
            'status'       => Ride::STATUS_COMPLETED,
            'completed_at' => now(),
            'share_active' => false,
        ];

        // Always persist the driver's confirmed fare — applies to ALL ride types
        if (isset($completionData['final_fare'])) {
            $updates['fare'] = (int) $completionData['final_fare'];
        }

        // Metered rides: resolve final dropoff from request or driver's current GPS
        if (is_null($ride->dropoff_address)) {
            // Prefer explicit coords from request; fall back to driver's live location
            $dropoffLat = ! empty($completionData['dropoff_lat'])
                ? (float) $completionData['dropoff_lat']
                : ((float) $user->current_latitude ?: null);
            $dropoffLng = ! empty($completionData['dropoff_lng'])
                ? (float) $completionData['dropoff_lng']
                : ((float) $user->current_longitude ?: null);

            if (! empty($completionData['dropoff_address'])) {
                $updates['dropoff_address'] = $completionData['dropoff_address'];
            }

            if ($dropoffLat && $dropoffLng) {
                $updates['dropoff_lat'] = $dropoffLat;
                $updates['dropoff_lng'] = $dropoffLng;

                // Auto-calculate fare from actual driven route when no explicit fare was given
                if (! isset($completionData['final_fare'])) {
                    $mRoute = $this->fare->getRoute(
                        (float) $ride->pickup_lat, (float) $ride->pickup_lng,
                        $dropoffLat, $dropoffLng,
                    );
                    $fareResult = $this->fare->calculateRideFare(
                        $ride->service_type, $mRoute,
                        (float) $ride->pickup_lat, (float) $ride->pickup_lng,
                    );
                    $updates['fare']         = $fareResult['total'];
                    $updates['distance_km']  = $mRoute['distance_km'];
                    $updates['duration_min'] = $mRoute['duration_min'];
                }
            }
        }

        $ride->update($updates);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');

        $transaction = null;
        if ($fresh->fare > 0) {
            $transaction = app(PaymentService::class)->processRide($fresh);
        }
        $this->firestore->syncRide($fresh);

        // Notify passenger: trip completed with fare
        if ($fresh->passenger) {
            $this->fcm->rideCompleted($fresh->passenger, $fresh->id, (int) $fresh->fare);
        }

        // Email receipt to passenger
        if ($fresh->passenger?->email) {
            try {
                Mail::to($fresh->passenger->email)->queue(TripReceipt::fromRide($fresh));
            } catch (\Throwable $e) {
                report($e);
            }
        }

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

        // Cancellation fee: charged to passenger based on how late they cancel
        $cancellationFee = 0;
        $isByPassenger   = $user->id === $ride->passenger_id;

        if ($isByPassenger) {
            $feeAfterArrival  = (int) \App\Models\PricingSetting::get('cancel_fee_after_arrival', 3000);
            $feeAfterAccepted = (int) \App\Models\PricingSetting::get('cancel_fee_after_accepted', 1000);
            $freeMinutes      = (int) \App\Models\PricingSetting::get('cancel_free_minutes', 3);

            if ($ride->status === Ride::STATUS_DRIVER_ARRIVED) {
                $cancellationFee = $feeAfterArrival;
            } elseif ($ride->status === Ride::STATUS_ACCEPTED && $ride->accepted_at) {
                $minutesSinceAccept = (int) now()->diffInMinutes($ride->accepted_at);
                if ($minutesSinceAccept >= $freeMinutes) {
                    $cancellationFee = $feeAfterAccepted;
                }
            }

            if ($cancellationFee > 0 && $user->wallet_balance >= $cancellationFee) {
                $this->wallet->debit($user, $cancellationFee, 'cancellation_fee', "Cancellation fee for ride #{$ride->id}");
            } elseif ($cancellationFee > 0) {
                $cancellationFee = 0; // Don't charge if insufficient balance
            }
        }

        // Track driver cancellations
        if (! $isByPassenger && $ride->driver_id) {
            $driver = User::find($ride->driver_id);
            if ($driver) {
                $newCount = $driver->cancellation_count + 1;
                $updates  = ['cancellation_count' => $newCount];
                if ($newCount >= 5) {
                    $updates['cancellation_penalty_until'] = now()->addHours(24);
                }
                $driver->update($updates);
            }
        }

        $ride->update([
            'status'             => Ride::STATUS_CANCELLED,
            'cancelled_at'       => now(),
            'cancellation_reason'=> $request->input('reason'),
            'cancellation_fee'   => $cancellationFee,
            'share_active'       => false,
        ]);

        $fresh = $ride->fresh()->load('passenger', 'driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        // Notify the other party about cancellation
        if ($isByPassenger && $fresh->driver) {
            $this->fcm->rideCancelledByPassenger($fresh->driver, $fresh->id);
        } elseif (! $isByPassenger && $fresh->passenger) {
            $this->fcm->rideCancelledByDriver($fresh->passenger, $fresh->id);
        }

        return $this->success([
            'ride'             => $fresh,
            'cancellation_fee' => $cancellationFee,
        ]);
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

        return $this->success(['rated' => true, 'ride' => $ride->fresh()]);
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

    // ── Tip driver ───────────────────────────────────────────────────────────

    /**
     * POST /v1/rides/{ride}/tip
     * Passenger adds a tip after a completed ride. Deducted from wallet.
     * Body: amount (KHR, min 500)
     */
    public function tip(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->id !== $ride->passenger_id) {
            return $this->unauthorized();
        }

        if ($ride->status !== Ride::STATUS_COMPLETED) {
            return response()->json(['data' => null, 'message' => 'Tips can only be added to completed rides.'], 422);
        }

        if ($ride->tip_amount > 0) {
            return response()->json(['data' => null, 'message' => 'A tip has already been added for this ride.'], 422);
        }

        $data = $request->validate([
            'amount' => 'required|integer|min:500',
        ]);

        if ($user->wallet_balance < $data['amount']) {
            return response()->json(['data' => null, 'message' => 'Insufficient wallet balance.'], 422);
        }

        // Deduct from passenger wallet
        $this->wallet->debit($user, $data['amount'], 'tip_out', "Tip for ride #{$ride->id}");

        // Credit driver wallet
        if ($ride->driver_id) {
            $driver = \App\Models\User::find($ride->driver_id);
            if ($driver) {
                $this->wallet->credit($driver, $data['amount'], 'tip_in', "Tip from passenger for ride #{$ride->id}");
            }
        }

        $ride->update(['tip_amount' => $data['amount']]);

        if ($ride->driver_id) {
            $this->fcm->sendToUser(
                $ride->driver_id,
                'You received a tip!',
                "Your passenger added a " . number_format($data['amount']) . " KHR tip.",
                ['type' => 'tip_received', 'ride_id' => (string) $ride->id, 'amount' => (string) $data['amount']],
            );
        }

        return $this->success([
            'message'    => 'Tip sent successfully.',
            'tip_amount' => $ride->tip_amount,
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

    // ── Rate passenger ────────────────────────────────────────────────────────

    /**
     * POST /v1/rides/{ride}/rate-passenger
     * Driver rates the passenger after a completed ride.
     * Body: rating (1–5), comment (optional)
     */
    public function ratePassenger(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $ride->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        if ($ride->status !== Ride::STATUS_COMPLETED) {
            return response()->json(['data' => null, 'message' => 'Ride must be completed before rating.'], 422);
        }

        if ($ride->passenger_rating !== null) {
            return response()->json(['data' => null, 'message' => 'Passenger already rated for this ride.'], 422);
        }

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $ride->update([
            'passenger_rating'         => $data['rating'],
            'passenger_rating_comment' => $data['comment'] ?? null,
            'passenger_rated_at'       => now(),
        ]);

        // Update passenger's overall rating (rolling average).
        $passenger = $ride->passenger;
        if ($passenger) {
            $total  = ($passenger->total_ratings ?? 0) + 1;
            $newAvg = (($passenger->rating ?? 0) * ($total - 1) + $data['rating']) / $total;
            $passenger->update([
                'rating'       => round($newAvg, 2),
                'total_ratings'=> $total,
            ]);
        }

        return $this->success(['rated' => true, 'passenger_rating' => $data['rating']]);
    }

    private function authUserOrFail(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) abort(401, 'Unauthorized');
        return $user;
    }

    private function buildDriverInfo(?User $driver): ?array
    {
        if (! $driver) return null;

        $vehicle = $driver->vehicles()->where('status', 'active')->latest()->first();

        return [
            'name'       => $driver->name,
            'phone'      => $driver->phone,
            'avatar_url' => $driver->avatar_url,
            'rating'     => (float) ($driver->rating ?? 0),
            'vehicle'    => $vehicle ? [
                'plate'       => $vehicle->license_plate,
                'make'        => $vehicle->make,
                'model'       => $vehicle->model,
                'type'        => $vehicle->type,
                'year'        => $vehicle->year,
                'color'       => $vehicle->details,
                'vehicle_url' => $vehicle->primary_image_url,
            ] : null,
        ];
    }
}
