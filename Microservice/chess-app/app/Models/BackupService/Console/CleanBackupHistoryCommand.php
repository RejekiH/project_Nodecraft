<?php

namespace App\Modules\BackupService\Console;

use App\Modules\BackupService\Services\BackupService;
use App\Modules\BackupService\Services\HeartbeatMonitorService;
use Illuminate\Console\Command;

/**
 * CleanBackupHistory Command
 *
 * Hapus file backup lama dan cek stale nodes.
 * Jadwalkan di app/Console/Kernel.php:
 *
 *   // Bersihkan backup lama setiap hari pukul 03:00
 *   $schedule->command('backup-service:clean')->dailyAt('03:00');
 *
 *   // Cek stale nodes setiap menit
 *   $schedule->command('backup-service:clean --check-nodes')->everyMinute();
 */
class CleanBackupHistoryCommand extends Command
{
    protected $signature   = 'backup-service:clean
                                {--check-nodes : Cek dan update status node yang tidak aktif}';
    protected $description = 'Hapus backup lama dan opsional cek stale nodes';

    public function handle(
        BackupService           $backupService,
        HeartbeatMonitorService $heartbeatMonitor
    ): int {
        if ($this->option('check-nodes')) {
            $this->info('[BackupService] Mengecek stale nodes...');
            $heartbeatMonitor->checkStaleNodes();
            $this->info('[BackupService] Pengecekan node selesai.');
        } else {
            $this->info('[BackupService] Membersihkan backup lama...');
            $backupService->pruneOldBackups();
            $this->info('[BackupService] Pembersihan selesai.');
        }

        return Command::SUCCESS;
    }
}
