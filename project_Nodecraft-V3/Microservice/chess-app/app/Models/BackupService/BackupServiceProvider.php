<?php

namespace App\Modules\BackupService;

use Illuminate\Support\ServiceProvider;
use App\Modules\BackupService\Console\StartHeartbeatListenerCommand;
use App\Modules\BackupService\Console\TriggerBackupCommand;
use App\Modules\BackupService\Console\CleanBackupHistoryCommand;
use App\Modules\BackupService\Console\CheckStaleNodesCommand;

class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/backup.php', 'backup');

        $this->app->singleton(\App\Modules\BackupService\Services\HeartbeatMonitorService::class);
        $this->app->singleton(\App\Modules\BackupService\Services\BackupService::class);
        $this->app->singleton(\App\Modules\BackupService\Services\RestoreService::class);
        $this->app->singleton(\App\Modules\BackupService\Services\UserServiceClient::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

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
