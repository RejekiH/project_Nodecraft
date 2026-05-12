<?php

namespace App\Modules\BackupService\Console;

use App\Modules\BackupService\Services\BackupService;
use Illuminate\Console\Command;

/**
 * TriggerBackup Command
 *
 * Jalankan backup secara manual via Artisan.
 * Juga dipanggil oleh scheduler untuk backup otomatis.
 *
 * Jadwalkan di app/Console/Kernel.php:
 *   // Backup penuh setiap hari pukul 02:00
 *   $schedule->command('backup-service:backup --type=full')->dailyAt('02:00');
 *
 *   // Backup incremental setiap jam
 *   $schedule->command('backup-service:backup --type=incremental')->hourly();
 *
 * Atau jalankan manual:
 *   php artisan backup-service:backup
 *   php artisan backup-service:backup --type=incremental
 */
class TriggerBackupCommand extends Command
{
    protected $signature   = 'backup-service:backup {--type=full : Tipe backup (full|incremental)}';
    protected $description = 'Jalankan backup MongoDB';

    public function handle(BackupService $backupService): int
    {
        $type = $this->option('type');

        if (!in_array($type, ['full', 'incremental'])) {
            $this->error("Tipe backup tidak valid: {$type}. Gunakan 'full' atau 'incremental'.");
            return Command::FAILURE;
        }

        $this->info("[BackupService] Memulai backup {$type}...");

        $record = match ($type) {
            'incremental' => $backupService->runIncrementalBackup('scheduler'),
            default       => $backupService->runFullBackup('scheduler'),
        };

        if ($record->status === 'success') {
            $this->info("[BackupService] Backup berhasil: {$record->file_path}");
            $this->line("  Ukuran: " . number_format($record->file_size / 1024, 2) . " KB");
            return Command::SUCCESS;
        }

        $this->error("[BackupService] Backup gagal: {$record->error}");
        return Command::FAILURE;
    }
}
