<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Explicit allow-list for every front-end that consumes this API.
    | Because supports_credentials is true, a wildcard origin is NOT allowed —
    | every origin must be listed explicitly or matched by a pattern.
    |
    | Patterns use PHP regex. The localhost pattern covers any port so that
    | all local development environments work without changing this file.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://admin.flisol.app',
        'https://certified.flisol.app',
        'https://subscription.flisol.app',
    ],

    // Matches http://localhost, http://localhost:4200, http://localhost:4300, etc.
    'allowed_origins_patterns' => [
        '/^http:\/\/localhost(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for Laravel Sanctum cookie-based authentication.
    'supports_credentials' => true,

];
