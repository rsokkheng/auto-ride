<?php

return [

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
