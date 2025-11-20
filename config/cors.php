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

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'billing/*',
        'organization-leader/*',
        'admin/payment-controls/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        'https://bodyf1rst.net',
        'https://www.bodyf1rst.net',
        'https://admin-bodyf1rst.com',
        'https://www.admin-bodyf1rst.com',
        // Development origins - only allowed in local/staging environments
        env('APP_ENV') !== 'production' ? 'http://localhost:8100' : null,
        env('APP_ENV') !== 'production' ? 'http://localhost:4200' : null,
        env('APP_ENV') !== 'production' ? 'http://localhost:4201' : null,
        env('APP_ENV') !== 'production' ? 'http://localhost:4202' : null,
        env('APP_ENV') !== 'production' ? 'capacitor://localhost' : null,
        env('APP_ENV') !== 'production' ? 'ionic://localhost' : null,
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
