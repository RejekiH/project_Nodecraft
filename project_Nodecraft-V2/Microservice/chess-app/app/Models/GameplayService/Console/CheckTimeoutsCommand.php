<?php

namespace App\Modules\GameplayService\Console;

use App\Modules\GameplayService\Models\GameSession;
use App\Modules\GameplayService\Services\GameplayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cek sesi aktif yang salah satu pemainnya sudah kehabisan waktu.
 * Dijalankan via scheduler setiap menit.
 *
 * Artisan: php artisan gameplay:check-timeouts
 */
class CheckTimeoutsCommand extends Command
{
    protected $signature   = 'gameplay:check-timeouts';
    protected $description = 'Cek dan handle sesi game yang pemainnya kehabisan waktu';

    public function __construct(
        private GameplayService $gameplayService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $activeSessions = GameSession::active()->get();

        if ($activeSessions->isEmpty()) {
            return self::SUCCESS;
        }

        $handled = 0;

        foreach ($activeSessions as $session) {
            $clock = $session->clock ?? [];

            // Skip jika time_control adalah '0' (unlimited)
            if ($session->time_control === '0') {
                continue;
            }

            $timedOut = null;

            if (isset($clock['white']) && $clock['white'] <= 0) {
                $timedOut = 'white';
            } elseif (isset($clock['black']) && $clock['black'] <= 0) {
                $timedOut = 'black';
            }

            if ($timedOut !== null) {
                try {
                    $this->gameplayService->handleTimeout((string) $session->_id, $timedOut);
                    $handled++;

                    Log::info('[CheckTimeoutsCommand] Timeout handled', [
                        'session_id' => (string) $session->_id,
                        'color'      => $timedOut,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[CheckTimeoutsCommand] Gagal handle timeout', [
                        'session_id' => (string) $session->_id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($handled > 0) {
            $this->info("Berhasil handle {$handled} timeout.");
        }

        return self::SUCCESS;
    }
}
