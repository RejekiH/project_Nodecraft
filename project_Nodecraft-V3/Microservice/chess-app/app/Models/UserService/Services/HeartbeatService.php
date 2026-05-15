<?php

namespace App\Modules\UserService\Services;

use App\Shared\Services\BaseHeartbeatService;
use App\Modules\UserService\Models\User;

/**
 * HeartbeatService — UserService
 *
 * Mengirim heartbeat ke RabbitMQ setiap menit via artisan command:
 *   php artisan user-service:heartbeat
 *
 * FIX: getModuleName() mengembalikan 'user-service' (lowercase dengan dash)
 * agar konsisten dengan format yang digunakan BackupService saat mencatat
 * NodeStatus di MongoDB. Sebelumnya mengembalikan 'UserService' (PascalCase)
 * yang tidak cocok dengan nama module RoomService 'room-service'.
 */
class HeartbeatService extends BaseHeartbeatService
{
    protected function getModuleName(): string
    {
        return 'user-service';
    }

    protected function getAdditionalMetrics(): array
    {
        return [
            'online_users' => User::where('status', 'online')->count(),
        ];
    }
}
