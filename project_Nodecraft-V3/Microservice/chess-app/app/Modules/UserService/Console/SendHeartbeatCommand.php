<?php

namespace App\Modules\UserService\Console;

use App\Modules\UserService\Services\HeartbeatService;
use Illuminate\Console\Command;

/**
 * SendHeartbeat Command
 * 
 * Mengirim event heartbeat ke RabbitMQ secara periodik.
 * Sesuai arsitektur: semua module → RabbitMQ (event: heartbeat) → BackupService.
 * 
 * Jadwalkan di app/Console/Kernel.php:
 *   $schedule->command('user-service:heartbeat')->everyMinute();
 * 
 * Atau jalankan manual:
 *   php artisan user-service:heartbeat
 */
class SendHeartbeatCommand extends Command
{
    protected $signature   = 'user-service:heartbeat';
    protected $description = 'Kirim heartbeat UserService ke RabbitMQ';

    public function handle(HeartbeatService $heartbeatService): int
    {
        $heartbeatService->publish();
        $this->info('Heartbeat UserService berhasil dikirim.');
        return Command::SUCCESS;
    }
}
