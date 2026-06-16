<?php

namespace App\Http\Controllers\Api;

use App\Models\CarRental;
use App\Models\PricingSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RentalController extends ApiController
{
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
            'vehicle_type'    => 'required|in:sedan,suv,van,motorcycle,truck,tuk_tuk,electric',
            'pickup_location' => 'required|string|max:255',
            'pickup_lat'      => 'nullable|numeric|between:-90,90',
            'pickup_lng'      => 'nullable|numeric|between:-180,180',
            'start_date'      => 'required|date|after_or_equal:today',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'payment_method'  => 'nullable|in:cash,wallet,aba,wing,other_online',
            'notes'           => 'nullable|string|max:500',
        ]);

        $start     = Carbon::parse($data['start_date']);
        $end       = Carbon::parse($data['end_date']);
        $totalDays = max(1, $start->diffInDays($end) + 1);

        $dailyRate = $this->dailyRate($data['vehicle_type']);
        $total     = $dailyRate * $totalDays;

        $rental = CarRental::create([
            'user_id'          => $user->id,
            'vehicle_type'     => $data['vehicle_type'],
            'pickup_location'  => $data['pickup_location'],
            'pickup_lat'       => $data['pickup_lat'] ?? null,
            'pickup_lng'       => $data['pickup_lng'] ?? null,
            'start_date'       => $data['start_date'],
            'end_date'         => $data['end_date'],
            'total_days'       => $totalDays,
            'daily_rate_khr'   => $dailyRate,
            'total_amount_khr' => $total,
            'payment_method'   => $data['payment_method'] ?? 'cash',
            'notes'            => $data['notes'] ?? null,
            'status'           => 'pending',
        ]);

        return $this->success([
            'rental_id'        => $rental->id,
            'vehicle_type'     => $rental->vehicle_type,
            'pickup_location'  => $rental->pickup_location,
            'start_date'       => $rental->start_date->toDateString(),
            'end_date'         => $rental->end_date->toDateString(),
            'total_days'       => $rental->total_days,
            'daily_rate_khr'   => $rental->daily_rate_khr,
            'total_amount_khr' => $rental->total_amount_khr,
            'total_amount_usd' => round($rental->total_amount_khr / 4000, 2),
            'payment_method'   => $rental->payment_method,
            'status'           => $rental->status,
            'message'          => 'Rental request submitted. We will confirm within 1 hour.',
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
            ->through(fn($r) => [
                'id'               => $r->id,
                'vehicle_type'     => $r->vehicle_type,
                'pickup_location'  => $r->pickup_location,
                'start_date'       => $r->start_date->toDateString(),
                'end_date'         => $r->end_date->toDateString(),
                'total_days'       => $r->total_days,
                'total_amount_khr' => $r->total_amount_khr,
                'status'           => $r->status,
                'created_at'       => $r->created_at->toDateTimeString(),
            ]);

        return $this->success([
            'rentals'    => $rentals->items(),
            'pagination' => [
                'total'        => $rentals->total(),
                'current_page' => $rentals->currentPage(),
                'last_page'    => $rentals->lastPage(),
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function dailyRate(string $vehicleType): int
    {
        // Rates in KHR per day. Can be moved to pricing_settings table.
        return match ($vehicleType) {
            'motorcycle' => 20_000,
            'tuk_tuk'   => 25_000,
            'electric'  => 35_000,
            'sedan'     => 40_000,
            'suv'       => 60_000,
            'van'       => 70_000,
            'truck'     => 100_000,
            default     => 40_000,
        };
    }
}
