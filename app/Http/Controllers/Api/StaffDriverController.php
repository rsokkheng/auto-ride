<?php

namespace App\Http\Controllers\Api;

use App\Services\PartnerStatementService as PS;
use Illuminate\Http\Request;

/**
 * ROTEH staff driver (salaried, Partner packages only) — the driver app's
 * home card: today's packages and the cash to hand over to ROTEH.
 */
class StaffDriverController extends ApiController
{
    /**
     * GET /v1/driver/staff/packages — packages admin assigned to me:
     * active ones (to pick up / on the way / to return) and today's finished.
     */
    public function packages(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || ! $user->isSalariedStaff()) return $this->unauthorized();

        $active = \App\Models\Delivery::with(['sender', 'partner.partnerProfile:id,user_id,business_name'])
            ->where('driver_id', $user->id)->whereNotNull('partner_id')
            ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_progress', 'in_transit', 'delivery_failed'])
            ->orderByRaw("CASE status WHEN 'delivery_failed' THEN 0 WHEN 'accepted' THEN 2 WHEN 'assigned' THEN 2 ELSE 1 END")
            ->orderBy('assigned_at')
            ->get();

        [$from, $to] = PS::dayRange(null);
        $appTz = config('app.timezone');
        $day  = [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)];
        $done = \App\Models\Delivery::whereNotNull('partner_id')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('driver_id', $user->id)
                    ->whereIn('status', ['completed', 'delivered', 'at_roteh', 'returned'])
                    ->where(fn ($q) => $q->whereBetween('completed_at', $day)->orWhereBetween('hub_returned_at', $day)))
                // Pickup legs I brought to the office today (the package may have a delivery driver now).
                ->orWhere(fn ($q) => $q->where('pickup_driver_id', $user->id)->whereBetween('office_received_at', $day)))
            ->latest('updated_at')->get();

        $shop = fn ($d) => $d->partner?->partnerProfile?->business_name;
        $office = ['address' => (string) config('ride.office.address'), 'lat' => config('ride.office.lat'), 'lng' => config('ride.office.lng')];
        // Pickup leg: shop → office. Delivery leg (and older packages): → customer.
        $route = fn ($d) => $d->leg === \App\Models\Delivery::LEG_PICKUP
            ? ['collect_from' => ['address' => $d->pickup_address, 'lat' => $d->pickup_lat, 'lng' => $d->pickup_lng], 'destination' => $office]
            : ['collect_from' => $d->leg === \App\Models\Delivery::LEG_DELIVERY ? $office : ['address' => $d->pickup_address, 'lat' => $d->pickup_lat, 'lng' => $d->pickup_lng],
               'destination'  => ['address' => $d->dropoff_address, 'lat' => $d->dropoff_lat, 'lng' => $d->dropoff_lng]];

        return $this->success([
            'active' => $active->map(fn ($d) => $d->toArray() + ['shop_name' => $shop($d)] + $route($d))->values(),
            'done'   => $done->map(fn ($d) => $d->toArray() + ['shop_name' => $shop($d), 
                // In the list only for the pickup leg (not finished by me on the delivery leg).
                'brought_to_office' => ! ($d->driver_id === $user->id && in_array($d->status, ['completed', 'delivered', 'at_roteh', 'returned'], true))])->values(),
        ]);
    }

    /**
     * GET /v1/driver/staff/today?date=YYYY-MM-DD — the staff home screen:
     * packages to pick up / on the way, the day's results, cash, balance.
     */
    public function today(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();
        if (! $user->isSalariedStaff()) {
            return $this->success(['is_staff' => false]);
        }

        $date = $request->validate(['date' => 'nullable|date_format:Y-m-d'])['date'] ?? null;
        [$from, $to] = PS::dayRange($date);
        $appTz = config('app.timezone');
        [$a, $b] = [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)];
        $mine = app(PS::class)->driverCash($from, $to)->firstWhere('driver.id', $user->id);

        $base = fn () => \App\Models\Delivery::where('driver_id', $user->id)->whereNotNull('partner_id');

        // Live workload (not date-bound).
        $toPickUp = $base()->whereIn('status', ['assigned', 'accepted'])->count();
        $onTheWay = $base()->whereIn('status', ['picked_up', 'in_progress', 'in_transit'])->count();
        $toReturn = $base()->where('status', 'delivery_failed')->count();

        // The day's results.
        $assigned  = $base()->whereBetween('assigned_at', [$a, $b])->count();
        $completed = $base()->whereIn('status', ['completed', 'delivered'])->whereBetween('completed_at', [$a, $b])->count();
        $failed    = $base()->whereIn('status', ['delivery_failed', 'at_roteh', 'returned'])->whereBetween('failed_at', [$a, $b])->count();

        // Working time: first pickup scan → last finish that day.
        $firstScan = $base()->whereBetween('pickup_scanned_at', [$a, $b])->min('pickup_scanned_at');
        $lastDone  = collect([
            $base()->whereBetween('completed_at', [$a, $b])->max('completed_at'),
            $base()->whereBetween('hub_returned_at', [$a, $b])->max('hub_returned_at'),
            $base()->whereBetween('failed_at', [$a, $b])->max('failed_at'),
        ])->filter()->max();
        $workMinutes = $firstScan && $lastDone
            ? max(0, \Carbon\Carbon::parse($firstScan)->diffInMinutes(\Carbon\Carbon::parse($lastDone)))
            : 0;

        $handled = $completed + $failed;

        return $this->success([
            'is_staff'          => true,
            'date'              => $from->toDateString(),
            'salary'            => (int) $user->salary,
            'wallet_balance'    => (int) $user->wallet_balance,
            'to_pick_up'        => $toPickUp,
            'on_the_way'        => $onTheWay,
            'to_return'         => $toReturn,
            'assigned'          => $assigned,
            'completed'         => $completed,
            'failed'            => $failed,
            'success_rate'      => $handled > 0 ? round($completed / $handled * 100) : null,
            'work_minutes'      => (int) $workMinutes,
            // Kept for the earlier app build.
            'delivered'         => $mine['delivered'] ?? 0,
            'returning'         => $mine['returning'] ?? 0,
            'returned'          => $mine['returned'] ?? 0,
            'cod_collected'     => $mine['cod_collected'] ?? 0,
            'cash_khr'          => $mine['cash_khr'] ?? 0,
            'cash_usd'          => $mine['cash_usd'] ?? 0,
            'cash_to_hand_over' => $mine['cash_to_hand_over'] ?? 0,
            'packages'          => $mine['packages'] ?? [],
        ]);
    }
}
