<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /*
    |--------------------------------------------------------------------------
    | FIX: schedule() sebelumnya kosong — semua fitur operasional tidak jalan:
    |   - Timeout game tidak pernah terdeteksi
    |   - Backup tidak pernah berjalan otomatis
    |   - Heartbeat tidak pernah terkirim ke RabbitMQ
    |   - Node check tidak pernah berjalan
    |
    | DEPLOYMENT: Pastikan cron aktif di VM-Web:
    |   sudo crontab -e
    |   * * * * * cd /var/www/chess-app && php artisan schedule:run >> /dev/null 2>&1
    |--------------------------------------------------------------------------
    */
    protected function schedule(Schedule $schedule): void
    {
        // GameplayService: deteksi pemain yang kehabisan waktu
        // Artisan: gameplay:check-timeouts (didaftarkan GameplayServiceProvider)
        $schedule->command('gameplay:check-timeouts')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        // BackupService: full backup harian pukul 02:00
        // Artisan: backup-service:backup --type=full (didaftarkan BackupServiceProvider)
        $schedule->command('backup-service:backup --type=full')
            ->dailyAt('02:00')
            ->withoutOverlapping(120)
            ->runInBackground();

        // BackupService: incremental backup setiap jam
        // Artisan: backup-service:backup --type=incremental
        $schedule->command('backup-service:backup --type=incremental')
            ->hourly()
            ->withoutOverlapping(50)
            ->runInBackground();

        // BackupService: hapus file backup lama pukul 03:00
        // Artisan: backup-service:clean (didaftarkan BackupServiceProvider)
        $schedule->command('backup-service:clean')
            ->dailyAt('03:00')
            ->withoutOverlapping(30)
            ->runInBackground();

        // BackupService: cek node yang tidak kirim heartbeat
        // Artisan: backup-service:check-nodes (didaftarkan BackupServiceProvider)
        $schedule->command('backup-service:check-nodes')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        // UserService: kirim heartbeat ke RabbitMQ
        // Artisan: user-service:heartbeat (didaftarkan UserServiceProvider via Console/)
        $schedule->command('user-service:heartbeat')
            ->everyMinute()
            ->withoutOverlapping(3)
            ->runInBackground();

        // RoomService: kirim heartbeat ke RabbitMQ
        // Artisan: room-service:heartbeat (didaftarkan RoomServiceProvider via Console/)
        $schedule->command('room-service:heartbeat')
            ->everyMinute()
            ->withoutOverlapping(3)
            ->runInBackground();
    }

    protected function commands(): void
    {
        // Load commands dari folder app/Console/Commands (default Laravel)
        $this->load(__DIR__ . '/Commands');

        // CATATAN: Commands module (gameplay:check-timeouts, backup-service:*, dll.)
        // TIDAK perlu di-load di sini karena sudah didaftarkan masing-masing
        // ServiceProvider via $this->commands([...]) di method boot().
        // Mendaftarkan ulang di sini akan menyebabkan duplikasi command.

        require base_path('routes/console.php');
    }
}
