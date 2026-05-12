<?php

namespace App\Modules\RoomService\Console;

use App\Modules\RoomService\Services\HeartbeatService;
use Illuminate\Console\Command;

/**
 * SendHeartbeat Command
 *
 * Mengirim event heartbeat ke RabbitMQ secara periodik.
 * Sesuai arsitektur: semua module → RabbitMQ (event: heartbeat) → BackupService.
 *
 * Jadwalkan di app/Console/Kernel.php:
 *   $schedule->command('room-service:heartbeat')->everyMinute();
 *
 * Atau jalankan manual:
 *   php artisan room-service:heartbeat
 */
class SendHeartbeatCommand extends Command
{
    protected $signature   = 'room-service:heartbeat';
    protected $description = 'Kirim heartbeat RoomService ke RabbitMQ';

    public function handle(HeartbeatService $heartbeatService): int
    {
        $heartbeatService->publish();
        $this->info('Heartbeat RoomService berhasil dikirim.');
        return Command::SUCCESS;
    }
}
