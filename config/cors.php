<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'api/sync/*'],

    'allowed_methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],

    /*
    | Explicit web origins. A wildcard ('*') cannot be used here because the
    | web client flushes telemetry via navigator.sendBeacon, which the browser
    | always sends with credentials mode 'include'. Per the CORS spec a
    | credentialed request may not receive 'Access-Control-Allow-Origin: *' —
    | the response must echo the concrete request origin. Listing origins
    | explicitly makes Laravel's CORS handler reflect the matched origin and
    | (with supports_credentials below) emit 'Access-Control-Allow-Credentials'.
    | Override via the CORS_ALLOWED_ORIGINS env var (comma-separated).
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'https://flagship.squirby.ai,https://app.squirby.ai,http://localhost:5173'
        ))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'X-Device-Id', 'X-Idempotency-Key'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
