<?php

/*
 * ABA PayWay (https://developer.payway.com.kh). Keys come from ABA
 * (paywaysales@ababank.com). Sandbox by default:
 *   sandbox    https://checkout-sandbox.payway.com.kh
 *   production https://checkout.payway.com.kh   ← set PAYWAY_BASE_URL to this to go live
 */
return [
    'base_url'     => env('PAYWAY_BASE_URL', 'https://checkout-sandbox.payway.com.kh'),
    'merchant_id'  => env('PAYWAY_MERCHANT_ID'),
    // "Public key" from ABA — signs every request (HMAC-SHA512).
    'api_key'      => env('PAYWAY_API_KEY'),
    // Key PayWay signs callbacks with (X-PayWay-HMAC-SHA512). Defaults to the API key.
    'callback_key' => env('PAYWAY_CALLBACK_KEY', env('PAYWAY_API_KEY')),
    'currency'     => env('PAYWAY_CURRENCY', 'KHR'),
    // How long a KHQR can be paid (minutes, PayWay min 3).
    'lifetime'     => (int) env('PAYWAY_QR_LIFETIME', 15),
    'qr_template'  => env('PAYWAY_QR_TEMPLATE', 'template3_color'),
    // Domain must be whitelisted in the PayWay merchant profile.
    'callback_url' => env('PAYWAY_CALLBACK_URL'),
];
