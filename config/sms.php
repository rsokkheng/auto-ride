<?php

return [
    'driver' => env('SMS_DRIVER', 'log'),

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],

    'nexmo' => [
        'key'    => env('NEXMO_KEY'),
        'secret' => env('NEXMO_SECRET'),
        'from'   => env('NEXMO_FROM', 'AutoRide'),
    ],

    'http' => [
        'url'     => env('SMS_HTTP_URL'),
        'api_key' => env('SMS_HTTP_API_KEY'),
        'from'    => env('SMS_HTTP_FROM', 'AutoRide'),
    ],
];
