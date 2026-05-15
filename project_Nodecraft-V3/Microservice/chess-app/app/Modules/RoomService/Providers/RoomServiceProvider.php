<?php

namespace App\Modules\RoomService;

use Illuminate\Support\ServiceProvider;

/*
|--------------------------------------------------------------------------
| FIX: Hapus registrasi alias middleware duplikat dari boot().
|
| Kode asli RoomServiceProvider mendaftarkan 'jwt.auth' dan 'internal.key'
| dengan class dari namespace RoomService (bukan UserService).
| Ini menimpa alias yang sudah didaftarkan UserServiceProvider
| → middleware yang aktif bergantung pada urutan boot provider.
|
| Solusi: hapus semua aliasMiddleware() dari sini.
| Alias sudah ada di app/Http/Kernel.php → $routeMiddleware dan
| menunjuk ke class di namespace UserService (yang correct).
|--------------------------------------------------------------------------
*/
class RoomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // FIX: Hapus baris ini yang sebelumnya ada:
        // $router = $this->app['router'];
        // $router->aliasMiddleware('jwt.auth', JwtAuthMiddleware::class);   ← class RoomService, bukan UserService!
        // $router->aliasMiddleware('internal.key', InternalApiKeyMiddleware::class);
        //
        // Duplikasi alias ini menyebabkan konflik dengan UserServiceProvider.
        // Sekarang alias hanya ada di app/Http/Kernel.php.

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\RoomService\Console\SendHeartbeatCommand::class,
            ]);
        }
    }
}
