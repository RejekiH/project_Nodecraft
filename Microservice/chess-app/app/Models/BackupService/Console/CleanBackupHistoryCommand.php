<?php

namespace App\Modules\BackupService\Console;

use App\Modules\BackupService\Services\BackupService;
use Illuminate\Console\Command;

/**
 * CleanBackupHistory Command
 *
 * Hapus file backup lama yang melewati batas retensi.
 * Jadwalkan di app/Console/Kernel.php:
 *
 *   // Bersihkan backup lama setiap hari pukul 03:00
 *   $schedule->command('backup-service:clean')->dailyAt('03:00');
 *
 * Fix: command ini sebelumnya juga menangani pengecekan stale nodes via flag --check-nodes.
 * Dua tanggung jawab berbeda dipisahkan — lihat CheckStaleNodesCommand untuk node check.
 */
class CleanBackupHistoryCommand extends Command
{
    protected $signature   = 'backup-service:clean';
    protected $description = 'Hapus file backup lama yang melewati batas retensi';

    public function handle(BackupService $backupService): int
    {
        $this->info('[BackupService] Membersihkan backup lama...');
        $backupService->pruneOldBackups();
        $this->info('[BackupService] Pembersihan selesai.');

        return Command::SUCCESS;
    }
}
