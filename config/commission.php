<?php

return [

    /*
    |----------------------------------------------------------------------
    | Platform Commission Rates (%) by Driver Type
    |----------------------------------------------------------------------
    | Percentage of trip fare kept by the platform.
    | Priority: driver.commission_rate → company.platform_commission_rate → here.
    */
    'platform_rate' => [
        'owner'    => env('COMMISSION_RATE_OWNER',    20),  // 20% to platform
        'rental'   => env('COMMISSION_RATE_RENTAL',   20),  // 20% to platform
        'employee' => env('COMMISSION_RATE_EMPLOYEE', 10),  // 10% to platform
    ],

    /*
    |----------------------------------------------------------------------
    | Company Commission Rate for Rental Drivers (%)
    |----------------------------------------------------------------------
    | Extra % of fare that goes to the company when the driver is 'rental'.
    | Added on top of the platform rate.
    | Priority: company.company_commission_rate → here.
    */
    'rental_company_rate' => env('COMMISSION_RENTAL_COMPANY', 10),  // 10% to company

    /*
    |----------------------------------------------------------------------
    | Company Commission Rate for Employee Drivers (%)
    |----------------------------------------------------------------------
    | % of fare that goes to the company (platform takes its cut first).
    | Priority: company.company_commission_rate → here.
    */
    'employee_company_rate' => env('COMMISSION_EMPLOYEE_COMPANY', 90),  // 90% remainder to company

];
