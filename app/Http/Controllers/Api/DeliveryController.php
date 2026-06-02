<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Vehicle;
use App\Services\DriverMatchingService;
use App\Services\FareService;
use App\Services\FirestoreService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class DeliveryController extends ApiController
{
    public function __construct(
        private DriverMatchingService $matcher,
        private FareService $fare,
        private FirestoreService $firestore,
    ) {}

    // ── List / History ──────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        if ($user->role === 'driver') {
            $deliveries = Delivery::with(['sender', 'vehicle'])->where('driver_id', $user->id)->paginate(20);
        } else {
            $deliveries = Delivery::with(['driver', 'vehicle'])->where('sender_id', $user->id)->paginate(20);
        }

        return $this->success(['deliveries' => $deliveries]);
    }

    public function history(Request $request)
    {
        return $this->index($request);
    }

    /**
     * GET /v1/deliveries/available
     *
     * Unassigned deliveries a driver can pick up.
     * Mirrors GET /v1/rides/available.
     */
    public function available(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $deliveries = Delivery::with(['sender', 'vehicle'])
            ->whereIn('status', ['requested', 'pending'])
            ->whereNull('driver_id')
            ->orderBy('created_at')
            ->paginate(20);

        return $this->success(['deliveries' => $deliveries]);
    }

    public function show(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if (! in_array($user->id, [$delivery->sender_id, $delivery->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success(['delivery' => $delivery->load('sender', 'driver', 'vehicle')]);
    }

    // ── Nearby Drivers ──────────────────────────────────────────────────────

    /**
     * GET /v1/deliveries/nearby-drivers
     *
     * Query params:
     *   pickup_lat   float   required
     *   pickup_lng   float   required
     *   limit        int     optional (default 10, max 50)
     */
    public function nearbyDrivers(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'limit'      => 'nullable|integer|min:1|max:50',
        ]);

        $limit   = (int) ($data['limit'] ?? 10);
        $drivers = $this->matcher->findDrivers((float) $data['pickup_lat'], (float) $data['pickup_lng'], $limit);

        $response = $drivers->map(fn($d) => [
            'id'              => $d->id,
            'name'            => $d->name,
            'phone'           => $d->phone,
            'rating'          => (float) $d->rating,
            'total_ratings'   => $d->total_ratings,
            'distance_km'     => $d->distance_km,
            'score'           => $d->score,
            'distance_source' => $d->distance_source,
            'vehicle'         => $d->vehicles->first(),
        ]);

        return $this->success([
            'drivers'       => $response,
            'total'         => $response->count(),
            'radius_km'     => config('delivery.match_radius_km', 30),
        ]);
    }

    // ── Store (book a delivery) ─────────────────────────────────────────────

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'sender_name'     => 'required|string|max:255',
            'recipient_name'  => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:24',
            'package_size'    => 'required|in:small,medium,large',
            'pickup_address'  => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'pickup_lat'      => 'nullable|numeric|between:-90,90',
            'pickup_lng'      => 'nullable|numeric|between:-180,180',
            'scheduled_at'    => 'nullable|date',
            'package_details' => 'nullable|string|max:500',
            'fee'             => 'nullable|numeric|min:0',
            'payment_by'      => 'nullable|in:sender,recipient',
            'payment_method'  => 'nullable|in:cash,wallet,aba,wing,other_online',
            'notes'           => 'nullable|string',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
        ]);

        $driverId = null;

        // If a specific vehicle was requested, honour it.
        if (! empty($data['vehicle_id'])) {
            $vehicle  = Vehicle::find($data['vehicle_id']);
            $driverId = $vehicle?->user_id;
        }

        // If no driver yet and we have coordinates, auto-assign the best nearby driver.
        if (! $driverId && ! empty($data['pickup_lat']) && ! empty($data['pickup_lng'])) {
            $best     = $this->matcher->findDrivers((float) $data['pickup_lat'], (float) $data['pickup_lng'], 1)->first();
            $driverId = $best?->id;
        }

        // Auto-calculate fee with surge if caller didn't provide one.
        $fee            = (int) ($data['fee'] ?? 0);
        $surgeMultiplier = 1.0;
        $surgeZoneId    = null;

        if ($fee === 0 && ! empty($data['pickup_lat']) && ! empty($data['pickup_lng'])) {
            $size      = $data['package_size'] ?? 'small';
            $baseFee   = (int) (
                config('delivery.fee_base', 3000)
                + config('delivery.fee_per_km', 1200) * 5
                + config("delivery.fee_surcharge_{$size}", 0)
            );
            $surgeResult     = $this->surge->applyTo($baseFee, (float) $data['pickup_lat'], (float) $data['pickup_lng'], 'deliveries');
            $fee             = $surgeResult['total'];
            $surgeMultiplier = $surgeResult['multiplier'];
            $surgeZoneId     = $surgeResult['zone']?->id;
        }

        $delivery = Delivery::create(array_merge(
            $data,
            [
                'sender_id'        => $user->id,
                'driver_id'        => $driverId,
                'status'           => 'requested',
                'fee'              => $fee,
                'payment_by'       => $data['payment_by'] ?? 'sender',
                'payment_method'   => $data['payment_method'] ?? 'cash',
                'payment_status'   => 'unpaid',
                'package_details'  => $data['package_details'] ?? '',
                'assigned_at'      => $driverId ? now() : null,
                'surge_multiplier' => $surgeMultiplier,
                'surge_zone_id'    => $surgeZoneId,
            ]
        ));

        $delivery->load('driver', 'vehicle');
        $this->firestore->syncDelivery($delivery);

        return $this->success(['delivery' => $delivery], 201);
    }

    // ── Accept ──────────────────────────────────────────────────────────────

    public function accept(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        // Only accept deliveries that are still open.
        if (! in_array($delivery->status, ['requested', 'pending'], true)) {
            return response()->json([
                'message' => "Delivery cannot be accepted — current status is \"{$delivery->status}\".",
            ], 422);
        }

        // Block if another driver already claimed it.
        if ($delivery->driver_id && $delivery->driver_id !== $user->id) {
            return response()->json(['message' => 'Delivery already claimed by another driver.'], 422);
        }

        $delivery->update([
            'driver_id'   => $user->id,
            'status'      => 'accepted',
            'assigned_at' => $delivery->assigned_at ?? now(),
        ]);

        $fresh = $delivery->fresh()->load('sender', 'driver', 'vehicle');

        // Sync booking to Firestore — Flutter sender listens here.
        $this->firestore->syncDelivery($fresh);

        return $this->success([
            'delivery' => $fresh,
            'message'  => 'Delivery accepted. Head to pickup location.',
        ]);
    }

    // ── Fee Estimate ────────────────────────────────────────────────────────

    /**
     * POST /v1/deliveries/estimate
     *
     * Returns an estimated delivery fee in Khmer Riel (KHR ៛).
     *
     * Rates:
     *   Base fee    : 3,000 ៛
     *   Per km      : 1,200 ៛
     *   Package size surcharge:
     *     small  → +0
     *     medium → +2,000 ៛
     *     large  → +5,000 ៛
     *
     * Result is rounded up to the nearest 100 ៛.
     */
    /**
     * POST /v1/deliveries/estimate
     *
     * Body:
     *   pickup_lat, pickup_lng   required
     *   dropoff_lat, dropoff_lng required
     *   package_size             optional (small|medium|large, default: small)
     */
    public function estimate(Request $request)
    {
        $data = $request->validate([
            'pickup_lat'   => 'required|numeric|between:-90,90',
            'pickup_lng'   => 'required|numeric|between:-180,180',
            'dropoff_lat'  => 'required|numeric|between:-90,90',
            'dropoff_lng'  => 'required|numeric|between:-180,180',
            'package_size' => 'nullable|in:small,medium,large',
        ]);

        $route  = $this->fare->getRoute(
            (float) $data['pickup_lat'],  (float) $data['pickup_lng'],
            (float) $data['dropoff_lat'], (float) $data['dropoff_lng'],
        );
        $result = $this->fare->calculateDeliveryFare(
            $data['package_size'] ?? 'small',
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
            'fare' => $result,
        ]);
    }

    // ── Track ───────────────────────────────────────────────────────────────

    public function track(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$delivery->sender_id, $delivery->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success([
            'delivery' => $delivery->load('driver', 'vehicle'),
            'tracking' => [
                'status'      => $delivery->status,
                'eta_minutes' => 12,
                'driver'      => $delivery->driver?->only(['id', 'name', 'phone']),
            ],
        ]);
    }

    // ── Cancel ──────────────────────────────────────────────────────────────

    public function cancel(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$delivery->sender_id, $delivery->driver_id], true)) {
            return $this->unauthorized();
        }

        if (in_array($delivery->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Delivery cannot be cancelled'], 422);
        }

        $delivery->update(['status' => 'cancelled']);
        $this->firestore->syncDelivery($delivery->fresh()->load('driver'));

        return $this->success(['delivery' => $delivery]);
    }

    // ── Confirm / Complete ──────────────────────────────────────────────────

    public function confirm(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || $delivery->sender_id !== $user->id) {
            return $this->unauthorized();
        }

        $delivery->update(['status' => 'completed']);

        // Create/update transaction record and process payment.
        $transaction = null;
        if ($delivery->fee > 0) {
            $transaction = app(PaymentService::class)->processDelivery($delivery->fresh());
        }

        $fresh = $delivery->fresh()->load('driver');
        $this->firestore->syncDelivery($fresh);

        return $this->success([
            'delivery'    => $fresh,
            'transaction' => $transaction,
        ]);
    }

    public function complete(Request $request, Delivery $delivery)
    {
        return $this->confirm($request, $delivery);
    }

    // ── Rate ────────────────────────────────────────────────────────────────

    /**
     * POST /v1/deliveries/{delivery}/rate
     *
     * Body:
     *   rating          float   required  1.0 – 5.0
     *   rating_comment  string  optional
     *
     * Only the sender may rate, and only after the delivery is completed.
     * Updates the driver's cached average rating atomically.
     */
    public function rate(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || $delivery->sender_id !== $user->id) {
            return $this->unauthorized();
        }

        if ($delivery->status !== 'completed') {
            return response()->json(['message' => 'Delivery must be completed before rating'], 422);
        }

        if ($delivery->rating !== null) {
            return response()->json(['message' => 'Delivery already rated'], 422);
        }

        $data = $request->validate([
            'rating'         => 'required|numeric|min:1|max:5',
            'rating_comment' => 'nullable|string|max:500',
        ]);

        $delivery->update([
            'rating'         => $data['rating'],
            'rating_comment' => $data['rating_comment'] ?? null,
        ]);

        // Update driver's cached aggregate rating (running average).
        if ($delivery->driver) {
            $driver       = $delivery->driver;
            $oldTotal     = $driver->total_ratings;
            $newTotal     = $oldTotal + 1;
            $newAvg       = round((($driver->rating * $oldTotal) + (float) $data['rating']) / $newTotal, 2);

            $driver->update([
                'rating'       => $newAvg,
                'total_ratings' => $newTotal,
            ]);
        }

        return $this->success([
            'delivery'       => $delivery->fresh(),
            'driver_rating'  => $delivery->driver?->fresh()->only(['id', 'rating', 'total_ratings']),
        ]);
    }
}
