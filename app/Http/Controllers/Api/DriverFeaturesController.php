<?php

namespace App\Http\Controllers\Api;

use App\Models\Ride;
use App\Models\Delivery;
use App\Models\DriverIncentive;
use App\Models\DriverSession;
use App\Models\RideDecline;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverFeaturesController extends ApiController
{
    // ── Driver dashboard ──────────────────────────────────────────────────────

    /**
     * GET /v1/driver/dashboard?period=today|week|month
     *
     * Returns: hours_online, acceptance_rate, accepted, completed
     */
    public function dashboard(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $period = $request->input('period', 'today');

        $start = match ($period) {
            'week'  => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        // ── Hours Online ─────────────────────────────────────────────────────
        $sessions = DriverSession::where('driver_id', $user->id)
            ->where('started_at', '>=', $start)
            ->get();

        $onlineMinutes = $sessions->sum(function ($s) {
            $end = $s->ended_at ?? now();
            return (int) $s->started_at->diffInMinutes($end);
        });

        $hoursOnline = round($onlineMinutes / 60, 1);

        // ── Accepted & Completed ─────────────────────────────────────────────
        $accepted = Ride::where('driver_id', $user->id)
            ->where('accepted_at', '>=', $start)
            ->count()
            + Delivery::where('driver_id', $user->id)
            ->where('assigned_at', '>=', $start)
            ->whereNotNull('driver_id')
            ->count();

        $completed = Ride::where('driver_id', $user->id)
            ->where('completed_at', '>=', $start)
            ->where('status', 'completed')
            ->count()
            + Delivery::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $start)
            ->count();

        // ── Acceptance Rate ──────────────────────────────────────────────────
        $declined = RideDecline::where('driver_id', $user->id)
            ->where('created_at', '>=', $start)
            ->count();

        $total = $accepted + $declined;
        $acceptanceRate = $total > 0 ? round(($accepted / $total) * 100, 1) : 100.0;

        // ── Earnings this period ─────────────────────────────────────────────
        $earnings = (int) Ride::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->sum('fare')
            + (int) Delivery::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $start)
            ->sum('fee');

        return $this->success([
            'period'          => $period,
            'from'            => $start->toDateTimeString(),
            'hours_online'    => $hoursOnline,
            'acceptance_rate' => $acceptanceRate,
            'accepted'        => $accepted,
            'completed'       => $completed,
            'declined'        => $declined,
            'earnings_khr'    => $earnings,
            'is_online'       => (bool) $user->available,
        ]);
    }

    // ── Earnings summary ──────────────────────────────────────────────────────

    public function earnings(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $period = $request->input('period', 'daily'); // daily|weekly|monthly

        $start = match ($period) {
            'weekly'  => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default   => now()->startOfDay(),
        };

        $rideEarnings = Ride::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->sum('fare');

        $deliveryEarnings = Delivery::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $start)
            ->sum('fee');

        $tripCount = Ride::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->count();

        $deliveryCount = Delivery::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $start)
            ->count();

        // Daily breakdown for weekly/monthly
        $breakdown = [];
        if ($period !== 'daily') {
            $breakdown = Ride::where('driver_id', $user->id)
                ->where('status', 'completed')
                ->where('completed_at', '>=', $start)
                ->select(DB::raw('DATE(completed_at) as date'), DB::raw('SUM(fare) as total'), DB::raw('COUNT(*) as trips'))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();
        }

        return $this->success([
            'period'           => $period,
            'from'             => $start->toDateTimeString(),
            'ride_earnings'    => (int) $rideEarnings,
            'delivery_earnings'=> (int) $deliveryEarnings,
            'total_earnings'   => (int) $rideEarnings + (int) $deliveryEarnings,
            'trip_count'       => $tripCount,
            'delivery_count'   => $deliveryCount,
            'breakdown'        => $breakdown,
            'currency'         => 'KHR',
        ]);
    }

    // ── Driver incentives ─────────────────────────────────────────────────────

    public function incentives(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $incentives = DriverIncentive::where('driver_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->map(function ($incentive) {
                $progress = $incentive->currentProgress();
                $incentive->progress        = $progress;
                $incentive->progress_pct    = $incentive->target_trips > 0
                    ? min(100, (int) round($progress / $incentive->target_trips * 100))
                    : 0;
                return $incentive;
            });

        return $this->success(['incentives' => $incentives]);
    }

    // ── Driver cancellation limit ─────────────────────────────────────────────

    public function cancellationStatus(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $isPenalised = $user->cancellation_penalty_until && now()->lt($user->cancellation_penalty_until);

        return $this->success([
            'cancellation_count'      => $user->cancellation_count,
            'is_penalised'            => $isPenalised,
            'penalty_until'           => $user->cancellation_penalty_until,
            'limit_before_penalty'    => 5, // configurable
        ]);
    }

    // ── Driver approval (for newly registered drivers) ────────────────────────

    public function approvalStatus(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        return $this->success([
            'approval_status' => $user->approval_status,
            'approved_at'     => $user->approved_at,
        ]);
    }

    // ── Admin: approve / reject driver ────────────────────────────────────────

    public function approveDriver(Request $request, User $driver)
    {
        $admin = $this->authUser($request);
        if (! $admin || $admin->role !== 'admin') return $this->unauthorized();

        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:255',
        ]);

        $driver->update([
            'approval_status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'approved_at'     => $data['action'] === 'approve' ? now() : null,
            'status_note'     => $data['reason'] ?? null,
        ]);

        return $this->success([
            'message' => "Driver {$data['action']}d.",
            'driver'  => $driver->fresh(['id', 'name', 'email', 'approval_status', 'approved_at']),
        ]);
    }

    public function pendingDrivers(Request $request)
    {
        $admin = $this->authUser($request);
        if (! $admin || $admin->role !== 'admin') return $this->unauthorized();

        $drivers = User::where('role', 'driver')
            ->where('approval_status', 'pending')
            ->paginate(20);

        return $this->success(['drivers' => $drivers]);
    }

    // ── Heat map ──────────────────────────────────────────────────────────────

    public function heatmap(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        // Aggregate pickup points from last 2 hours
        $points = Ride::where('status', 'requested')
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->where('created_at', '>=', now()->subHours(2))
            ->select('pickup_lat as lat', 'pickup_lng as lng')
            ->get();

        return $this->success(['points' => $points]);
    }

    // ── Mask phone ────────────────────────────────────────────────────────────

    public function maskedPhone(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }

        // Return proxy phone if set, otherwise the real phone masked
        $target = $user->id === $ride->driver_id
            ? $ride->passenger   // driver wants passenger phone
            : $ride->driver;     // passenger wants driver phone

        if (! $target) {
            return response()->json(['data' => null, 'message' => 'No phone to show.'], 404);
        }

        $phone = $target->proxy_phone ?? $target->phone;

        return $this->success(['phone' => $phone]);
    }

    // ── Earnings summary (Flutter alias) ─────────────────────────────────────

    /**
     * GET /v1/driver/earnings/summary
     * Returns today, this week, and total trip count in one call.
     */
    public function earningsSummary(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $todayStart = now()->startOfDay();
        $weekStart  = now()->startOfWeek();

        $todayKhr = (int) Ride::where('driver_id', $user->id)->where('status', 'completed')
            ->where('completed_at', '>=', $todayStart)->sum('fare')
            + (int) Delivery::where('driver_id', $user->id)->where('status', 'completed')
            ->where('updated_at', '>=', $todayStart)->sum('fee');

        $weekKhr = (int) Ride::where('driver_id', $user->id)->where('status', 'completed')
            ->where('completed_at', '>=', $weekStart)->sum('fare')
            + (int) Delivery::where('driver_id', $user->id)->where('status', 'completed')
            ->where('updated_at', '>=', $weekStart)->sum('fee');

        $totalTrips = Ride::where('driver_id', $user->id)->where('status', 'completed')->count()
            + Delivery::where('driver_id', $user->id)->where('status', 'completed')->count();

        return $this->success([
            'today_khr'   => $todayKhr,
            'week_khr'    => $weekKhr,
            'total_trips' => $totalTrips,
            'currency'    => 'KHR',
        ]);
    }

    // ── Earnings history (daily breakdown) ───────────────────────────────────

    /**
     * GET /v1/driver/earnings/history?days=7
     */
    public function earningsHistory(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $days  = (int) $request->input('days', 7);
        $start = now()->subDays($days)->startOfDay();

        $rideRows = Ride::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('COUNT(*) as trips'), DB::raw('SUM(fare) as amount_khr'))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $deliveryRows = Delivery::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $start)
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('COUNT(*) as trips'), DB::raw('SUM(fee) as amount_khr'))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // Merge rides + deliveries per day
        $allDates = collect(range(0, $days - 1))->map(fn($i) => now()->subDays($i)->toDateString())->sort()->values();

        $items = $allDates->map(function ($date) use ($rideRows, $deliveryRows) {
            $r = $rideRows->get($date);
            $d = $deliveryRows->get($date);
            return [
                'date'       => $date,
                'trips'      => ($r->trips ?? 0) + ($d->trips ?? 0),
                'amount_khr' => (int) ($r->amount_khr ?? 0) + (int) ($d->amount_khr ?? 0),
            ];
        });

        return $this->success(['items' => $items, 'currency' => 'KHR']);
    }
}
