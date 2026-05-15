<?php

namespace App\Modules\GameplayService\Console;

use App\Modules\GameplayService\Services\HeartbeatService;
use Illuminate\Console\Command;

/**
 * Kirim heartbeat GameplayService ke RabbitMQ.
 *
 * Jadwalkan di app/Console/Kernel.php:
 *   $schedule->command('gameplay-service:heartbeat')->everyMinute();
 *
 * Atau jalankan manual:
 *   php artisan gameplay-service:heartbeat
 */
class SendHeartbeatCommand extends Command
{
    protected $signature   = 'gameplay-service:heartbeat';
    protected $description = 'Kirim heartbeat GameplayService ke RabbitMQ';

    public function handle(HeartbeatService $heartbeatService): int
    {
        $heartbeatService->send();
        $this->info('Heartbeat GameplayService berhasil dikirim.');
        return Command::SUCCESS;
    }
}
