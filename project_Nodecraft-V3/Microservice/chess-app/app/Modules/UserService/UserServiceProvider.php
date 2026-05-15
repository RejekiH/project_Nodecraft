<?php

namespace App\Modules\UserService;

use Illuminate\Support\ServiceProvider;

/**
 * UserServiceProvider
 *
 * FIX: Hapus $router->aliasMiddleware() dari boot().
 * Alias 'jwt.auth' dan 'internal.key' sudah didaftarkan di
 * app/Http/Kernel.php → $routeMiddleware sebagai single source of truth.
 * Mendaftarkan ulang di sini menyebabkan konflik saat urutan boot berubah.
 */
class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Modules\UserService\Services\JwtService::class);
        $this->app->singleton(\App\Modules\UserService\Services\UserService::class);
        $this->app->singleton(\App\Modules\UserService\Services\HeartbeatService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\UserService\Console\SendHeartbeatCommand::class,
            ]);
        }
    }
}
