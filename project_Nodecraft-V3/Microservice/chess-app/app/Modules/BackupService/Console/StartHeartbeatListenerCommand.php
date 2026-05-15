<?php

namespace App\Modules\BackupService\Console;

use App\Modules\BackupService\Services\HeartbeatMonitorService;
use Illuminate\Console\Command;

/**
 * StartHeartbeatListener Command
 *
 * Mulai consume heartbeat dari RabbitMQ secara terus-menerus.
 * Jalankan sebagai long-running process menggunakan Supervisor.
 *
 * Konfigurasi Supervisor (/etc/supervisor/conf.d/backup-heartbeat.conf):
 *
 *   [program:backup-heartbeat]
 *   command=php /var/www/artisan backup-service:heartbeat-listen
 *   autostart=true
 *   autorestart=true
 *   stderr_logfile=/var/log/supervisor/backup-heartbeat.err.log
 *   stdout_logfile=/var/log/supervisor/backup-heartbeat.out.log
 *
 * Atau jalankan manual untuk testing:
 *   php artisan backup-service:heartbeat-listen
 */
class StartHeartbeatListenerCommand extends Command
{
    protected $signature   = 'backup-service:heartbeat-listen';
    protected $description = 'Mulai consume heartbeat dari RabbitMQ (blocking)';

    public function handle(HeartbeatMonitorService $heartbeatMonitor): int
    {
        $this->info('[BackupService] Memulai listener heartbeat RabbitMQ...');
        $this->info('Tekan Ctrl+C untuk berhenti.');

        $heartbeatMonitor->startConsuming(function () {
            // Callback dipanggil setiap kali pesan diterima
            // Berguna untuk testing — bisa di-override
        });

        return Command::SUCCESS;
    }
}
