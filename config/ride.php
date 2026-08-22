<?php

return [

    /*
    |------------------------------------------------------------------
    | Sequential Dispatch (Grab-style, expanding radius)
    |------------------------------------------------------------------
    | When a ride is requested, drivers within the first radius tier are
    | ranked by DriverMatchingService and offered the ride one at a time —
    | best match first. If a driver doesn't accept within the offer window,
    | the ride is offered to the next driver in that tier's queue. Once a
    | tier's ranked queue is exhausted (nobody accepted, or none were
    | available), the search widens to the next radius tier — drivers
    | already tried are never re-offered — before finally falling through
    | to the untargeted self-serve window below.
    */
    'radius_tiers_km'       => env('RIDE_RADIUS_TIERS_KM', '2,4,6,8'),
    'dispatch_limit'        => env('RIDE_DISPATCH_LIMIT', 10),
    'offer_timeout_seconds' => env('RIDE_OFFER_TIMEOUT_SECONDS', 15),

    /*
    |------------------------------------------------------------------
    | Self-Serve Grace Window
    |------------------------------------------------------------------
    | Once the ranked queue is exhausted (no driver accepted, or none
    | were available), the ride stays "requested" so any driver can
    | still claim it via GET /v1/rides/available. If nobody does within
    | this window, the ride is auto-cancelled (reason: no_driver_available).
    */
    'self_serve_window_seconds' => env('RIDE_SELF_SERVE_TIMEOUT', 60),

];
