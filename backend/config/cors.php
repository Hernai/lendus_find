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

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://192.168.1.65:5173',
        'https://f6a07823cbf3.ngrok-free.app',
        'https://eaac7437a876.ngrok-free.app',
        'https://*.ngrok-free.app',
        'https://*.ngrok.io',
        // Capacitor (apps native iOS/Android)
        'capacitor://localhost',
        'ionic://localhost',
        'http://localhost',
        'https://localhost',
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.ngrok-free\.app$#',
        '#^https://.*\.ngrok\.io$#',
        // Capacitor variantes con/sin puerto.
        '#^capacitor://localhost(:\d+)?$#',
        '#^ionic://localhost(:\d+)?$#',
        '#^https?://localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-App-Update-Available'],

    'max_age' => 0,

    // El auth desde frontend es 100% Bearer (no cookies de Sanctum).
    // Mantenemos en false para no requerir credentials en CORS preflight.
    'supports_credentials' => false,

];
