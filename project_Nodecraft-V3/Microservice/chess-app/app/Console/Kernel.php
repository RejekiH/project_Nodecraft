<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ── GameplayService ───────────────────────────────────────────────
        $schedule->command('gameplay:check-timeouts')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        // ── BackupService ─────────────────────────────────────────────────
        $schedule->command('backup-service:backup --type=full')
            ->dailyAt('02:00')
            ->withoutOverlapping(120)
            ->runInBackground();

        $schedule->command('backup-service:backup --type=incremental')
            ->hourly()
            ->withoutOverlapping(50)
            ->runInBackground();

        $schedule->command('backup-service:clean')
            ->dailyAt('03:00')
            ->withoutOverlapping(30)
            ->runInBackground();

        $schedule->command('backup-service:check-nodes')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        // ── Heartbeat semua module ────────────────────────────────────────
        $schedule->command('user-service:heartbeat')
            ->everyMinute()
            ->withoutOverlapping(3)
            ->runInBackground();

        $schedule->command('room-service:heartbeat')
            ->everyMinute()
            ->withoutOverlapping(3)
            ->runInBackground();

        // FIX: gameplay-service:heartbeat sebelumnya tidak ada di sini
        // karena GameplayService belum punya HeartbeatService sama sekali.
        $schedule->command('gameplay-service:heartbeat')
            ->everyMinute()
            ->withoutOverlapping(3)
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
