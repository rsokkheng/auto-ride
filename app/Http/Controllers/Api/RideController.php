<?php

namespace App\Http\Controllers\Api;

use App\Models\Ride;
use App\Models\SurgeZone;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FirestoreService;
use App\Services\PaymentService;
use App\Services\SurgeZoneService;
use Illuminate\Http\Request;

class RideController extends ApiController
{
    public function __construct(
        private SurgeZoneService $surge,
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
            'pickup_lat'      => 'nullable|numeric|between:-90,90',
            'pickup_lng'      => 'nullable|numeric|between:-180,180',
            'distance'        => 'nullable|numeric|min:0',
            'scheduled_at'    => 'nullable|date',
            'service_type'    => 'required|string|in:standard,premium,shared',
            'notes'           => 'nullable|string',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
        ]);

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $data['driver_id'] = $vehicle?->user_id;
        }

        $fareResult = $this->calculateFare(
            $data['service_type'],
            (float) ($data['distance'] ?? 5),
            isset($data['pickup_lat'], $data['pickup_lng'])
                ? [(float) $data['pickup_lat'], (float) $data['pickup_lng']]
                : null
        );

        $ride = Ride::create(array_merge($data, [
            'passenger_id'    => $user->id,
            'status'          => 'requested',
            'fare'            => $fareResult['total'],
            'surge_multiplier'=> $fareResult['multiplier'],
            'surge_zone_id'   => $fareResult['zone']?->id,
        ]));

        $ride->load('driver', 'vehicle');
        $this->firestore->syncRide($ride);

        return $this->success([
            'ride' => $ride,
            'fare' => $fareResult,
        ], 201);
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

        if ($ride->driver_id && $ride->driver_id !== $user->id) {
            return response()->json(['message' => 'Ride already claimed'], 422);
        }

        $ride->update(['driver_id' => $user->id, 'status' => 'accepted']);

        $fresh = $ride->fresh()->load('driver', 'vehicle');
        $this->firestore->syncRide($fresh);

        return $this->success(['ride' => $fresh]);
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
     * Body: distance?, service_type?, pickup_lat?, pickup_lng?
     */
    public function estimate(Request $request)
    {
        $data = $request->validate([
            'distance'     => 'nullable|numeric|min:0',
            'service_type' => 'nullable|in:standard,premium,shared',
            'pickup_lat'   => 'nullable|numeric|between:-90,90',
            'pickup_lng'   => 'nullable|numeric|between:-180,180',
        ]);

        $fareResult = $this->calculateFare(
            $data['service_type'] ?? 'standard',
            (float) ($data['distance'] ?? 5),
            isset($data['pickup_lat'], $data['pickup_lng'])
                ? [(float) $data['pickup_lat'], (float) $data['pickup_lng']]
                : null
        );

        return $this->success(['estimate' => $fareResult]);
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

    /**
     * Calculate ride fare with optional surge zone pricing.
     *
     * Formula: (base + per_km × max(1, distance)) × surge_multiplier
     * Rounded up to nearest 100 ៛.
     *
     * @param  array<float>|null  $latLng  [lat, lng] of pickup; null = no surge check
     * @return array{base: int, multiplier: float, surge_amount: int, total: int, zone: ?SurgeZone, breakdown: array}
     */
    protected function calculateFare(string $serviceType, float $distance = 5, ?array $latLng = null): array
    {
        [$base, $perKm] = match ($serviceType) {
            'premium' => [
                config('delivery.ride_base_premium',  8000),
                config('delivery.ride_perkm_premium', 3000),
            ],
            'shared'  => [
                config('delivery.ride_base_shared',  2500),
                config('delivery.ride_perkm_shared', 1000),
            ],
            default   => [
                config('delivery.ride_base_standard',  4000),
                config('delivery.ride_perkm_standard', 1500),
            ],
        };

        $baseFare = (int) ($base + ($perKm * max(1, $distance)));

        if ($latLng) {
            $result = $this->surge->applyTo($baseFare, $latLng[0], $latLng[1], 'rides');
        } else {
            $result = [
                'base'         => $baseFare,
                'multiplier'   => 1.0,
                'surge_amount' => 0,
                'total'        => (int) (ceil($baseFare / 100) * 100),
                'zone'         => null,
            ];
        }

        $result['breakdown'] = [
            'service_type' => $serviceType,
            'base_fee'     => $base,
            'distance_km'  => $distance,
            'per_km_rate'  => $perKm,
            'fare_before_surge' => $baseFare,
        ];

        return $result;
    }

    protected function authUserOrFail(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) abort(401, 'Unauthorized');
        return $user;
    }
}
