<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],


    'paymob' => [
        'username' => env('PAYMOB_USERNAME'),
        'password' => env('PAYMOB_PASSWORD'),
        'api_key' => env('PAYMOB_API_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        'integration_id' => env('PAYMOB_INTEGRATION_ID', '5463067'),
        'iframe_id' => env('PAYMOB_IFRAME_ID', '995308'),
        'mode' => env('PAYMOB_MODE', 'test'),        // test أو live
        'currency' => env('PAYMOB_CURRENCY', 'EGP'),
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),

        // Endpoints
        'auth_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com') . '/api/auth/tokens',
        'payment_links_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com') . '/api/ecommerce/payment-links',

        // Public key
        'public_key' => env('PAYMOB_PUBLIC_KEY'),

        // Features
        'tokenization_enabled' => env('PAYMOB_TOKENIZATION_ENABLED', false),
        'save_card_enabled' => env('PAYMOB_SAVE_CARD_ENABLED', true),

        // IP Whitelist
        'allowed_ips' => explode(',', env('PAYMOB_ALLOWED_IPS', '')),

        // URLs
        'callback_url' => env('PAYMOB_CALLBACK_URL'),
        // 'return_url' => env('PAYMOB_RETURN_URL'),
        // 'cancel_url' => env('PAYMOB_CANCEL_URL'),

    ],
    'like4app' => [
        'device_id'     => env('LIKE4APP_DEVICE_ID', '79e55b05dbdb440283797f15c8c688d96f0edea9c4821f0ad0ec2036030370f8'),
        'security_code' => env('LIKE4APP_SECURITY_CODE', '01c75aa369cbf785f169c0e045b963f74088f16127ece710e050216040bf3460'),
        'email'         => env('LIKE4APP_EMAIL', 'Mr.mtttm@gmail.com'),
        'lang_id'       => env('LIKE4APP_LANG_ID', '1'),
        'secret_key' => env('LIKE4APP_SECRET_KEY', 't-3zafRa'),
        'secret_iv'  => env('LIKE4APP_SECRET_IV', 'St@cE4eZ'),
    ],
    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model'   => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
    ],
];
