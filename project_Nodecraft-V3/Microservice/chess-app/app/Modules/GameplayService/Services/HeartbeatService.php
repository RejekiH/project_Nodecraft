<?php

namespace App\Modules\GameplayService\Services;

use App\Shared\Services\BaseHeartbeatService;
use App\Modules\GameplayService\Models\GameSession;

/**
 * HeartbeatService — GameplayService
 *
 * Mengirim heartbeat ke RabbitMQ setiap menit via artisan command:
 *   php artisan gameplay-service:heartbeat
 *
 * Dipantau oleh BackupService untuk mendeteksi jika GameplayService
 * tidak merespons (degraded/offline).
 */
class HeartbeatService extends BaseHeartbeatService
{
    protected function getModuleName(): string
    {
        return 'gameplay-service';
    }

    protected function getAdditionalMetrics(): array
    {
        return [
            'active_sessions'   => GameSession::active()->count(),
            'finished_today'    => GameSession::finished()
                ->where('finished_at', '>=', now()->startOfDay()->toISOString())
                ->count(),
        ];
    }
}
