<?php

return [

    /*
    |------------------------------------------------------------------
    | Sequential Dispatch (Grab-style)
    |------------------------------------------------------------------
    | When a ride is requested, the top N nearby drivers are ranked by
    | DriverMatchingService and offered the ride one at a time — best
    | match first. If a driver doesn't accept within the offer window,
    | the ride is automatically offered to the next driver in the queue.
    */
    'match_radius_km'       => env('RIDE_MATCH_RADIUS_KM', 8),
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
