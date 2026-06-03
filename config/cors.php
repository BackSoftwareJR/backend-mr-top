<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Strict CORS for api.wenando.com — Sanctum SPA cookie auth.
    | See docs/1_ARCHITECTURE_&_SECURITY.md §3 and docs/7_SECURITY_&_COMPLIANCE_GUIDELINES.md §7.3.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => match (env('APP_ENV', 'production')) {
        'local' => [
            'https://wenando.com',
            'https://www.wenando.com',
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ],
        'staging' => [
            'https://wenando.com',
            'https://www.wenando.com',
            'https://staging.wenando.com',
        ],
        default => [
            'https://wenando.com',
            'https://www.wenando.com',
        ],
    },

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
