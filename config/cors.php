<?php

/**
 * CORS configuration driven by environment variables so that each deployment
 * (local, staging, production) can allow its own front-end origins without
 * editing code.
 *
 * CORS_ALLOWED_ORIGINS          -> comma separated list of exact origins
 * CORS_ALLOWED_ORIGIN_PATTERNS  -> comma separated list of regex patterns (quote the value in .env because of "#")
 */
$origins = array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')))));
$patterns = array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')))));
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => $patterns,
    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Request-Id',
        'X-Session-Id',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'api-key',
    ],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 600,
    'supports_credentials' => true,
];
