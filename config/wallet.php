<?php

return [
    'exchange_rate' => [
        'provider' => env('EXCHANGE_RATE_PROVIDER', 'exchangerate_api'),
        'api_key' => env('EXCHANGE_RATE_API_KEY'),
        'cache_ttl' => (int) env('EXCHANGE_RATE_CACHE_TTL', 3600),
        'live_mode' => filter_var(env('EXCHANGE_RATE_LIVE_MODE', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
