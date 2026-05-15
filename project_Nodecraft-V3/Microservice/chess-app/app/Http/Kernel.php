<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | FIX: Tambahkan 'jwt.auth' dan 'internal.key' sebagai satu-satunya
    | sumber pendaftaran alias. Sebelumnya tidak ada di sini — kedua alias
    | hanya didaftarkan di ServiceProvider via $router->aliasMiddleware()
    | yang rentan konflik jika urutan boot berubah.
    |
    | Dengan mendaftarkan di Kernel (Laravel 9: $routeMiddleware), alias
    | tersedia sejak bootstrap — tidak bergantung pada urutan provider boot.
    |
    | PENTING: Setelah ini, hapus baris $router->aliasMiddleware('jwt.auth')
    | dan $router->aliasMiddleware('internal.key') dari boot() di:
    |   - app/Models/UserService/UserServiceProvider.php
    |   - app/Models/RoomService/RoomServiceProvider.php
    |
    */
    protected $routeMiddleware = [
        'auth'             => \App\Http\Middleware\Authenticate::class,
        'auth.basic'       => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session'     => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers'    => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can'              => \Illuminate\Auth\Middleware\Authorize::class,
        'guest'            => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed'           => \App\Http\Middleware\ValidateSignature::class,
        'throttle'         => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'         => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // FIX: Chess App middleware aliases
        'jwt.auth'         => \App\Models\UserService\Middleware\JwtAuthMiddleware::class,
        'internal.key'     => \App\Models\UserService\Middleware\InternalApiKeyMiddleware::class,
    ];
}
