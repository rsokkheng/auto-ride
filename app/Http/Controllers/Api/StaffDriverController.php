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
        $done = \App\Models\Delivery::where('driver_id', $user->id)->whereNotNull('partner_id')
            ->whereIn('status', ['completed', 'delivered', 'returned'])
            ->where(fn ($q) => $q->whereBetween('completed_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)])
                ->orWhereBetween('returned_at', [$from->copy()->setTimezone($appTz), $to->copy()->setTimezone($appTz)]))
            ->latest('updated_at')->get();

        $shop = fn ($d) => $d->partner?->partnerProfile?->business_name;

        return $this->success([
            'active' => $active->map(fn ($d) => $d->toArray() + ['shop_name' => $shop($d)])->values(),
            'done'   => $done->map(fn ($d) => $d->toArray() + ['shop_name' => $shop($d)])->values(),
        ]);
    }

    /** GET /v1/driver/staff/today */
    public function today(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();
        if (! $user->isSalariedStaff()) {
            return $this->success(['is_staff' => false]);
        }

        [$from, $to] = PS::dayRange(null);
        $mine = app(PS::class)->driverCash($from, $to)->firstWhere('driver.id', $user->id);

        return $this->success([
            'is_staff'          => true,
            'salary'            => (int) $user->salary,
            'date'              => $from->toDateString(),
            'delivered'         => $mine['delivered'] ?? 0,
            'returning'         => $mine['returning'] ?? 0,
            'returned'          => $mine['returned'] ?? 0,
            'cod_collected'     => $mine['cod_collected'] ?? 0,
            'cash_to_hand_over' => $mine['cash_to_hand_over'] ?? 0,
            'packages'          => $mine['packages'] ?? [],
        ]);
    }
}
