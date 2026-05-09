<?php

namespace App\Modules\BackupService\Controllers;

use App\Modules\BackupService\Services\BackupService;
use App\Modules\BackupService\Services\UserServiceClient;
use App\Modules\BackupService\Services\HeartbeatMonitorService;
use App\Modules\BackupService\Requests\TriggerBackupRequest;
use App\Modules\BackupService\Requests\ApplyMatchResultRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * BackupController
 *
 * Endpoint:
 *   GET  /api/backup/health                         - Health check publik
 *   POST /api/internal/backup/trigger               - Trigger backup manual
 *   GET  /api/internal/backup/history               - Riwayat backup
 *   POST /api/internal/backup/match-result          - Forward hasil match ke UserService
 */
class BackupController extends Controller
{
    public function __construct(
        private BackupService          $backupService,
        private UserServiceClient      $userServiceClient,
        private HeartbeatMonitorService $heartbeatMonitor,
    ) {}

    // ─────────────────────────────────────────────
    // GET /api/backup/health
    // ─────────────────────────────────────────────

    /**
     * Health check publik — tidak perlu autentikasi.
     *
     * Response 200:
     * {
     *   "success": true,
     *   "service": "backup-service",
     *   "status": "healthy",
     *   "last_backup": { ...record } | null
     * }
     */
    public function health(): JsonResponse
    {
        $lastBackup = $this->backupService->getLastSuccessful();

        return response()->json([
            'success'     => true,
            'service'     => 'backup-service',
            'status'      => 'healthy',
            'last_backup' => $lastBackup ? $lastBackup->toSummaryArray() : null,
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/backup/trigger
    // ─────────────────────────────────────────────

    /**
     * Trigger backup manual.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     * Body: { type: "full" | "incremental" }
     *
     * Response 202:
     * {
     *   "success": true,
     *   "message": "Backup dimulai",
     *   "data": { ...backup_record }
     * }
     */
    public function trigger(TriggerBackupRequest $request): JsonResponse
    {
        $type = $request->input('type', 'full');

        $record = match ($type) {
            'incremental' => $this->backupService->runIncrementalBackup('manual'),
            default       => $this->backupService->runFullBackup('manual'),
        };

        return response()->json([
            'success' => true,
            'message' => 'Backup dimulai',
            'data'    => $record->toSummaryArray(),
        ], 202);
    }

    // ─────────────────────────────────────────────
    // GET /api/internal/backup/history
    // ─────────────────────────────────────────────

    /**
     * Riwayat backup.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     * Query: limit (default 20, max 100), offset (default 0)
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": [ ...records ],
     *   "meta": { total, limit, offset }
     * }
     */
    public function history(Request $request): JsonResponse
    {
        $limit  = (int) $request->query('limit', 20);
        $offset = (int) $request->query('offset', 0);

        $result = $this->backupService->getHistory($limit, $offset);

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => [
                'total'  => $result['total'],
                'limit'  => $result['limit'],
                'offset' => $result['offset'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/backup/match-result
    // ─────────────────────────────────────────────

    /**
     * Terima hasil match dari RoomService, teruskan ke UserService.
     * BackupService berperan sebagai relay + pencatat backup setelah match.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     * Body:
     * {
     *   "results": [
     *     { "user_id": "...", "result": "win",  "preview": {...} },
     *     { "user_id": "...", "result": "loss", "preview": {...} }
     *   ]
     * }
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": { "<user_id>": { new_rating: ... }, ... }
     * }
     */
    public function applyMatchResult(ApplyMatchResultRequest $request): JsonResponse
    {
        $results = $request->validated()['results'];

        $responses = $this->userServiceClient->batchApplyMatchResults($results);

        // Trigger incremental backup setelah match result diproses
        // Fix: capture $backupService via use() — tidak boleh pakai $this di dalam closure
        // karena Controller bisa sudah di-garbage-collect saat closure dieksekusi afterResponse.
        $backupService = $this->backupService;
        dispatch(function () use ($backupService) {
            $backupService->runIncrementalBackup('match_result');
        })->afterResponse();

        return response()->json([
            'success' => true,
            'data'    => $responses,
        ]);
    }
}
