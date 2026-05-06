<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk JWT token yang digunakan user-service.
    | Secret key harus sama di semua service agar token bisa diverifikasi
    | oleh service lain (Room Service, Gameplay Service).
    |
    */

    'secret' => env('JWT_SECRET'),

    // Durasi access token dalam detik (default: 1 jam)
    'expires_in' => (int) env('JWT_EXPIRES_IN', 3600),

    // Durasi refresh token dalam detik (default: 7 hari)
    'refresh_expires_in' => (int) env('JWT_REFRESH_EXPIRES_IN', 604800),

    'algorithm' => 'HS256',

];
