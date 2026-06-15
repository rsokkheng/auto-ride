<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TripHistoryController extends ApiController
{
    /**
     * GET /v1/trips
     *
     * Unified trip history: rides + deliveries + movings in one feed.
     *
     * Query params:
     *   filter  = recent | day | month        (default: recent)
     *   date    = YYYY-MM-DD                  (required when filter=day)
     *   month   = YYYY-MM                     (required when filter=month)
     *   type    = all | ride | delivery | moving (default: all)
     *   status  = all | completed | cancelled  (default: all)
     *   page    = 1, 2, ...                   (15 per page)
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $filter = $request->input('filter', 'recent'); // recent | day | month
        $type   = $request->input('type', 'all');      // all | ride | delivery | moving
        $status = $request->input('status', 'all');    // all | completed | cancelled

        [$start, $end] = $this->resolveDateRange($filter, $request);

        $trips = collect();

        // ── Rides ────────────────────────────────────────────────────────────
        if (in_array($type, ['all', 'ride'])) {
            $q = Ride::with(['driver', 'passenger', 'vehicle'])
                ->where(fn($q) => $q->where('passenger_id', $user->id)->orWhere('driver_id', $user->id));

            $this->applyDateFilter($q, $start, $end, 'completed_at', 'created_at');
            $this->applyStatusFilter($q, $status);

            $q->get()->each(function ($ride) use (&$trips) {
                $trips->push($this->formatRide($ride));
            });
        }

        // ── Deliveries ───────────────────────────────────────────────────────
        if (in_array($type, ['all', 'delivery'])) {
            $q = Delivery::with(['driver', 'sender', 'vehicle'])
                ->where('service_type', 'delivery')
                ->where(fn($q) => $q->where('sender_id', $user->id)->orWhere('driver_id', $user->id));

            $this->applyDateFilter($q, $start, $end, 'updated_at', 'created_at');
            $this->applyStatusFilter($q, $status);

            $q->get()->each(function ($delivery) use (&$trips) {
                $trips->push($this->formatDelivery($delivery));
            });
        }

        // ── Movings ──────────────────────────────────────────────────────────
        if (in_array($type, ['all', 'moving'])) {
            $q = Delivery::with(['driver', 'sender', 'vehicle'])
                ->where('service_type', 'moving')
                ->where(fn($q) => $q->where('sender_id', $user->id)->orWhere('driver_id', $user->id));

            $this->applyDateFilter($q, $start, $end, 'updated_at', 'created_at');
            $this->applyStatusFilter($q, $status);

            $q->get()->each(function ($delivery) use (&$trips) {
                $trips->push($this->formatDelivery($delivery));
            });
        }

        // Sort all trips newest first
        $sorted = $trips->sortByDesc('date')->values();

        // Manual pagination
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = 15;
        $total   = $sorted->count();
        $items   = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        // Group by month for the grouped view
        $grouped = $this->groupByMonth($items);

        // Stats for the period
        $stats = $this->calcStats($sorted);

        return $this->success([
            'trips'       => $items,
            'grouped'     => $grouped,
            'stats'       => $stats,
            'filter'      => $filter,
            'type'        => $type,
            'pagination'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $perPage),
                'has_more'     => ($page * $perPage) < $total,
            ],
        ]);
    }

    /**
     * GET /v1/trips/months
     * Returns distinct months that have trips — for the month-picker UI.
     */
    public function months(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $rideMonths = Ride::where(fn($q) => $q->where('passenger_id', $user->id)->orWhere('driver_id', $user->id))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->groupBy('month')
            ->pluck('month');

        $deliveryMonths = Delivery::where(fn($q) => $q->where('sender_id', $user->id)->orWhere('driver_id', $user->id))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->groupBy('month')
            ->pluck('month');

        $months = $rideMonths->merge($deliveryMonths)
            ->unique()
            ->sort()
            ->reverse()
            ->map(fn($m) => [
                'value' => $m,
                'label' => Carbon::createFromFormat('Y-m', $m)->format('F Y'),
            ])
            ->values();

        return $this->success(['months' => $months]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function resolveDateRange(string $filter, Request $request): array
    {
        return match ($filter) {
            'day' => [
                Carbon::parse($request->input('date', today()))->startOfDay(),
                Carbon::parse($request->input('date', today()))->endOfDay(),
            ],
            'month' => [
                Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth(),
                Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->endOfMonth(),
            ],
            default => [null, null], // recent = no date limit
        };
    }

    private function applyDateFilter($query, $start, $end, string $completedCol, string $fallbackCol): void
    {
        if (! $start) return;

        $query->where(function ($q) use ($start, $end, $completedCol, $fallbackCol) {
            $q->whereBetween($completedCol, [$start, $end])
              ->orWhere(function ($q2) use ($start, $end, $completedCol, $fallbackCol) {
                  $q2->whereNull($completedCol)->whereBetween($fallbackCol, [$start, $end]);
              });
        });
    }

    private function applyStatusFilter($query, string $status): void
    {
        if ($status === 'completed') $query->where('status', 'completed');
        if ($status === 'cancelled') $query->where('status', 'cancelled');
    }

    private function formatRide(Ride $ride): array
    {
        $other = $ride->driver ?? $ride->passenger;
        return [
            'id'             => $ride->id,
            'ref'            => 'RIDE-' . str_pad($ride->id, 6, '0', STR_PAD_LEFT),
            'type'           => 'ride',
            'type_label'     => 'Ride',
            'status'         => $ride->status,
            'status_label'   => $this->statusLabel($ride->status),
            'pickup'         => $ride->pickup_address,
            'dropoff'        => $ride->dropoff_address,
            'pickup_lat'     => $ride->pickup_lat,
            'pickup_lng'     => $ride->pickup_lng,
            'dropoff_lat'    => $ride->dropoff_lat,
            'dropoff_lng'    => $ride->dropoff_lng,
            'amount'         => $ride->fare ?? 0,
            'discount'       => $ride->discount_amount ?? 0,
            'tip'            => $ride->tip_amount ?? 0,
            'currency'       => 'KHR',
            'payment_method' => $ride->payment_method ?? 'cash',
            'rating'         => $ride->rating,
            'date'           => ($ride->completed_at ?? $ride->created_at)?->toIso8601String(),
            'date_label'     => ($ride->completed_at ?? $ride->created_at)?->format('d M Y, h:i A'),
            'other_party'    => $other ? ['name' => $other->name, 'avatar' => $other->avatar] : null,
            'vehicle'        => $ride->vehicle ? ['type' => $ride->vehicle->type, 'plate' => $ride->vehicle->plate] : null,
            'can_rebook'     => $ride->status === 'completed',
            'can_rate'       => $ride->status === 'completed' && ! $ride->rating,
        ];
    }

    private function formatDelivery(Delivery $delivery): array
    {
        $other = $delivery->driver ?? $delivery->sender;
        $isMoving = $delivery->service_type === 'moving';
        return [
            'id'             => $delivery->id,
            'ref'            => ($isMoving ? 'MOV-' : 'DEL-') . str_pad($delivery->id, 6, '0', STR_PAD_LEFT),
            'type'           => $delivery->service_type,
            'type_label'     => $isMoving ? 'Moving' : 'Delivery',
            'status'         => $delivery->status,
            'status_label'   => $this->statusLabel($delivery->status),
            'pickup'         => $delivery->pickup_address,
            'dropoff'        => $delivery->dropoff_address,
            'pickup_lat'     => $delivery->pickup_lat,
            'pickup_lng'     => $delivery->pickup_lng,
            'dropoff_lat'    => $delivery->dropoff_lat,
            'dropoff_lng'    => $delivery->dropoff_lng,
            'amount'         => $delivery->fee ?? 0,
            'discount'       => $delivery->discount_amount ?? 0,
            'tip'            => 0,
            'currency'       => 'KHR',
            'payment_method' => $delivery->payment_method ?? 'cash',
            'rating'         => $delivery->rating ?? null,
            'date'           => ($delivery->updated_at ?? $delivery->created_at)?->toIso8601String(),
            'date_label'     => ($delivery->updated_at ?? $delivery->created_at)?->format('d M Y, h:i A'),
            'other_party'    => $other ? ['name' => $other->name, 'avatar' => $other->avatar ?? null] : null,
            'vehicle'        => null,
            'recipient_name' => $delivery->recipient_name,
            'can_rebook'     => $delivery->status === 'completed',
            'can_rate'       => $delivery->status === 'completed' && ! $delivery->rating,
        ];
    }

    private function groupByMonth(\Illuminate\Support\Collection $trips): array
    {
        return $trips->groupBy(fn($t) => Carbon::parse($t['date'])->format('F Y'))
            ->map(fn($group, $month) => [
                'month' => $month,
                'count' => $group->count(),
                'trips' => $group->values(),
            ])
            ->values()
            ->toArray();
    }

    private function calcStats(\Illuminate\Support\Collection $trips): array
    {
        $completed = $trips->where('status', 'completed');
        return [
            'total_trips'     => $trips->count(),
            'completed'       => $completed->count(),
            'cancelled'       => $trips->where('status', 'cancelled')->count(),
            'total_spent_khr' => (int) $completed->sum('amount'),
            'rides'           => $trips->where('type', 'ride')->count(),
            'deliveries'      => $trips->where('type', 'delivery')->count(),
            'movings'         => $trips->where('type', 'moving')->count(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'completed'     => 'Completed',
            'cancelled'     => 'Cancelled',
            'in_progress'   => 'In Progress',
            'accepted'      => 'Accepted',
            'driver_arrived'=> 'Driver Arrived',
            'requested'     => 'Looking for driver',
            default         => ucfirst($status),
        };
    }
}
