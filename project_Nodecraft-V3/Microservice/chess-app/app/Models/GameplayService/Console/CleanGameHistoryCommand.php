<?php

namespace App\Modules\GameplayService\Console;

use App\Modules\GameplayService\Models\GameSession;
use App\Modules\GameplayService\Models\MoveRecord;
use Illuminate\Console\Command;

/**
 * Hapus riwayat game lama dari database.
 * Dijalankan via scheduler (e.g. bulanan) untuk menjaga ukuran collection.
 *
 * Artisan: php artisan gameplay:clean-history --days=90
 */
class CleanGameHistoryCommand extends Command
{
    protected $signature = 'gameplay:clean-history
                            {--days=90 : Hapus game yang lebih lama dari N hari}
                            {--dry-run : Hanya tampilkan jumlah, tidak benar-benar hapus}';

    protected $description = 'Hapus riwayat game lama (finished/aborted) dari database';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $sessions = GameSession::whereIn('status', ['finished', 'aborted'])
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = $sessions->count();

        if ($count === 0) {
            $this->info("Tidak ada game yang lebih lama dari {$days} hari.");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[Dry-run] {$count} sesi game akan dihapus (lebih lama dari {$days} hari).");
            return self::SUCCESS;
        }

        $sessionIds = $sessions->pluck('_id')->map(fn($id) => (string) $id)->toArray();

        // Hapus move records dulu
        MoveRecord::whereIn('session_id', $sessionIds)->delete();

        // Hapus sesi
        GameSession::whereIn('_id', $sessionIds)->delete();

        $this->info("Berhasil menghapus {$count} sesi game beserta move records-nya.");

        return self::SUCCESS;
    }
}
