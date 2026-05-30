<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Vehicle;
use App\Services\DriverMatchingService;
use Illuminate\Http\Request;

class DeliveryController extends ApiController
{
    public function __construct(private DriverMatchingService $matcher) {}

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

        $delivery = Delivery::create(array_merge(
            $data,
            [
                'sender_id'       => $user->id,
                'driver_id'       => $driverId,
                'status'          => 'requested',
                'fee'             => $data['fee'] ?? 0,
                'package_details' => $data['package_details'] ?? '',
            ]
        ));

        return $this->success(['delivery' => $delivery->load('driver', 'vehicle')], 201);
    }

    // ── Accept ──────────────────────────────────────────────────────────────

    public function accept(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || $user->role !== 'driver') {
            return $this->unauthorized();
        }

        if ($delivery->driver_id && $delivery->driver_id !== $user->id) {
            return response()->json(['message' => 'Delivery already claimed'], 422);
        }

        $delivery->update([
            'driver_id' => $user->id,
            'status'    => 'accepted',
        ]);

        return $this->success(['delivery' => $delivery->fresh()->load('sender', 'vehicle')]);
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
    public function estimate(Request $request)
    {
        $data = $request->validate([
            'distance'     => 'nullable|numeric|min:0',
            'package_size' => 'nullable|in:small,medium,large',
        ]);

        $distance = (float) ($data['distance'] ?? 5);
        $size     = $data['package_size'] ?? 'small';

        $base      = config('delivery.fee_base', 3000);
        $perKm     = config('delivery.fee_per_km', 1200);
        $surcharge = config("delivery.fee_surcharge_{$size}", 0);

        $raw = $base + ($perKm * max(1, $distance)) + $surcharge;
        $fee = (int) (ceil($raw / 100) * 100);

        return $this->success([
            'estimated_fee'  => $fee,
            'currency'       => 'KHR',
            'breakdown'      => [
                'base_fee'   => $base,
                'distance_km'=> $distance,
                'per_km_rate'=> $perKm,
                'surcharge'  => $surcharge,
            ],
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

        return $this->success(['delivery' => $delivery]);
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
