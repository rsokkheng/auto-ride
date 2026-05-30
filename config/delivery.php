<?php

return [

    /*
    |----------------------------------------------------------------------
    | Currency
    |----------------------------------------------------------------------
    | All monetary amounts are stored and calculated in Khmer Riel (KHR ៛).
    | KHR has no sub-unit in practical use — amounts are whole integers.
    | Symbol: ៛    ISO code: KHR
    */
    'currency_code'   => 'KHR',
    'currency_symbol' => '៛',

    /*
    |----------------------------------------------------------------------
    | Delivery Fee Pricing (KHR ៛)
    |----------------------------------------------------------------------
    | fee = base + (per_km × distance_km) + package_surcharge
    | Result is rounded up to the nearest 100 ៛.
    */
    'fee_base'             => env('DELIVERY_FEE_BASE', 3000),
    'fee_per_km'           => env('DELIVERY_FEE_PER_KM', 1200),
    'fee_surcharge_small'  => env('DELIVERY_FEE_SURCHARGE_SMALL',  0),
    'fee_surcharge_medium' => env('DELIVERY_FEE_SURCHARGE_MEDIUM', 2000),
    'fee_surcharge_large'  => env('DELIVERY_FEE_SURCHARGE_LARGE',  5000),

    /*
    |----------------------------------------------------------------------
    | Ride Fare Pricing (KHR ៛)
    |----------------------------------------------------------------------
    | fare = base + (per_km × distance_km)
    | Result is rounded up to the nearest 100 ៛.
    */
    'ride_base_standard'  => env('RIDE_FARE_BASE_STANDARD',  4000),
    'ride_perkm_standard' => env('RIDE_FARE_PERKM_STANDARD', 1500),
    'ride_base_premium'   => env('RIDE_FARE_BASE_PREMIUM',   8000),
    'ride_perkm_premium'  => env('RIDE_FARE_PERKM_PREMIUM',  3000),
    'ride_base_shared'    => env('RIDE_FARE_BASE_SHARED',    2500),
    'ride_perkm_shared'   => env('RIDE_FARE_PERKM_SHARED',   1000),

    /*
    |----------------------------------------------------------------------
    | Driver Matching — Search Radius
    |----------------------------------------------------------------------
    | Maximum distance (km) to search for available drivers from the
    | pickup location. Drivers beyond this radius are excluded.
    */
    'match_radius_km' => env('DRIVER_MATCH_RADIUS_KM', 30),

    /*
    |----------------------------------------------------------------------
    | Driver Matching — Scoring Weights
    |----------------------------------------------------------------------
    | Score = distance_km * distance_weight + (5.0 - rating) * rating_weight
    | Lower score = better match. Increase distance_weight to favour
    | proximity; increase rating_weight to favour highly-rated drivers.
    */
    'match_distance_weight' => env('DRIVER_MATCH_DISTANCE_WEIGHT', 0.7),
    'match_rating_weight'   => env('DRIVER_MATCH_RATING_WEIGHT', 1.5),

];
