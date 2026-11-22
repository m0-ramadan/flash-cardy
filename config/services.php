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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'firebase' => [
        'web_api_key' => env('FIREBASE_WEB_API_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v23.0'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect_uri' => env('APPLE_REDIRECT_URI'),
    ],

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
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        'integration_id' => env('PAYMOB_INTEGRATION_ID', '5463067'),
        'iframe_id' => env('PAYMOB_IFRAME_ID', '995308'),
        'mode' => env('PAYMOB_MODE', 'test'),        // test أو live
        'currency' => env('PAYMOB_CURRENCY', 'EGP'),
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),

        // Endpoints
        'auth_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com').'/api/auth/tokens',
        'payment_links_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com').'/api/ecommerce/payment-links',
        'intention_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com').'/v1/intention/',

        // Public key
        'public_key' => env('PAYMOB_PUBLIC_KEY'),

        // Features
        'tokenization_enabled' => env('PAYMOB_TOKENIZATION_ENABLED', false),
        'save_card_enabled' => env('PAYMOB_SAVE_CARD_ENABLED', true),

        // IP Whitelist
        'allowed_ips' => explode(',', env('PAYMOB_ALLOWED_IPS', '')),

        // URLs
        'callback_url' => env('PAYMOB_CALLBACK_URL'),
        'return_url' => env('PAYMOB_RETURN_URL'),
        'cancel_url' => env('PAYMOB_CANCEL_URL'),

    ],
    'nowpayments' => [
        'base_url' => env('NOWPAYMENTS_BASE_URL', 'https://api.nowpayments.io/v1'),
        'api_key' => env('NOWPAYMENTS_API_KEY'),
        'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
        'store_currency' => strtoupper(env('NOWPAYMENTS_STORE_CURRENCY', 'EGP')),
        'price_currency' => strtolower(env('NOWPAYMENTS_PRICE_CURRENCY', 'USD')),
        'payout_currency' => strtolower(env('NOWPAYMENTS_PAYOUT_CURRENCY', 'usdttrc20')),
        'default_pay_currency' => strtolower(env('NOWPAYMENTS_DEFAULT_PAY_CURRENCY', 'usdttrc20')),
        'allowed_pay_currencies' => array_values(array_filter(array_map(
            fn ($currency) => strtolower(trim($currency)),
            explode(',', env('NOWPAYMENTS_ALLOWED_PAY_CURRENCIES', 'usdttrc20,usdtbsc'))
        ))),
        'is_fixed_rate' => filter_var(env('NOWPAYMENTS_FIXED_RATE', false), FILTER_VALIDATE_BOOLEAN),
        'is_fee_paid_by_user' => filter_var(env('NOWPAYMENTS_FEE_PAID_BY_USER', false), FILTER_VALIDATE_BOOLEAN),
        'success_url' => env('NOWPAYMENTS_SUCCESS_URL'),
        'cancel_url' => env('NOWPAYMENTS_CANCEL_URL'),
        'connect_timeout' => (int) env('NOWPAYMENTS_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('NOWPAYMENTS_TIMEOUT', 20),
    ],
    'like4app' => [
        'device_id' => env('LIKE4APP_DEVICE_ID'),
        'security_code' => env('LIKE4APP_SECURITY_CODE'),
        'email' => env('LIKE4APP_EMAIL'),
        'lang_id' => env('LIKE4APP_LANG_ID', '1'),
        'secret_key' => env('LIKE4APP_SECRET_KEY'),
        'secret_iv' => env('LIKE4APP_SECRET_IV'),
        'phone' => env('LIKE4APP_PHONE'),
        'hash_key' => env('LIKE4APP_HASH_KEY'),
        'base_url' => env('LIKE4APP_BASE_URL', 'https://taxes.like4app.com/online'),
    ],
    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
    ],
];
