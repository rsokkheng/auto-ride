<?php

namespace App\Http\Controllers\Api;

use App\Models\CarRental;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RentalController extends ApiController
{
    /**
     * GET /v1/rentals/catalog
     * Browse available vehicle types and pricing — no auth required.
     */
    public function catalog(Request $request)
    {
        $catalog = [
            [
                'vehicle_type'     => 'motorcycle',
                'label'            => 'Motorcycle',
                'seats'            => 1,
                'daily_rate_usd'   => $this->dailyRateUsd('motorcycle'),
                'description'      => 'Lightweight & fuel-efficient for city trips',
                'icon'             => 'motorcycle',
            ],
            [
                'vehicle_type'     => 'tuk_tuk',
                'label'            => 'Tuk Tuk',
                'seats'            => 3,
                'daily_rate_usd'   => $this->dailyRateUsd('tuk_tuk'),
                'description'      => 'Classic Cambodian 3-wheeler for short rides',
                'icon'             => 'tuk_tuk',
            ],
            [
                'vehicle_type'     => 'electric',
                'label'            => 'Electric Car',
                'seats'            => 4,
                'daily_rate_usd'   => $this->dailyRateUsd('electric'),
                'description'      => 'Eco-friendly electric vehicle',
                'icon'             => 'electric',
            ],
            [
                'vehicle_type'     => 'sedan',
                'label'            => 'Sedan',
                'seats'            => 4,
                'daily_rate_usd'   => $this->dailyRateUsd('sedan'),
                'description'      => 'Comfortable sedan for city and highway',
                'icon'             => 'sedan',
            ],
            [
                'vehicle_type'     => 'suv',
                'label'            => 'SUV',
                'seats'            => 7,
                'daily_rate_usd'   => $this->dailyRateUsd('suv'),
                'description'      => 'Spacious SUV for families and groups',
                'icon'             => 'suv',
            ],
            [
                'vehicle_type'     => 'van',
                'label'            => 'Van',
                'seats'            => 12,
                'daily_rate_usd'   => $this->dailyRateUsd('van'),
                'description'      => 'Large van for group travel',
                'icon'             => 'van',
            ],
            [
                'vehicle_type'     => 'truck',
                'label'            => 'Truck',
                'seats'            => 2,
                'daily_rate_usd'   => $this->dailyRateUsd('truck'),
                'description'      => 'Heavy-duty truck for cargo',
                'icon'             => 'truck',
            ],
        ];

        // Optional: estimate total if dates provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = \Carbon\Carbon::parse($request->start_date);
            $end   = \Carbon\Carbon::parse($request->end_date);
            $days  = max(1, $start->diffInDays($end) + 1);

            $catalog = array_map(function ($item) use ($days) {
                $item['days']           = $days;
                $item['total_usd']      = round($item['daily_rate_usd'] * $days, 2);
                return $item;
            }, $catalog);
        }

        return $this->success(['catalog' => $catalog]);
    }

    /**
     * GET /v1/rentals/{rental}
     * Single booking detail.
     */
    public function show(Request $request, CarRental $rental)
    {
        $user = $this->authUser($request);
        if (! $user || $rental->user_id !== $user->id) return $this->unauthorized();

        return $this->success(['rental' => $this->formatRental($rental)]);
    }

    /**
     * POST /v1/rentals
     * Book a car rental.
     * Body: vehicle_type, pickup_location, pickup_lat?, pickup_lng?,
     *       start_date (YYYY-MM-DD), end_date (YYYY-MM-DD),
     *       payment_method?, notes?
     */
    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'marketplace_product_id' => 'nullable|exists:marketplace_products,id',
            'vehicle_type'           => 'required|string|max:50',
            'pickup_location'        => 'required|string|max:255',
            'pickup_lat'             => 'nullable|numeric|between:-90,90',
            'pickup_lng'             => 'nullable|numeric|between:-180,180',
            'start_date'             => 'required|date|after_or_equal:today',
            'end_date'               => 'required|date|after_or_equal:start_date',
            'payment_method'         => 'nullable|in:cash,wallet,aba,wing,other_online',
            'notes'                  => 'nullable|string|max:500',
        ]);

        $start     = Carbon::parse($data['start_date']);
        $end       = Carbon::parse($data['end_date']);
        $totalDays = max(1, $start->diffInDays($end) + 1);

        $dailyRateKhr = $this->dailyRate($data['vehicle_type']);
        $totalKhr     = $dailyRateKhr * $totalDays;

        $rental = CarRental::create([
            'user_id'                => $user->id,
            'marketplace_product_id' => $data['marketplace_product_id'] ?? null,
            'vehicle_type'           => $data['vehicle_type'],
            'pickup_location'        => $data['pickup_location'],
            'pickup_lat'             => $data['pickup_lat'] ?? null,
            'pickup_lng'             => $data['pickup_lng'] ?? null,
            'start_date'             => $data['start_date'],
            'end_date'               => $data['end_date'],
            'total_days'             => $totalDays,
            'daily_rate_khr'         => $dailyRateKhr,
            'total_amount_khr'       => $totalKhr,
            'payment_method'         => $data['payment_method'] ?? 'cash',
            'notes'                  => $data['notes'] ?? null,
            'status'                 => 'pending',
        ]);

        return $this->success([
            'rental'  => $this->formatRental($rental),
            'message' => 'Rental request submitted. We will confirm within 1 hour.',
        ], 201);
    }

    /**
     * GET /v1/rentals
     * List the authenticated user's rentals.
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $rentals = CarRental::where('user_id', $user->id)
            ->latest()
            ->paginate(15)
            ->through(fn($r) => $this->formatRental($r));

        return $this->success([
            'rentals'    => $rentals->items(),
            'pagination' => [
                'total'        => $rentals->total(),
                'current_page' => $rentals->currentPage(),
                'last_page'    => $rentals->lastPage(),
            ],
        ]);
    }

    /**
     * POST /v1/rentals/{rental}/cancel
     * User cancels their own pending booking.
     */
    public function cancel(Request $request, CarRental $rental)
    {
        $user = $this->authUser($request);
        if (! $user || $rental->user_id !== $user->id) return $this->unauthorized();

        if (in_array($rental->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Booking cannot be cancelled'], 422);
        }

        $rental->update(['status' => 'cancelled']);
        return $this->success(['rental' => $this->formatRental($rental->fresh())]);
    }

    /**
     * POST /v1/rentals/{rental}/confirm
     * Admin confirms a pending booking.
     */
    public function confirm(Request $request, CarRental $rental)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'admin') return $this->unauthorized();

        if ($rental->status !== 'pending') {
            return response()->json(['message' => 'Only pending bookings can be confirmed'], 422);
        }

        $rental->update(['status' => 'confirmed']);
        return $this->success(['rental' => $this->formatRental($rental->fresh())]);
    }

    /**
     * DELETE /v1/rentals/{rental}
     * User deletes a cancelled booking from their history.
     */
    public function destroy(Request $request, CarRental $rental)
    {
        $user = $this->authUser($request);
        if (! $user || $rental->user_id !== $user->id) return $this->unauthorized();

        if (! in_array($rental->status, ['cancelled', 'completed'])) {
            return response()->json(['message' => 'Only cancelled or completed bookings can be deleted'], 422);
        }

        $rental->delete();
        return $this->success(['message' => 'Booking deleted']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function dailyRateUsd(string $vehicleType): float
    {
        return match ($vehicleType) {
            'motorcycle' => 5.00,
            'tuk_tuk'   => 7.00,
            'electric'  => 9.00,
            'sedan'     => 10.00,
            'suv'       => 15.00,
            'van'       => 18.00,
            'truck'     => 25.00,
            default     => 10.00,
        };
    }

    private function dailyRate(string $vehicleType): int
    {
        return (int) round($this->dailyRateUsd($vehicleType) * 4000);
    }

    private function formatRental(CarRental $r): array
    {
        $dailyUsd = $this->dailyRateUsd($r->vehicle_type);
        $totalUsd = round($dailyUsd * $r->total_days, 2);

        $r->loadMissing(['user', 'marketplaceProduct.images']);

        $product = $r->marketplaceProduct;

        return [
            'rental_id'              => $r->id,
            'marketplace_product_id' => $r->marketplace_product_id,
            'vehicle_type'           => $r->vehicle_type,
            'pickup_location'        => $r->pickup_location,
            'pickup_lat'             => $r->pickup_lat,
            'pickup_lng'             => $r->pickup_lng,
            'start_date'             => $r->start_date->toDateString(),
            'end_date'               => $r->end_date->toDateString(),
            'total_days'             => $r->total_days,
            'daily_rate_usd'         => $dailyUsd,
            'total_amount_usd'       => $totalUsd,
            'payment_method'         => $r->payment_method,
            'notes'                  => $r->notes,
            'status'                 => $r->status,
            'created_at'             => $r->created_at->toDateTimeString(),
            'user' => $r->user ? [
                'id'    => $r->user->id,
                'name'  => $r->user->name,
                'phone' => $r->user->phone,
            ] : null,
            'product' => $product ? [
                'id'                 => $product->id,
                'title'              => $product->title,
                'listing_type'       => $product->listing_type,
                'condition'          => $product->condition,
                'rent_price_per_day' => (float) $product->rent_price_per_day,
                'image'              => $product->images->first()?->full_url,
            ] : null,
        ];
    }
}
