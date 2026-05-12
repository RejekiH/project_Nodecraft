<?php

namespace App\Modules\GameplayService;

use Illuminate\Support\ServiceProvider;
use App\Modules\GameplayService\Console\CleanGameHistoryCommand;
use App\Modules\GameplayService\Console\CheckTimeoutsCommand;

/**
 * GameplayServiceProvider
 *
 * Mendaftarkan GameplayService module ke aplikasi Laravel.
 * Sesuai arsitektur Modular Monolith: satu project Laravel,
 * setiap module mendaftarkan dirinya sendiri via ServiceProvider.
 *
 * Daftarkan di config/app.php pada array 'providers':
 *   App\Modules\GameplayService\GameplayServiceProvider::class,
 */
class GameplayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge konfigurasi gameplay
        $this->mergeConfigFrom(__DIR__ . '/../../../config/gameplay.php', 'gameplay');

        // Binding services sebagai singleton
        $this->app->singleton(
            \App\Modules\GameplayService\Services\ChessValidator::class
        );

        $this->app->singleton(
            \App\Modules\GameplayService\Services\RoomServiceClient::class
        );

        $this->app->singleton(
            \App\Modules\GameplayService\Services\GameplayService::class
        );
    }

    public function boot(): void
    {
        // Load routes module ini
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // Daftarkan Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanGameHistoryCommand::class,
                CheckTimeoutsCommand::class,
            ]);
        }
    }
}
