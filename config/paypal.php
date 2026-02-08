<?php

return [
    'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
    'client_id' => env('PAYPAL_CLIENT_ID', ''),
    'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
    'currency' => env('PAYPAL_CURRENCY', 'USD'),
    
    'sandbox' => [
        'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID', env('PAYPAL_CLIENT_ID', '')),
        'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET', env('PAYPAL_CLIENT_SECRET', '')),
    ],
    
    'live' => [
        'client_id' => env('PAYPAL_LIVE_CLIENT_ID', env('PAYPAL_CLIENT_ID', '')),
        'client_secret' => env('PAYPAL_LIVE_CLIENT_SECRET', env('PAYPAL_CLIENT_SECRET', '')),
    ],
];
