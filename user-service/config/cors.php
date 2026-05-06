<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Configuration - User Service
    |--------------------------------------------------------------------------
    |
    | Untuk Fase 2 (development), izinkan semua origin.
    | Pada Fase 8+ (production VMware), ubah allowed_origins ke IP spesifik.
    |
    */

    'paths'                    => ['api/*'],
    'allowed_methods'          => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins'          => ['*'],    // Ubah ke IP frontend di production
    'allowed_origins_patterns' => [],
    'allowed_headers'          => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Internal-Key',   // untuk inter-service communication
    ],
    'exposed_headers'          => [],
    'max_age'                  => 86400,    // 24 jam cache preflight
    'supports_credentials'     => false,
];
