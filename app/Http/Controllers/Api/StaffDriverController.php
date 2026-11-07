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
