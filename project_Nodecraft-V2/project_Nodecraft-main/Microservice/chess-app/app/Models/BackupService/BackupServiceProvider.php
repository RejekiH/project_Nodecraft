<?php

namespace App\Modules\BackupService;

use Illuminate\Support\ServiceProvider;
use App\Modules\BackupService\Console\StartHeartbeatListenerCommand;
use App\Modules\BackupService\Console\TriggerBackupCommand;
use App\Modules\BackupService\Console\CleanBackupHistoryCommand;
use App\Modules\BackupService\Console\CheckStaleNodesCommand;

/**
 * BackupServiceProvider
 *
 * Mendaftarkan BackupService module ke aplikasi Laravel.
 * Sesuai arsitektur Modular Monolith: satu project Laravel,
 * setiap module mendaftarkan dirinya sendiri via ServiceProvider.
 *
 * Daftarkan di config/app.php pada array 'providers':
 *   App\Modules\BackupService\BackupServiceProvider::class,
 */
class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge konfigurasi backup
        $this->mergeConfigFrom(__DIR__ . '/../../../config/backup.php', 'backup');

        // Binding services sebagai singleton
        $this->app->singleton(
            \App\Modules\BackupService\Services\HeartbeatMonitorService::class
        );

        $this->app->singleton(
            \App\Modules\BackupService\Services\BackupService::class
        );

        $this->app->singleton(
            \App\Modules\BackupService\Services\UserServiceClient::class
        );
    }

    public function boot(): void
    {
        // Load routes module ini
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // Daftarkan Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                StartHeartbeatListenerCommand::class,
                TriggerBackupCommand::class,
                CleanBackupHistoryCommand::class,
                CheckStaleNodesCommand::class,
            ]);
        }
    }
}
