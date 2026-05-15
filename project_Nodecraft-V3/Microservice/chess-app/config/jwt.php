<?php

/*
|--------------------------------------------------------------------------
| JWT Configuration
|--------------------------------------------------------------------------
|
| FIX: File ini wajib ada karena JwtService.php memanggil:
|   config('jwt.secret')           → JWT_SECRET di .env
|   config('jwt.expires_in')       → JWT_EXPIRES_IN di .env
|   config('jwt.refresh_expires_in') → JWT_REFRESH_EXPIRES_IN di .env
|
| Tanpa file ini, semua config('jwt.*') return null dan JwtService
| throw RuntimeException("JWT_SECRET belum dikonfigurasi") saat boot.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Secret key untuk signing dan verifikasi JWT token
    |----------------------------------------------------------------------
    | WAJIB diisi di .env. Generate dengan:
    |   php -r "echo base64_encode(random_bytes(64));"
    */
    'secret' => env('JWT_SECRET'),

    /*
    |----------------------------------------------------------------------
    | Masa aktif access token (dalam detik)
    |----------------------------------------------------------------------
    | Default: 3600 = 1 jam
    */
    'expires_in' => (int) env('JWT_EXPIRES_IN', 3600),

    /*
    |----------------------------------------------------------------------
    | Masa aktif refresh token (dalam detik)
    |----------------------------------------------------------------------
    | Default: 604800 = 7 hari
    */
    'refresh_expires_in' => (int) env('JWT_REFRESH_EXPIRES_IN', 604800),

];
