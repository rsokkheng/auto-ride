<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverMatchingService;
use App\Services\FareService;
use App\Services\FcmService;
use App\Services\FirestoreService;
use App\Services\MovingFareService;
use App\Services\PaymentService;
use App\Mail\TripReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class DeliveryController extends ApiController
{
    public function __construct(
        private DriverMatchingService $matcher,
        private FareService $fare,
        private FcmService $fcm,
        private FirestoreService $firestore,
        private MovingFareService $movingFare,
    ) {}

    // ── List / History ──────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $perPage = min((int) ($request->query('per_page', 10)), 100);
        $status  = $request->query('status');

        if ($user->role === 'driver') {
            $query = Delivery::with(['sender', 'vehicle'])->where('driver_id', $user->id);
        } else {
            $query = Delivery::with(['driver', 'vehicle'])->where('sender_id', $user->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $deliveries = $query->latest()->paginate($perPage)->appends($request->query());

        return $this->success([
            'total'      => $deliveries->total(),
            'deliveries' => $deliveries->items(),
            'pagination' => [
                'total'        => $deliveries->total(),
                'per_page'     => $deliveries->perPage(),
                'current_page' => $deliveries->currentPage(),
                'last_page'    => $deliveries->lastPage(),
            ],
        ]);
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
            ->paginate(10);

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

    public function indexMoving(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $perPage = min((int) ($request->query('per_page', 10)), 100);
        $status  = $request->query('status');

        if ($user->role === 'driver') {
            $query = Delivery::with(['sender', 'vehicle'])
                ->where('driver_id', $user->id)
                ->where('service_type', 'moving');
        } else {
            $query = Delivery::with(['driver', 'vehicle'])
                ->where('sender_id', $user->id)
                ->where('service_type', 'moving');
        }

        if ($status) {
            $query->where('status', $status);
        }

        $movings = $query->orderByDesc('id')->paginate($perPage)->appends($request->query());

        return $this->success([
            'total'   => $movings->total(),
            'movings' => $movings->items(),
            'pagination' => [
                'total'        => $movings->total(),
                'per_page'     => $movings->perPage(),
                'current_page' => $movings->currentPage(),
                'last_page'    => $movings->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $enumGuards = [
            'helper_type'   => ['normal_carry', 'heavy_carry'],
            'payment_model' => ['customer_pays', 'partner_pays', 'split_payment', 'sponsored'],
        ];
        foreach ($enumGuards as $key => $valid) {
            if ($request->exists($key) && ! in_array($request->input($key), $valid, true)) {
                $request->merge([$key => null]);
            }
        }

        $serviceType = $request->input('service_type') ?? 'delivery';

        $data = $request->validate([
            'service_type'      => 'nullable|in:delivery,moving',
            'service_option'    => 'nullable|in:normal,express',
            'sender_name'       => [Rule::requiredIf($serviceType === 'delivery'), 'string', 'max:255'],
            'sender_phone'      => 'nullable|string|max:24',
            'recipient_name'    => [Rule::requiredIf($serviceType === 'delivery'), 'string', 'max:255'],
            'recipient_phone'   => [Rule::requiredIf($serviceType === 'delivery'), 'string', 'max:24'],
            'package_size'      => 'nullable|in:small,medium,large,extra_large',
            'pickup_address'    => 'required|string|max:255',
            'dropoff_address'   => 'nullable|string|max:255',
            'pickup_lat'        => 'nullable|numeric|between:-90,90',
            'pickup_lng'        => 'nullable|numeric|between:-180,180',
            'dropoff_lat'       => 'nullable|numeric|between:-90,90',
            'dropoff_lng'       => 'nullable|numeric|between:-180,180',
            'scheduled_at'      => 'nullable|date',
            'package_details'   => 'nullable|string|max:500',
            'fee'               => 'nullable|numeric|min:0',
            'package_amount'    => 'nullable|integer|min:0',
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

        $hasDropoff = ! empty($data['dropoff_lat']) && ! empty($data['dropoff_lng']);

        // Calculate fee based on service type — automatically when pickup & dropoff coords are provided or via standard route.
        $fee        = (int) ($data['fee'] ?? 0);
        $helperFee  = null;
        $floorFee   = null;

        if ($hasDropoff && ! empty($data['pickup_lat']) && ! empty($data['pickup_lng'])) {
            if ($serviceType === 'moving') {
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
            } elseif ($serviceType === 'delivery') {
                $route = $this->fare->getRoute(
                    (float) $data['pickup_lat'],  (float) $data['pickup_lng'],
                    (float) $data['dropoff_lat'], (float) $data['dropoff_lng'],
                );
                $fareResult = $this->fare->calculateDeliveryFare(
                    $data['package_size'] ?? 'small',
                    $route,
                    (float) $data['pickup_lat'],
                    (float) $data['pickup_lng'],
                    'delivery',
                );
                $fee = $fareResult['total'];
            }
        } elseif ($fee <= 0) {
            $pLat = ! empty($data['pickup_lat']) ? (float) $data['pickup_lat'] : 11.5564;
            $pLng = ! empty($data['pickup_lng']) ? (float) $data['pickup_lng'] : 104.9282;
            $dLat = ! empty($data['dropoff_lat']) ? (float) $data['dropoff_lat'] : 11.5700;
            $dLng = ! empty($data['dropoff_lng']) ? (float) $data['dropoff_lng'] : 104.9350;

            if ($serviceType === 'moving') {
                $fareResult = $this->movingFare->estimate(
                    $pLat,  $pLng,
                    $dLat, $dLng,
                    (int) ($data['floor_pickup']     ?? 0),
                    (int) ($data['floor_dropoff']    ?? 0),
                    (bool) ($data['has_elevator']    ?? false),
                    (int) ($data['requires_helpers'] ?? 0),
                    $data['helper_type'] ?? 'normal_carry',
                );
                $fee       = $fareResult['total'];
                $helperFee = $fareResult['helper_fee'];
                $floorFee  = $fareResult['floor_fee'];
            } elseif ($serviceType === 'delivery') {
                $route = $this->fare->getRoute($pLat, $pLng, $dLat, $dLng);
                $fareResult = $this->fare->calculateDeliveryFare(
                    $data['package_size'] ?? 'small',
                    $route,
                    $pLat,
                    $pLng,
                    'delivery',
                );
                $fee = $fareResult['total'];
            }
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
            'sender_name'       => $data['sender_name'] ?? $user->name,
            'sender_phone'      => $data['sender_phone'] ?? null,
            'recipient_name'    => $data['recipient_name'] ?? null,
            'recipient_phone'   => $data['recipient_phone'] ?? null,
            'package_size'      => $data['package_size'] ?? null,
            'pickup_address'    => $data['pickup_address'],
            'dropoff_address'   => $data['dropoff_address'] ?? null,
            'pickup_lat'        => $data['pickup_lat'] ?? null,
            'pickup_lng'        => $data['pickup_lng'] ?? null,
            'dropoff_lat'       => $hasDropoff ? (float) $data['dropoff_lat'] : null,
            'dropoff_lng'       => $hasDropoff ? (float) $data['dropoff_lng'] : null,
            'scheduled_at'      => $data['scheduled_at'] ?? null,
            'package_details'   => $data['package_details'] ?? '',
            'notes'             => $data['notes'] ?? null,
            'status'            => 'requested',
            'fee'               => $fee,
            'package_amount'    => (int) ($data['package_amount'] ?? 0),
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

        $delivery->load('sender', 'driver', 'vehicle');
        $this->firestore->syncDelivery($delivery);

        try {
            $nearbyDrivers = User::where('role', 'driver')
                ->where('available', true)
                ->whereNotNull('fcm_token')
                ->get();
            $dropoffLabel = $delivery->dropoff_address ?? 'Destination TBD';
            $this->fcm->sendToUsers(
                $nearbyDrivers->all(),
                '📦 New Delivery Request',
                "{$delivery->pickup_address} → {$dropoffLabel}",
                ['type' => 'delivery_requested', 'delivery_id' => (string) $delivery->id]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->success(['delivery' => $delivery], 201);
    }

    // ── Accept ──────────────────────────────────────────────────────────────

    public function accept(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        // Accept open deliveries (regular) or partner-assigned deliveries.
        $acceptableStatuses = ['requested', 'pending', 'assigned'];
        if (! in_array($delivery->status, $acceptableStatuses, true)) {
            return response()->json([
                'message' => "Delivery cannot be accepted — current status is \"{$delivery->status}\".",
            ], 422);
        }

        // For partner-assigned deliveries, only the assigned driver can accept.
        if ($delivery->status === 'assigned' && $delivery->driver_id !== $user->id) {
            return response()->json(['message' => 'This delivery is assigned to a different driver.'], 403);
        }

        // For open deliveries, block if another driver already claimed it.
        if (in_array($delivery->status, ['requested', 'pending']) && $delivery->driver_id && $delivery->driver_id !== $user->id) {
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

        // Notify sender: driver assigned
        if ($fresh->sender) {
            $this->fcm->deliveryAccepted($fresh->sender, $fresh->id, $fresh->driver->name ?? 'Your driver');
        }

        return $this->success([
            'delivery' => $fresh,
            'message'  => 'Delivery accepted. Head to pickup location.',
        ]);
    }

    // ── Start (picked up / in progress) ────────────────────────────────────

    /**
     * POST /v1/deliveries/{id}/start
     * POST /v1/movings/{id}/start
     * Driver has picked up the package / started the moving job.
     */
    public function start(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $delivery->driver_id !== $user->id) {
            return $this->unauthorized();
        }

        // Idempotent — already picked up (either flow) or finished
        if ($delivery->isInTransit() || $delivery->isFinished()) {
            return $this->success([
                'delivery' => $delivery->load('sender', 'driver', 'vehicle'),
                'message'  => 'Already started.',
            ]);
        }

        if ($delivery->status !== 'accepted') {
            return response()->json([
                'data'    => null,
                'message' => "Cannot start — status is \"{$delivery->status}\". Must be accepted first.",
            ], 422);
        }

        $delivery->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        $fresh = $delivery->fresh()->load('sender', 'driver', 'vehicle');
        $this->firestore->syncDelivery($fresh);

        try {
            if ($fresh->sender) {
                $label = $fresh->service_type === 'moving' ? 'Moving job' : 'Delivery';
                $this->fcm->sendToUser(
                    $fresh->sender,
                    "{$label} Started",
                    "Your driver has picked up and is on the way.",
                    ['type' => 'delivery_started', 'delivery_id' => (string) $fresh->id]
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->success([
            'delivery' => $fresh,
            'message'  => 'Started. On the way to destination.',
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
            'package_size'      => 'nullable|in:small,medium,large,extra_large',
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
            'delivery',
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

    // ── QR Code Scan ────────────────────────────────────────────────────────

    /**
     * POST /v1/deliveries/scan-qr
     * Driver scans QR code to confirm pickup or delivery.
     * Body: qr_token (string scanned from QR code)
     *
     * Transitions:
     *   accepted  → picked_up  (pickup scan)
     *   in_transit → delivered  (delivery scan)
     */
    public function scanQr(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $data = $request->validate([
            'qr_token' => 'required|string',
        ]);

        // Strip prefix if app sends full payload "AUTORIDE:DELIVERY:{token}"
        $token = preg_replace('/^AUTORIDE:DELIVERY:/i', '', trim($data['qr_token']));

        $delivery = Delivery::where('qr_token', $token)->first();

        if (! $delivery) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code. Package not found.',
            ], 404);
        }

        if ($delivery->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'This package is not assigned to you.',
            ], 403);
        }

        // Pickup scan: accepted → picked_up
        if ($delivery->status === 'accepted') {
            $delivery->update([
                'status'            => 'picked_up',
                'pickup_scanned_at' => now(),
                'started_at'        => now(),
            ]);

            $this->firestore->syncDelivery($delivery->fresh()->load('sender', 'driver'));

            return $this->success([
                'action'   => 'pickup_confirmed',
                'message'  => 'Pickup confirmed. Proceed to delivery address.',
                'delivery' => $delivery->fresh()->only(['id', 'status', 'dropoff_address', 'dropoff_lat', 'dropoff_lng', 'recipient_name', 'recipient_phone']),
            ]);
        }

        // In-transit scan: picked_up → in_transit (optional intermediate)
        if ($delivery->status === 'picked_up') {
            $delivery->update(['status' => 'in_transit']);
            $this->firestore->syncDelivery($delivery->fresh()->load('sender', 'driver'));

            return $this->success([
                'action'   => 'in_transit',
                'message'  => 'Status updated to In Transit.',
                'delivery' => $delivery->fresh()->only(['id', 'status', 'dropoff_address', 'recipient_name', 'recipient_phone']),
            ]);
        }

        // Delivery scan: in_transit → delivered
        if ($delivery->status === 'in_transit') {
            $delivery->update([
                'status'              => 'delivered',
                'delivery_scanned_at' => now(),
                'completed_at'        => now(),
            ]);

            $fresh = $delivery->fresh()->load('sender', 'driver');
            $this->firestore->syncDelivery($fresh);

            // Auto-complete and settle payment
            try {
                app(\App\Services\PaymentService::class)->settleDelivery($fresh);
                $fresh->refresh();
            } catch (\Throwable $e) { report($e); }

            // Notify partner/sender
            if ($fresh->sender) {
                try {
                    $this->fcm->sendToUser($fresh->sender, 'Package Delivered', 'Your package has been delivered successfully.', [
                        'type'        => 'delivery_delivered',
                        'delivery_id' => (string) $fresh->id,
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $this->success([
                'action'   => 'delivery_confirmed',
                'message'  => 'Delivery confirmed. Order completed.',
                'delivery' => $fresh->only(['id', 'status', 'completed_at', 'payment_status']),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'QR scan not applicable at current status: ' . $delivery->status,
        ], 422);
    }

    // ── Cancel ──────────────────────────────────────────────────────────────

    public function cancel(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$delivery->sender_id, $delivery->driver_id], true)) {
            return $this->unauthorized();
        }

        // Terminal statuses include "delivered" (partner/QR flow), not just "completed".
        if ($delivery->isTerminal()) {
            return response()->json(['message' => 'Delivery cannot be cancelled'], 422);
        }

        $isByDriver = $user->role === 'driver';
        $delivery->update(['status' => 'cancelled']);
        $fresh = $delivery->fresh()->load('sender', 'driver');
        $this->firestore->syncDelivery($fresh);

        // Notify the other party
        if ($isByDriver && $fresh->sender) {
            $this->fcm->deliveryCancelled($fresh->sender, $fresh->id, 'driver');
        } elseif (! $isByDriver && $fresh->driver) {
            $this->fcm->deliveryCancelled($fresh->driver, $fresh->id, 'sender');
        }

        return $this->success(['delivery' => $fresh]);
    }

    // ── Confirm / Complete ──────────────────────────────────────────────────

    public function confirm(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || $delivery->sender_id !== $user->id) {
            return $this->unauthorized();
        }

        $delivery->update(['status' => 'completed', 'completed_at' => $delivery->completed_at ?? now()]);

        // Create transaction record and settle payment (no-op if already paid).
        $transaction = app(PaymentService::class)->settleDelivery($delivery->fresh());

        $fresh = $delivery->fresh()->load('sender', 'driver');
        $this->firestore->syncDelivery($fresh);

        // Notify sender: delivery completed
        if ($fresh->sender) {
            $this->fcm->deliveryCompleted($fresh->sender, $fresh->id);
        }

        return $this->success([
            'delivery'    => $fresh,
            'transaction' => $transaction,
        ]);
    }

    public function complete(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $isDriver = $user->role === 'driver' && $delivery->driver_id === $user->id;
        $isSender = $delivery->sender_id === $user->id;

        if (! $isDriver && ! $isSender) {
            return $this->unauthorized();
        }

        // Idempotent — already completed
        if ($delivery->status === 'completed') {
            return $this->success([
                'delivery' => $delivery->load('sender', 'driver', 'vehicle'),
                'message'  => 'Already completed.',
            ]);
        }

        if ($delivery->status === 'cancelled') {
            return response()->json(['data' => null, 'message' => 'Cannot complete a cancelled job.'], 422);
        }

        $completionData = $request->validate([
            'dropoff_address' => 'nullable|string|max:255',
            'dropoff_lat'     => 'nullable|numeric|between:-90,90',
            'dropoff_lng'     => 'nullable|numeric|between:-180,180',
            'final_fee'       => 'nullable|integer|min:0',
        ]);

        $updates = ['status' => 'completed', 'completed_at' => now()];

        // For bookings without a pre-set destination: driver provides final dropoff
        if (is_null($delivery->dropoff_address)) {
            if (! empty($completionData['dropoff_address'])) {
                $updates['dropoff_address'] = $completionData['dropoff_address'];
            }
            if (! empty($completionData['dropoff_lat'])) {
                $updates['dropoff_lat'] = (float) $completionData['dropoff_lat'];
                $updates['dropoff_lng'] = (float) $completionData['dropoff_lng'];
            }
            if (isset($completionData['final_fee'])) {
                $updates['fee'] = (int) $completionData['final_fee'];
            } elseif (! empty($completionData['dropoff_lat']) && ! empty($delivery->pickup_lat)) {
                // Auto-calculate fee from actual route if no explicit fee given
                if ($delivery->service_type === 'moving') {
                    $fareResult = $this->movingFare->estimate(
                        (float) $delivery->pickup_lat, (float) $delivery->pickup_lng,
                        (float) $completionData['dropoff_lat'], (float) $completionData['dropoff_lng'],
                        (int) ($delivery->floor_pickup  ?? 0),
                        (int) ($delivery->floor_dropoff ?? 0),
                        (bool) ($delivery->has_elevator ?? false),
                        (int) ($delivery->requires_helpers ?? 0),
                        $delivery->helper_type ?? 'normal_carry',
                    );
                    $updates['fee'] = $fareResult['total'];
                } else {
                    $route = $this->fare->getRoute(
                        (float) $delivery->pickup_lat, (float) $delivery->pickup_lng,
                        (float) $completionData['dropoff_lat'], (float) $completionData['dropoff_lng'],
                    );
                    $fareResult = $this->fare->calculateDeliveryFare(
                        $delivery->package_size ?? 'small', $route,
                        (float) $delivery->pickup_lat, (float) $delivery->pickup_lng,
                        'delivery',
                    );
                    $updates['fee'] = $fareResult['total'];
                }
            }
        }

        $delivery->update($updates);

        $fresh = $delivery->fresh()->load('sender', 'driver', 'vehicle');

        // Settle payment and credit the driver wallet. settleDelivery() is a no-op
        // when the job was already paid — a partner order that reached "delivered"
        // via QR scan settled there, and paying again would double-credit the driver.
        try {
            app(\App\Services\PaymentService::class)->settleDelivery($fresh);
            $fresh->refresh();
        } catch (\Throwable $e) {
            report($e);
        }

        $this->firestore->syncDelivery($fresh);

        try {
            if ($fresh->sender) {
                $this->fcm->deliveryCompleted($fresh->sender, $fresh->id);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Email receipt to sender
        if ($fresh->sender?->email) {
            try {
                Mail::to($fresh->sender->email)->queue(TripReceipt::fromDelivery($fresh));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->success([
            'delivery' => $fresh,
            'message'  => 'Job completed successfully.',
        ]);
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

        // "delivered" (partner/QR flow) counts as finished — otherwise those
        // orders could never be rated.
        if (! $delivery->isFinished()) {
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

        // Only allow updates while the order is still unclaimed (includes the
        // partner flow's "created").
        if (! in_array($delivery->status, Delivery::OPEN_STATUSES, true)) {
            return response()->json([
                'message' => "Cannot update delivery with status '{$delivery->status}'",
            ], 422);
        }

        $data = $request->validate([
            'sender_name'       => 'nullable|string|max:255',
            'sender_phone'      => 'nullable|string|max:24',
            'service_option'    => 'nullable|in:normal,express',
            'recipient_name'    => 'nullable|string|max:255',
            'recipient_phone'   => 'nullable|string|max:24',
            'package_size'      => 'nullable|in:small,medium,large,extra_large',
            'package_amount'    => 'nullable|integer|min:0',
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

        // Recalculate fee if route or service attributes changed
        $merged = array_merge($delivery->toArray(), $updateData);
        $pLat = $merged['pickup_lat'] ?? null;
        $pLng = $merged['pickup_lng'] ?? null;
        $dLat = $merged['dropoff_lat'] ?? null;
        $dLng = $merged['dropoff_lng'] ?? null;

        if (! empty($pLat) && ! empty($pLng) && ! empty($dLat) && ! empty($dLng)) {
            $serviceType = $merged['service_type'] ?? 'delivery';
            if ($serviceType === 'delivery') {
                $route = $this->fare->getRoute((float) $pLat, (float) $pLng, (float) $dLat, (float) $dLng);
                $fareResult = $this->fare->calculateDeliveryFare($merged['package_size'] ?? 'small', $route, (float) $pLat, (float) $pLng, 'delivery');
                $fee = $fareResult['total'];
                $serviceOption = $merged['service_option'] ?? 'normal';
                if ($serviceOption === 'express') {
                    $multiplier = (float) \App\Models\PricingSetting::get('delivery_express_multiplier', config('delivery.express_multiplier', 1.25));
                    $fee = (int) ceil(($fee * $multiplier) / 100) * 100;
                    $updateData['express_multiplier'] = $multiplier;
                }
                $updateData['fee'] = $fee;
            }
        }

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

        // Only allow deletion before the package has been collected
        if (! in_array($delivery->status, Delivery::PRE_PICKUP_STATUSES, true)) {
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
