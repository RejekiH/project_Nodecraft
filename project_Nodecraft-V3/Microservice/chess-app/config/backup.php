<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage
    |--------------------------------------------------------------------------
    */
    'storage_path'    => env('BACKUP_PATH', storage_path('backups')),
    'retention_days'  => (int) env('BACKUP_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    */
    'heartbeat_stale_minutes' => (int) env('HEARTBEAT_STALE_MINUTES', 3),
    'offline_threshold'       => (int) env('HEARTBEAT_OFFLINE_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Database targets
    |--------------------------------------------------------------------------
    */
    'databases' => ['chess_app'],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */
    'user_service_url' => env('USER_SERVICE_URL', env('APP_URL', 'http://localhost')),
    'http_timeout'     => (int) env('BACKUP_HTTP_TIMEOUT', 10),

];
