<?php

namespace App\Modules\UserService\Services;

use App\Shared\Services\BaseHeartbeatService;
use App\Modules\UserService\Models\User;

/**
 * FIX #12: Sebelumnya duplikasi kode penuh dari RoomService\HeartbeatService.
 * Sekarang cukup extend BaseHeartbeatService dan set module name.
 */
class HeartbeatService extends BaseHeartbeatService
{
    protected function getModuleName(): string
    {
        return 'UserService';
    }

    protected function getAdditionalMetrics(): array
    {
        return [
            'online_users' => User::where('status', 'online')->count(),
        ];
    }
}
