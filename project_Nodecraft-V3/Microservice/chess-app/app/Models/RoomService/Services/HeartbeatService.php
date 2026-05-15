<?php

namespace App\Modules\RoomService\Services;

use App\Shared\Services\BaseHeartbeatService;
use App\Modules\RoomService\Models\Room;

/**
 * HeartbeatService — RoomService
 *
 * FIX: File sebelumnya berisi namespace App\Modules\UserService\Services
 * yang merupakan copy-paste error. Namespace diperbaiki ke RoomService.
 *
 * Mengirim heartbeat ke RabbitMQ setiap menit via artisan command:
 *   php artisan room-service:heartbeat
 */
class HeartbeatService extends BaseHeartbeatService
{
    protected function getModuleName(): string
    {
        return 'room-service';
    }

    protected function getAdditionalMetrics(): array
    {
        return [
            'waiting_rooms'     => Room::waiting()->count(),
            'in_progress_rooms' => Room::inProgress()->count(),
        ];
    }
}
