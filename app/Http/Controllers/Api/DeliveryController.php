<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Vehicle;
use App\Services\DriverMatchingService;
use App\Services\FareService;
use App\Services\FirestoreService;
use App\Services\MovingFareService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryController extends ApiController
{
    public function __construct(
        private DriverMatchingService $matcher,
        private FareService $fare,
        private FirestoreService $firestore,
        private MovingFareService $movingFare,
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

    public function storeMoving(Request $request)
    {
        $request->merge(['service_type' => 'moving']);
        return $this->store($request);
    }

    public function estimateMoving(Request $request)
    {
        $request->merge(['service_type' => 'moving']);
        return $this->estimate($request);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        foreach (['payment_model', 'helper_type'] as $key) {
            if ($request->exists($key) && $request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }

        $serviceType = $request->input('service_type') ?? 'delivery';

        $data = $request->validate([
            'service_type'      => 'nullable|in:delivery,moving',
            'service_option'    => 'nullable|in:normal,express',
            'sender_name'       => [Rule::requiredIf($serviceType === 'delivery'), 'string', 'max:255'],
            'recipient_name'    => [Rule::requiredIf($serviceType === 'delivery'), 'string', 'max:255'],
            'recipient_phone'   => [Rule::requiredIf($serviceType === 'delivery'), 'string', 'max:24'],
            'package_size'      => 'nullable|in:small,medium,large',
            'pickup_address'    => 'required|string|max:255',
            'dropoff_address'   => 'required|string|max:255',
            'pickup_lat'        => 'nullable|numeric|between:-90,90',
            'pickup_lng'        => 'nullable|numeric|between:-180,180',
            'dropoff_lat'       => 'nullable|numeric|between:-90,90',
            'dropoff_lng'       => 'nullable|numeric|between:-180,180',
            'scheduled_at'      => 'nullable|date',
            'package_details'   => 'nullable|string|max:500',
            'fee'               => 'nullable|numeric|min:0',
            'payment_by'        => 'nullable|in:sender,recipient',
            'payment_method'    => 'nullable|in:cash,wallet,aba,wing,other_online',
            'notes'             => 'nullable|string',
            'vehicle_id'        => 'nullable|exists:vehicles,id',
            // Moving-specific
            'floor_pickup'       => 'nullable|integer|min:0|max:50',
            'floor_dropoff'      => 'nullable|integer|min:0|max:50',
            'has_elevator'       => 'nullable|boolean',
            'needs_stairs_carry' => 'nullable|boolean',
            'heavy_items'        => 'nullable|boolean',
            'requires_helpers'   => 'nullable|integer|min:0|max:4',
            'helper_type'        => 'sometimes|nullable|in:normal_carry,heavy_carry',
            // Payment model
            'payment_model'      => 'sometimes|nullable|in:customer_pays,partner_pays,split_payment,sponsored',
            'split_pct_customer' => 'nullable|integer|min:0|max:100',
            'partner_reference'  => 'nullable|string|max:150',
        ]);

        $serviceType = $data['service_type'] ?? 'delivery';
        $driverId    = null;

        if (! empty($data['vehicle_id'])) {
            $vehicle  = Vehicle::find($data['vehicle_id']);
            $driverId = $vehicle?->user_id;
        }

        if (! $driverId && ! empty($data['pickup_lat']) && ! empty($data['pickup_lng'])) {
            $best     = $this->matcher->findDrivers((float) $data['pickup_lat'], (float) $data['pickup_lng'], 1)->first();
            $driverId = $best?->id;
        }

        // Calculate fee based on service type.
        $fee        = (int) ($data['fee'] ?? 0);
        $helperFee  = null;
        $floorFee   = null;

        if ($fee === 0 && $serviceType === 'moving'
            && ! empty($data['pickup_lat']) && ! empty($data['dropoff_lat'])
        ) {
            $fareResult = $this->movingFare->estimate(
                (float) $data['pickup_lat'],  (float) $data['pickup_lng'],
                (float) $data['dropoff_lat'], (float) $data['dropoff_lng'],
                (int) ($data['floor_pickup']     ?? 0),
                (int) ($data['floor_dropoff']    ?? 0),
                (bool) ($data['has_elevator']    ?? false),
                (int) ($data['requires_helpers'] ?? 0),
                $data['helper_type'] ?? 'normal_carry',
            );
            $fee       = $fareResult['total'];
            $helperFee = $fareResult['helper_fee'];
            $floorFee  = $fareResult['floor_fee'];
        }

        // Apply express multiplier if requested.
        $serviceOption = $data['service_option'] ?? 'normal';
        $multiplier = $serviceOption === 'express'
            ? (float) \App\Models\PricingSetting::get('delivery_express_multiplier', config('delivery.express_multiplier', 1.25))
            : 1.0;

        if ($multiplier !== 1.0) {
            $fee = (int) ceil(($fee * $multiplier) / 100) * 100;
            if ($helperFee !== null) {
                $helperFee = (int) ceil(($helperFee * $multiplier) / 100) * 100;
            }
            if ($floorFee !== null) {
                $floorFee = (int) ceil(($floorFee * $multiplier) / 100) * 100;
            }
        }

        $delivery = Delivery::create([
            'sender_id'         => $user->id,
            'driver_id'         => $driverId,
            'service_type'      => $serviceType,
            'service_option'    => $data['service_option'] ?? 'normal',
            'sender_name'       => $data['sender_name'] ?? $user->name,
            'recipient_name'    => $data['recipient_name'] ?? null,
            'recipient_phone'   => $data['recipient_phone'] ?? null,
            'package_size'      => $data['package_size'] ?? null,
            'pickup_address'    => $data['pickup_address'],
            'dropoff_address'   => $data['dropoff_address'],
            'pickup_lat'        => $data['pickup_lat'] ?? null,
            'pickup_lng'        => $data['pickup_lng'] ?? null,
            'scheduled_at'      => $data['scheduled_at'] ?? null,
            'package_details'   => $data['package_details'] ?? '',
            'notes'             => $data['notes'] ?? null,
            'status'            => 'requested',
            'fee'               => $fee,
            'payment_by'        => $data['payment_by'] ?? 'sender',
            'payment_method'    => $data['payment_method'] ?? 'cash',
            'payment_status'    => 'unpaid',
            'assigned_at'       => $driverId ? now() : null,
            'surge_multiplier'  => 1.0,
            // Moving fields
            'floor_pickup'       => $data['floor_pickup']      ?? null,
            'floor_dropoff'      => $data['floor_dropoff']     ?? null,
            'has_elevator'       => $data['has_elevator']      ?? false,
            'needs_stairs_carry' => $data['needs_stairs_carry'] ?? false,
            'heavy_items'        => $data['heavy_items']       ?? false,
            'requires_helpers'   => $data['requires_helpers']  ?? 0,
            'helper_type'        => $data['helper_type']       ?? null,
            'helper_fee'         => $helperFee,
            'floor_fee'          => $floorFee,
            'service_option'     => $serviceOption,
            'express_multiplier' => $multiplier !== 1.0 ? $multiplier : null,
            // Payment model
            'payment_model'      => $data['payment_model']     ?? 'customer_pays',
            'split_pct_customer' => $data['split_pct_customer'] ?? null,
            'partner_reference'  => $data['partner_reference'] ?? null,
        ]);

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
    /**
     * POST /v1/deliveries/estimate
     *
     * For service_type=delivery: returns package delivery fare.
     * For service_type=moving:   returns full moving fare breakdown
     *   (base + distance + truck + helper + floor fees).
     */
    public function estimate(Request $request)
    {
        $data = $request->validate([
            'service_type'      => 'nullable|in:delivery,moving',
            'service_option'    => 'nullable|in:normal,express',
            'pickup_lat'        => 'required|numeric|between:-90,90',
            'pickup_lng'        => 'required|numeric|between:-180,180',
            'dropoff_lat'       => 'required|numeric|between:-90,90',
            'dropoff_lng'       => 'required|numeric|between:-180,180',
            // Delivery
            'package_size'      => 'nullable|in:small,medium,large',
            // Moving
            'floor_pickup'      => 'nullable|integer|min:0|max:50',
            'floor_dropoff'     => 'nullable|integer|min:0|max:50',
            'has_elevator'      => 'nullable|boolean',
            'requires_helpers'  => 'nullable|integer|min:0|max:4',
            'helper_type'       => 'nullable|in:normal_carry,heavy_carry',
        ]);

        $serviceType = $data['service_type'] ?? 'delivery';

        if ($serviceType === 'moving') {
            $fare = $this->movingFare->estimate(
                (float) $data['pickup_lat'],  (float) $data['pickup_lng'],
                (float) $data['dropoff_lat'], (float) $data['dropoff_lng'],
                (int) ($data['floor_pickup']    ?? 0),
                (int) ($data['floor_dropoff']   ?? 0),
                (bool) ($data['has_elevator']   ?? false),
                (int) ($data['requires_helpers']?? 0),
                $data['helper_type'] ?? 'normal_carry',
            );

            // Apply service option multiplier (normal|express)
            $serviceOption = $data['service_option'] ?? 'normal';
            $multiplier = $serviceOption === 'express'
                ? (float) config('delivery.express_multiplier', 1.25)
                : 1.0;

            if ($multiplier !== 1.0) {
                    $fare['total'] = (int) ceil(($fare['total'] * $multiplier) / 100) * 100;
                    $fare['helper_fee'] = (int) ceil((($fare['helper_fee'] ?? 0) * $multiplier) / 100) * 100;
                    $fare['floor_fee'] = (int) ceil((($fare['floor_fee'] ?? 0) * $multiplier) / 100) * 100;
                $fare['express_multiplier'] = $multiplier;
                $fare['service_option'] = $serviceOption;
            }

            return $this->success([
                'service_type' => 'moving',
                'fare'         => $fare,
            ]);
        }

        // Default: package delivery estimate.
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

        // Apply express multiplier for delivery estimates
        $serviceOption = $data['service_option'] ?? 'normal';
        $multiplier = $serviceOption === 'express'
            ? (float) \App\Models\PricingSetting::get('delivery_express_multiplier', config('delivery.express_multiplier', 1.25))
            : 1.0;

        if ($multiplier !== 1.0) {
            $result['subtotal'] = (int) ceil(($result['subtotal'] * $multiplier) / 100) * 100;
            $result['total'] = (int) ceil(($result['total'] * $multiplier) / 100) * 100;
            foreach ($result['breakdown'] as $k => $v) {
                if (is_numeric($v)) {
                    $result['breakdown'][$k] = (int) ceil(($v * $multiplier) / 100) * 100;
                }
            }
            $result['express_multiplier'] = $multiplier;
            $result['service_option'] = $serviceOption;
        }

        return $this->success([
            'service_type' => 'delivery',
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

    // ── Update ──────────────────────────────────────────────────────────────

    /**
     * PUT/PATCH /v1/deliveries/{delivery}
     *
     * Updates delivery details. Only allowed before delivery is accepted or in certain statuses.
     */
    public function update(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || $delivery->sender_id !== $user->id) {
            return $this->unauthorized();
        }

        // Only allow updates for pending/requested deliveries
        if (! in_array($delivery->status, ['requested', 'pending'], true)) {
            return response()->json([
                'message' => "Cannot update delivery with status '{$delivery->status}'",
            ], 422);
        }

        $data = $request->validate([
            'sender_name'       => 'nullable|string|max:255',
            'service_option'    => 'nullable|in:normal,express',
            'recipient_name'    => 'nullable|string|max:255',
            'recipient_phone'   => 'nullable|string|max:24',
            'package_size'      => 'nullable|in:small,medium,large',
            'pickup_address'    => 'nullable|string|max:255',
            'dropoff_address'   => 'nullable|string|max:255',
            'pickup_lat'        => 'nullable|numeric|between:-90,90',
            'pickup_lng'        => 'nullable|numeric|between:-180,180',
            'dropoff_lat'       => 'nullable|numeric|between:-90,90',
            'dropoff_lng'       => 'nullable|numeric|between:-180,180',
            'scheduled_at'      => 'nullable|date',
            'package_details'   => 'nullable|string|max:500',
            'payment_method'    => 'nullable|in:cash,wallet,aba,wing,other_online',
            'notes'             => 'nullable|string',
            'floor_pickup'      => 'nullable|integer|min:0|max:50',
            'floor_dropoff'     => 'nullable|integer|min:0|max:50',
            'has_elevator'      => 'nullable|boolean',
            'needs_stairs_carry' => 'nullable|boolean',
            'heavy_items'       => 'nullable|boolean',
            'requires_helpers'  => 'nullable|integer|min:0|max:4',
            'helper_type'       => 'nullable|in:normal_carry,heavy_carry',
        ]);

        $updateData = array_filter($data, fn($value) => $value !== null);

        $delivery->update($updateData);
        $delivery->load('driver', 'vehicle');

        $this->firestore->syncDelivery($delivery);

        return $this->success([
            'delivery' => $delivery,
            'message' => 'Delivery updated successfully',
        ]);
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    /**
     * DELETE /v1/deliveries/{delivery}
     *
     * Soft deletes a delivery. Only allowed for pending/requested deliveries.
     * The sender can delete their own delivery, or a driver can delete if not yet confirmed.
     */
    public function destroy(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        // Only sender can delete, or driver can delete if still pending
        if ($delivery->sender_id !== $user->id && ! ($user->role === 'driver' && $delivery->driver_id === $user->id)) {
            return $this->unauthorized();
        }

        // Only allow deletion for certain statuses
        if (! in_array($delivery->status, ['requested', 'pending', 'accepted'], true)) {
            return response()->json([
                'message' => "Cannot delete delivery with status '{$delivery->status}'",
            ], 422);
        }

        $delivery->delete();

        return $this->success([
            'message' => 'Delivery deleted successfully',
            'delivery_id' => $delivery->id,
        ]);
    }
}
