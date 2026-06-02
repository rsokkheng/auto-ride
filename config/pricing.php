<?php

/**
 * Ride fare pricing — Cambodia market (KHR ៛)
 * Modelled after PassApp / Grab Southeast Asia.
 *
 * Formula:
 *   fare = booking_fee + base + (per_km × distance) + (per_min × traffic_minutes)
 *   + night_surcharge (22:00–05:00, +20%)
 *   × surge_multiplier
 *   rounded up to nearest 100 ៛, capped at minimum
 */
return [

    'rides' => [

        'motorcycle' => [
            'label'       => '🛵 Motorcycle',
            'base'        => env('RIDE_BASE_MOTO',    2500),
            'per_km'      => env('RIDE_PERKM_MOTO',    700),
            'per_min'     => env('RIDE_PERMIN_MOTO',   150),  // traffic surcharge per minute
            'booking_fee' => env('RIDE_BOOKING_MOTO',  500),
            'minimum'     => env('RIDE_MIN_MOTO',      3000),
            'capacity'    => 1,
        ],

        'tuk_tuk' => [
            'label'       => '🛺 Tuk-tuk',
            'base'        => env('RIDE_BASE_TUK',      3500),
            'per_km'      => env('RIDE_PERKM_TUK',     900),
            'per_min'     => env('RIDE_PERMIN_TUK',    200),
            'booking_fee' => env('RIDE_BOOKING_TUK',   500),
            'minimum'     => env('RIDE_MIN_TUK',       4500),
            'capacity'    => 3,
        ],

        'standard' => [
            'label'       => '🚗 Car Standard',
            'base'        => env('RIDE_BASE_STD',      5000),
            'per_km'      => env('RIDE_PERKM_STD',     1500),
            'per_min'     => env('RIDE_PERMIN_STD',    250),
            'booking_fee' => env('RIDE_BOOKING_STD',   1000),
            'minimum'     => env('RIDE_MIN_STD',       7000),
            'capacity'    => 4,
        ],

        'premium' => [
            'label'       => '🚙 Car Premium',
            'base'        => env('RIDE_BASE_PREM',     8000),
            'per_km'      => env('RIDE_PERKM_PREM',    3000),
            'per_min'     => env('RIDE_PERMIN_PREM',   400),
            'booking_fee' => env('RIDE_BOOKING_PREM',  1000),
            'minimum'     => env('RIDE_MIN_PREM',      12000),
            'capacity'    => 4,
        ],

        'shared' => [
            'label'       => '🚐 Shared Ride',
            'base'        => env('RIDE_BASE_SHARED',   2500),
            'per_km'      => env('RIDE_PERKM_SHARED',  1000),
            'per_min'     => env('RIDE_PERMIN_SHARED', 150),
            'booking_fee' => env('RIDE_BOOKING_SHARED',500),
            'minimum'     => env('RIDE_MIN_SHARED',    4000),
            'capacity'    => 4,
        ],

        'van' => [
            'label'       => '🚐 Van / XL',
            'base'        => env('RIDE_BASE_VAN',      7000),
            'per_km'      => env('RIDE_PERKM_VAN',     2200),
            'per_min'     => env('RIDE_PERMIN_VAN',    350),
            'booking_fee' => env('RIDE_BOOKING_VAN',   1000),
            'minimum'     => env('RIDE_MIN_VAN',       10000),
            'capacity'    => 7,
        ],

    ],

    // Night surcharge rate (decimal). Applied 22:00–05:00.
    'night_surcharge_rate' => env('NIGHT_SURCHARGE_RATE', 0.20),

    // Average city speed used for Haversine ETA fallback (km/h).
    'avg_city_speed_kmh' => env('AVG_CITY_SPEED_KMH', 30),

    // Delivery
    'delivery' => [
        'booking_fee'    => env('DELIVERY_BOOKING_FEE', 500),
        'night_surcharge'=> env('DELIVERY_NIGHT_RATE',  0.15),
    ],

];
