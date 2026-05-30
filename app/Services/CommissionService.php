<?php

namespace App\Services;

use App\Models\User;

class CommissionService
{
    /**
     * Split a trip fare into driver earning, platform fee, and company share.
     *
     * Returns amounts in KHR (integers, rounded down to nearest 100 ៛).
     *
     * driver_type  | driver_earning | platform_fee | company_share
     * -------------|----------------|--------------|---------------
     * owner        | fare * (1-p)   | fare * p     | 0
     * rental       | fare*(1-p-c)   | fare * p     | fare * c
     * employee     | 0 (salary)     | fare * p     | fare * (1-p)
     *
     * Rate priority (highest to lowest):
     *   1. user.commission_rate          (per-driver admin override)
     *   2. company.platform_commission_rate  (company-level override)
     *   3. config('commission.platform_rate.<type>')  (default)
     */
    public function split(int $fare, User $driver): array
    {
        $type = $driver->driver_type ?? 'owner';

        // Resolve platform commission rate.
        $platformRate = $driver->commission_rate
            ?? $driver->company?->platform_commission_rate
            ?? (float) config("commission.platform_rate.{$type}", 20);

        $platformFee  = $this->roundDown($fare * $platformRate / 100);
        $companyShare = 0;
        $driverEarning = 0;

        switch ($type) {
            case 'owner':
                $driverEarning = $fare - $platformFee;
                break;

            case 'rental':
                $companyRate  = (float) ($driver->company?->company_commission_rate
                    ?? config('commission.rental_company_rate', 10));
                $companyShare  = $this->roundDown($fare * $companyRate / 100);
                $driverEarning = $fare - $platformFee - $companyShare;
                break;

            case 'employee':
                // Driver earns salary — no per-trip wallet credit.
                $companyShare = $fare - $platformFee;
                $driverEarning = 0;
                break;

            default:
                $driverEarning = $fare - $platformFee;
        }

        return [
            'fare'           => $fare,
            'platform_fee'   => $platformFee,
            'company_share'  => $companyShare,
            'driver_earning' => max(0, $driverEarning),
            'platform_rate'  => $platformRate,
            'driver_type'    => $type,
        ];
    }

    private function roundDown(float $amount): int
    {
        // Round down to nearest 100 ៛.
        return (int) (floor($amount / 100) * 100);
    }
}
