<?php

namespace App\Modules\RoomService;

use Illuminate\Support\ServiceProvider;
use App\Modules\RoomService\Middleware\JwtAuthMiddleware;
use App\Modules\RoomService\Middleware\InternalApiKeyMiddleware;

/**
 * RoomServiceProvider
 *
 * Mendaftarkan RoomService module ke aplikasi Laravel.
 * Sesuai arsitektur Modular Monolith: satu project Laravel,
 * setiap module mendaftarkan dirinya sendiri via ServiceProvider.
 *
 * Daftarkan di config/app.php pada array 'providers':
 *   App\Modules\UserService\UserServiceProvider::class,   ← sudah ada
 *   App\Modules\RoomService\RoomServiceProvider::class,   ← tambahkan ini
 */
class RoomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Binding services — bisa di-override saat testing
        $this->app->singleton(
            \App\Modules\RoomService\Services\UserServiceClient::class
        );

        $this->app->singleton(
            \App\Modules\RoomService\Services\RoomService::class
        );

        $this->app->singleton(
            \App\Modules\RoomService\Services\HeartbeatService::class
        );
    }

    public function boot(): void
    {
        // Load routes module ini
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // Daftarkan middleware aliases
        // CATATAN: jika UserServiceProvider sudah daftarkan 'jwt.auth' dan 'internal.key',
        // baris ini tidak perlu karena alias sudah ada. Namun aman untuk di-set ulang.
        $router = $this->app['router'];
        $router->aliasMiddleware('jwt.auth',     JwtAuthMiddleware::class);
        $router->aliasMiddleware('internal.key', InternalApiKeyMiddleware::class);
    }
}
