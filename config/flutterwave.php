<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flutterwave API Keys
    |--------------------------------------------------------------------------
    |
    | Your Flutterwave public and secret API keys. These are used for all
    | payment API interactions including initiating payments, verifying
    | transactions, and handling webhooks.
    |
    */

    'public_key' => env('FLUTTERWAVE_PUBLIC_KEY', ''),

    'secret_key' => env('FLUTTERWAVE_SECRET_KEY', ''),

    'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The secret hash used to verify that incoming webhook requests are
    | genuinely from Flutterwave. Set this to a random string and configure
    | the same value in your Flutterwave dashboard.
    |
    */

    'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'staging' for test mode or 'live' for production. This controls
    | which Flutterwave environment is used for API calls.
    |
    */

    'env' => env('FLUTTERWAVE_ENV', 'staging'),

    /*
    |--------------------------------------------------------------------------
    | Payment Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for payment processing. The currency is XAF (Central
    | African CFA franc) since DancyMeals operates in Cameroon.
    |
    */

    'currency' => 'XAF',

    'country' => 'CM',

    'payment_methods' => 'mobilemoneycameroon',

    /*
    |--------------------------------------------------------------------------
    | Commission
    |--------------------------------------------------------------------------
    |
    | Default platform commission percentage taken from each transaction.
    | This can be overridden per cook via admin settings.
    |
    */

    'default_commission_percentage' => 10,

];
