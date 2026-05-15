<?php

namespace App\Modules\GameplayService;

use Illuminate\Support\ServiceProvider;
use App\Modules\GameplayService\Console\CleanGameHistoryCommand;
use App\Modules\GameplayService\Console\CheckTimeoutsCommand;
use App\Modules\GameplayService\Console\SendHeartbeatCommand;

class GameplayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/gameplay.php', 'gameplay');

        $this->app->singleton(\App\Modules\GameplayService\Services\ChessValidator::class);
        $this->app->singleton(\App\Modules\GameplayService\Services\RoomServiceClient::class);
        $this->app->singleton(\App\Modules\GameplayService\Services\GameplayService::class);
        $this->app->singleton(\App\Modules\GameplayService\Services\HeartbeatService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanGameHistoryCommand::class,
                CheckTimeoutsCommand::class,
                SendHeartbeatCommand::class,
            ]);
        }
    }
}
