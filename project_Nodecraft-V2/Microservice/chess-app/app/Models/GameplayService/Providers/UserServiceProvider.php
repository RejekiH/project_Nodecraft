<?php

namespace App\Modules\UserService;

use Illuminate\Support\ServiceProvider;

/*
|--------------------------------------------------------------------------
| FIX: Hapus registrasi alias middleware dari boot().
|
| Sebelumnya boot() mendaftarkan ulang 'jwt.auth' dan 'internal.key'
| via $router->aliasMiddleware(). Ini menimbulkan masalah:
|   1. RoomServiceProvider mendaftarkan alias yang sama dengan class berbeda
|      → alias bisa tertimpa tergantung urutan boot provider
|   2. Duplikasi dengan Http/Kernel.php yang sekarang sudah menjadi
|      single source of truth untuk alias middleware
|
| Alias sekarang hanya ada di app/Http/Kernel.php → $routeMiddleware
|--------------------------------------------------------------------------
*/
class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\UserService\Services\JwtService::class
        );

        $this->app->singleton(
            \App\Modules\UserService\Services\UserService::class
        );

        $this->app->singleton(
            \App\Modules\UserService\Services\HeartbeatService::class
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // FIX: Hapus baris ini yang sebelumnya ada:
        // $router = $this->app['router'];
        // $router->aliasMiddleware('jwt.auth', JwtAuthMiddleware::class);
        // $router->aliasMiddleware('internal.key', InternalApiKeyMiddleware::class);
        //
        // Alias sekarang hanya didaftarkan di app/Http/Kernel.php → $routeMiddleware
    }
}
