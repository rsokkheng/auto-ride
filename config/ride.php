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

    /*
    |------------------------------------------------------------------
    | Pickup No-Show Timeout
    |------------------------------------------------------------------
    | Set on arrive() — if the passenger hasn't boarded within this many
    | minutes, AutoCancelTimedOutRides (rides:auto-cancel-timed-out) cancels
    | the ride and frees the driver (available=true) again.
    */
    'pickup_timeout_minutes' => env('RIDE_PICKUP_TIMEOUT_MINUTES', 5),

    /*
    |------------------------------------------------------------------
    | Local Business Timezone
    |------------------------------------------------------------------
    | Anything that depends on the local wall clock — night/weekend/
    | holiday surcharges and recurring surge schedules — uses this
    | explicitly, so it stays correct regardless of app.timezone.
    */
    'local_timezone' => env('APP_LOCAL_TIMEZONE', 'Asia/Phnom_Penh'),

    /*
    |------------------------------------------------------------------
    | Service Area (bounding box)
    |------------------------------------------------------------------
    | Coordinates outside this box are treated as bad GPS (a simulator's
    | default location, a stale cached fix) and never stored as a trip
    | point or priced. Default: Cambodia — same box as the driver app.
    */
    'service_area' => [
        'min_lat' => (float) env('SERVICE_AREA_MIN_LAT', 10.4),
        'max_lat' => (float) env('SERVICE_AREA_MAX_LAT', 14.7),
        'min_lng' => (float) env('SERVICE_AREA_MIN_LNG', 102.3),
        'max_lng' => (float) env('SERVICE_AREA_MAX_LNG', 107.6),
    ],

];
