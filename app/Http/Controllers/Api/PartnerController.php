<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\User;
use App\Services\DriverMatchingService;
use App\Services\FirestoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PartnerController extends ApiController
{
    public function __construct(
        private DriverMatchingService $matcher,
        private FirestoreService      $firestore,
    ) {}

    // ── Create delivery order ─────────────────────────────────────────────────

    /**
     * POST /v1/partner/deliveries
     * Partner creates a delivery order. QR token is generated automatically.
     */
    public function store(Request $request)
    {
        $partner = $this->authUser($request);
        if (! $partner || $partner->role !== 'partner') return $this->unauthorized();

        $data = $request->validate([
            'recipient_name'    => 'required|string|max:255',
            'recipient_phone'   => 'required|string|max:24',
            'pickup_address'    => 'required|string|max:500',
            'dropoff_address'   => 'required|string|max:500',
            'pickup_lat'        => 'required|numeric',
            'pickup_lng'        => 'required|numeric',
            'dropoff_lat'       => 'required|numeric',
            'dropoff_lng'       => 'required|numeric',
            'package_size'      => 'nullable|in:small,medium,large,extra_large',
            'package_details'   => 'nullable|string|max:1000',
            'fee'               => 'required|integer|min:0',
            'payment_method'    => 'nullable|in:cash,wallet,aba,wing,other_online',
            'payment_by'        => 'nullable|in:sender,recipient',
            'notes'             => 'nullable|string|max:500',
            'partner_reference' => 'nullable|string|max:100',
            'auto_assign'       => 'nullable|boolean',
        ]);

        $qrToken = Str::random(32);

        $delivery = Delivery::create([
            'partner_id'        => $partner->id,
            'sender_id'         => $partner->id,
            'sender_name'       => $partner->name,
            'sender_phone'      => $partner->phone,
            'recipient_name'    => $data['recipient_name'],
            'recipient_phone'   => $data['recipient_phone'],
            'pickup_address'    => $data['pickup_address'],
            'dropoff_address'   => $data['dropoff_address'],
            'pickup_lat'        => $data['pickup_lat'],
            'pickup_lng'        => $data['pickup_lng'],
            'dropoff_lat'       => $data['dropoff_lat'],
            'dropoff_lng'       => $data['dropoff_lng'],
            'package_size'      => $data['package_size'] ?? 'medium',
            'package_details'   => $data['package_details'] ?? null,
            'fee'               => $data['fee'],
            'payment_method'    => $data['payment_method'] ?? 'cash',
            'payment_by'        => $data['payment_by'] ?? 'sender',
            'payment_status'    => 'unpaid',
            'notes'             => $data['notes'] ?? null,
            'partner_reference' => $data['partner_reference'] ?? null,
            'service_type'      => 'delivery',
            'status'            => 'created',
            'qr_token'          => $qrToken,
        ]);

        // Auto-assign nearest driver if requested
        if ($request->boolean('auto_assign')) {
            $this->autoAssign($delivery);
            $delivery->refresh();
        }

        return $this->success([
            'message'  => 'Delivery order created.',
            'delivery' => $this->formatDelivery($delivery),
            'qr_code'  => [
                'token'   => $qrToken,
                'payload' => 'AUTORIDE:DELIVERY:' . $qrToken,
            ],
        ], 201);
    }

    // ── List partner's orders ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $partner = $this->authUser($request);
        if (! $partner || $partner->role !== 'partner') return $this->unauthorized();

        $status  = $request->query('status');
        $perPage = min((int) $request->query('per_page', 10), 100);

        $query = Delivery::with(['driver:id,name,phone,avatar,rating', 'vehicle'])
            ->where('partner_id', $partner->id)
            ->orderByDesc('id');

        if ($status) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        return $this->success([
            'deliveries' => $paginator->items(),
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    // ── Show single order ─────────────────────────────────────────────────────

    public function show(Request $request, Delivery $delivery)
    {
        $partner = $this->authUser($request);
        if (! $partner || $partner->role !== 'partner') return $this->unauthorized();
        if ($delivery->partner_id !== $partner->id) return $this->unauthorized();

        $delivery->load(['driver:id,name,phone,avatar,rating,current_latitude,current_longitude', 'vehicle']);

        return $this->success(['delivery' => $this->formatDelivery($delivery)]);
    }

    // ── Manual driver assignment ──────────────────────────────────────────────

    /**
     * POST /v1/partner/deliveries/{delivery}/assign
     * Body: driver_id
     */
    public function assign(Request $request, Delivery $delivery)
    {
        $partner = $this->authUser($request);
        if (! $partner || $partner->role !== 'partner') return $this->unauthorized();
        if ($delivery->partner_id !== $partner->id) return $this->unauthorized();

        if (! in_array($delivery->status, ['created', 'assigned'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot assign driver — order status is "' . $delivery->status . '".',
            ], 422);
        }

        $data = $request->validate([
            'driver_id' => 'required|integer|exists:users,id',
        ]);

        $driver = User::where('id', $data['driver_id'])->where('role', 'driver')->first();
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 422);
        }

        $delivery->update([
            'driver_id'       => $driver->id,
            'status'          => 'assigned',
            'assigned_at'     => now(),
            'assignment_type' => 'manual',
        ]);

        return $this->success([
            'message'  => "Order assigned to {$driver->name}.",
            'delivery' => $this->formatDelivery($delivery->fresh(['driver'])),
        ]);
    }

    // ── Auto-assign nearest driver ────────────────────────────────────────────

    /**
     * POST /v1/partner/deliveries/{delivery}/auto-assign
     */
    public function autoAssignRoute(Request $request, Delivery $delivery)
    {
        $partner = $this->authUser($request);
        if (! $partner || $partner->role !== 'partner') return $this->unauthorized();
        if ($delivery->partner_id !== $partner->id) return $this->unauthorized();

        if (! in_array($delivery->status, ['created', 'assigned'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot auto-assign — order status is "' . $delivery->status . '".',
            ], 422);
        }

        $driver = $this->autoAssign($delivery);
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No available drivers nearby. Try again shortly.',
            ], 422);
        }

        return $this->success([
            'message'  => "Order auto-assigned to {$driver->name}.",
            'delivery' => $this->formatDelivery($delivery->fresh(['driver'])),
        ]);
    }

    // ── Cancel order ──────────────────────────────────────────────────────────

    public function cancel(Request $request, Delivery $delivery)
    {
        $partner = $this->authUser($request);
        if (! $partner || $partner->role !== 'partner') return $this->unauthorized();
        if ($delivery->partner_id !== $partner->id) return $this->unauthorized();

        if ($delivery->isInTransit() || $delivery->isFinished()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel — package is already in transit.',
            ], 422);
        }

        $delivery->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->input('reason'),
        ]);

        return $this->success(['message' => 'Order cancelled.']);
    }

    // ── Nearby drivers (for manual assignment UI) ─────────────────────────────

    public function nearbyDrivers(Request $request, Delivery $delivery)
    {
        $partner = $this->authUser($request);
        if (! $partner || $partner->role !== 'partner') return $this->unauthorized();
        if ($delivery->partner_id !== $partner->id) return $this->unauthorized();

        $drivers = $this->matcher->findDrivers(
            (float) $delivery->pickup_lat,
            (float) $delivery->pickup_lng,
            20
        );

        return $this->success([
            'drivers' => $drivers->map(fn($d) => [
                'id'          => $d->id,
                'name'        => $d->name,
                'phone'       => $d->phone,
                'rating'      => $d->rating,
                'distance_km' => $d->distance_km,
                'eta_minutes' => $d->eta_minutes,
                'vehicle'     => $d->vehicles->first()?->only(['type', 'plate_number', 'brand', 'model']),
            ]),
        ]);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function autoAssign(Delivery $delivery): ?User
    {
        $drivers = $this->matcher->findDrivers(
            (float) $delivery->pickup_lat,
            (float) $delivery->pickup_lng,
            1
        );

        $driver = $drivers->first();
        if (! $driver) return null;

        $delivery->update([
            'driver_id'       => $driver->id,
            'status'          => 'assigned',
            'assigned_at'     => now(),
            'assignment_type' => 'auto',
        ]);

        return $driver;
    }

    private function formatDelivery(Delivery $d): array
    {
        return [
            'id'                  => $d->id,
            'status'              => $d->status,
            'partner_reference'   => $d->partner_reference,
            'recipient_name'      => $d->recipient_name,
            'recipient_phone'     => $d->recipient_phone,
            'pickup_address'      => $d->pickup_address,
            'dropoff_address'     => $d->dropoff_address,
            'pickup_lat'          => $d->pickup_lat,
            'pickup_lng'          => $d->pickup_lng,
            'dropoff_lat'         => $d->dropoff_lat,
            'dropoff_lng'         => $d->dropoff_lng,
            'package_size'        => $d->package_size,
            'package_details'     => $d->package_details,
            'fee'                 => $d->fee,
            'payment_method'      => $d->payment_method,
            'payment_status'      => $d->payment_status,
            'notes'               => $d->notes,
            'assignment_type'     => $d->assignment_type,
            'qr_token'            => $d->qr_token,
            'qr_payload'          => $d->qr_token ? 'AUTORIDE:DELIVERY:' . $d->qr_token : null,
            'driver'              => $d->driver ? [
                'id'          => $d->driver->id,
                'name'        => $d->driver->name,
                'phone'       => $d->driver->phone,
                'rating'      => $d->driver->rating,
                'latitude'    => $d->driver->current_latitude,
                'longitude'   => $d->driver->current_longitude,
            ] : null,
            'pickup_scanned_at'   => $d->pickup_scanned_at?->toDateTimeString(),
            'delivery_scanned_at' => $d->delivery_scanned_at?->toDateTimeString(),
            'assigned_at'         => $d->assigned_at?->toDateTimeString(),
            'started_at'          => $d->started_at?->toDateTimeString(),
            'completed_at'        => $d->completed_at?->toDateTimeString(),
            'created_at'          => $d->created_at->toDateTimeString(),
        ];
    }
}
