<?php

namespace App\Modules\RoomService;

use Illuminate\Support\ServiceProvider;

/**
 * RoomServiceProvider
 *
 * FIX: Hapus $router->aliasMiddleware() yang menduplikasi alias dari
 * UserServiceProvider dan Http/Kernel.php. Sekarang alias hanya ada
 * di satu tempat: app/Http/Kernel.php → $routeMiddleware.
 */
class RoomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Modules\RoomService\Services\UserServiceClient::class);
        $this->app->singleton(\App\Modules\RoomService\Services\RoomService::class);
        $this->app->singleton(\App\Modules\RoomService\Services\HeartbeatService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\RoomService\Console\SendHeartbeatCommand::class,
            ]);
        }
    }
}
