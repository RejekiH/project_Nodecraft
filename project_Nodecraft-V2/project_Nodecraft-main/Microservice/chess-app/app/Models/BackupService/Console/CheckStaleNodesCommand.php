<?php

namespace App\Modules\BackupService\Console;

use App\Modules\BackupService\Services\HeartbeatMonitorService;
use Illuminate\Console\Command;

/**
 * CheckStaleNodes Command
 *
 * Cek dan update status node backup yang tidak aktif (stale).
 * Jadwalkan di app/Console/Kernel.php:
 *
 *   // Cek stale nodes setiap menit
 *   $schedule->command('backup-service:check-nodes')->everyMinute();
 *
 * Fix: sebelumnya tanggung jawab ini digabung dengan CleanBackupHistoryCommand
 * via flag --check-nodes, melanggar single-responsibility.
 * Operator yang menjalankan backup:clean tidak menyadari bahwa node check
 * tidak berjalan bersamaan. Dipisah menjadi command tersendiri agar
 * jadwal dan tanggung jawab masing-masing jelas.
 */
class CheckStaleNodesCommand extends Command
{
    protected $signature   = 'backup-service:check-nodes';
    protected $description = 'Cek dan update status node backup yang tidak aktif';

    public function handle(HeartbeatMonitorService $heartbeatMonitor): int
    {
        $this->info('[BackupService] Mengecek stale nodes...');
        $heartbeatMonitor->checkStaleNodes();
        $this->info('[BackupService] Pengecekan node selesai.');

        return Command::SUCCESS;
    }
}
