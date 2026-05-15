<?php

namespace App\Modules\BackupService\Controllers;

use App\Modules\BackupService\Services\HeartbeatMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * HeartbeatController
 *
 * Endpoint:
 *   GET /api/internal/backup/status           - Status semua node
 *   GET /api/internal/backup/status/{module}  - Status satu module
 */
class HeartbeatController extends Controller
{
    public function __construct(private HeartbeatMonitorService $heartbeatMonitor) {}

    // ─────────────────────────────────────────────
    // GET /api/internal/backup/status
    // ─────────────────────────────────────────────

    /**
     * Status semua node yang dipantau.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "module": "user-service",
     *       "status": "online",
     *       "hostname": "node-01",
     *       "last_seen": "2026-05-07T03:00:00.000Z",
     *       "missed_beats": 0
     *     },
     *     ...
     *   ]
     * }
     */
    public function status(): JsonResponse
    {
        $statuses = $this->heartbeatMonitor->getAllStatuses();

        return response()->json([
            'success' => true,
            'data'    => $statuses,
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/internal/backup/status/{module}
    // ─────────────────────────────────────────────

    /**
     * Status satu module spesifik.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     *
     * Response 200:
     * { "success": true, "data": { ...node_status } }
     *
     * Response 404:
     * { "success": false, "error": { "code": "MODULE_NOT_FOUND", ... } }
     */
    public function moduleStatus(string $module): JsonResponse
    {
        $status = $this->heartbeatMonitor->getModuleStatus($module);

        if (!$status) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'MODULE_NOT_FOUND',
                    'message' => "Module '{$module}' tidak ditemukan atau belum pernah mengirim heartbeat",
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }
}
