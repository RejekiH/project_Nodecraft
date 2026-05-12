<?php

namespace App\Modules\UserService;

use Illuminate\Support\ServiceProvider;
use App\Modules\UserService\Middleware\JwtAuthMiddleware;
use App\Modules\UserService\Middleware\InternalApiKeyMiddleware;

/**
 * UserServiceProvider
 * 
 * Mendaftarkan UserService module ke aplikasi Laravel.
 * Sesuai arsitektur Modular Monolith: satu project Laravel,
 * setiap module mendaftarkan dirinya sendiri via ServiceProvider.
 * 
 * Daftarkan di config/app.php pada array 'providers':
 *   App\Modules\UserService\UserServiceProvider::class,
 */
class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Binding services — bisa di-override saat testing
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
        // Load routes module ini
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // Daftarkan middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('jwt.auth',     JwtAuthMiddleware::class);
        $router->aliasMiddleware('internal.key', InternalApiKeyMiddleware::class);
    }
}
