<?php

return [
    'env' => env('PESAPAL_ENV', 'sandbox'),
    'consumer_key' => env('PESAPAL_CONSUMER_KEY', ''),
    'consumer_secret' => env('PESAPAL_CONSUMER_SECRET', ''),
    'notification_id' => env('PESAPAL_NOTIFICATION_ID', ''),
    'callback_url' => env('PESAPAL_CALLBACK_URL', 'https://www.dukamkononi.com/api/payments/pesapal-callback'),
    'ipn_url' => env('PESAPAL_IPN_URL', 'https://www.dukamkononi.com/api/payments/pesapal-ipn'),
    'matangazo_price' => env('MATANGAZO_PRICE', 3000),
    'matangazo_duration_days' => env('MATANGAZO_DURATION_DAYS', 30),
    'test_mode' => env('ENABLE_TEST_MODE', false),
];