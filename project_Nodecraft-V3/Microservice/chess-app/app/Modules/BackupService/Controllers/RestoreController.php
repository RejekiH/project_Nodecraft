<?php

namespace App\Modules\BackupService\Controllers;

use App\Modules\BackupService\Services\RestoreService;
use App\Modules\BackupService\Services\BackupService;
use App\Modules\BackupService\Exceptions\BackupException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RestoreController
 *
 * Endpoint:
 *   POST /api/internal/backup/restore/{id}   - Restore dari backup ID tertentu
 *   POST /api/internal/backup/restore/latest - Restore dari backup terbaru
 *   POST /api/internal/backup/snapshot       - Ambil snapshot sekarang (alias trigger full backup)
 */
class RestoreController extends Controller
{
    public function __construct(
        private RestoreService $restoreService,
        private BackupService  $backupService,
    ) {}

    // ─────────────────────────────────────────────
    // POST /api/internal/backup/restore/{id}
    // ─────────────────────────────────────────────

    /**
     * Restore database dari backup record ID tertentu.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     *
     * Response 200:
     * {
     *   "success": true,
     *   "message": "Restore selesai",
     *   "data": { ...backup_record }
     * }
     *
     * Response 404/422/500:
     * { "success": false, "error": { "code": "...", "message": "..." } }
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $record = $this->restoreService->restoreFromId($id);

            return response()->json([
                'success' => true,
                'message' => 'Restore selesai. Database telah dikembalikan ke kondisi backup.',
                'data'    => $record->toSummaryArray(),
            ]);

        } catch (BackupException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'RESTORE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode() ?: 500);
        }
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/backup/restore/latest
    // ─────────────────────────────────────────────

    /**
     * Restore dari backup terbaru yang berhasil.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     */
    public function restoreLatest(): JsonResponse
    {
        try {
            $record = $this->restoreService->restoreLatest();

            return response()->json([
                'success' => true,
                'message' => 'Restore dari backup terbaru selesai.',
                'data'    => $record->toSummaryArray(),
            ]);

        } catch (BackupException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'RESTORE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode() ?: 500);
        }
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/backup/snapshot
    // ─────────────────────────────────────────────

    /**
     * Ambil snapshot database sekarang (full backup on-demand).
     * Berguna sebelum deployment atau operasi berbahaya.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     *
     * Response 202:
     * {
     *   "success": true,
     *   "message": "Snapshot diambil",
     *   "data": { ...backup_record }
     * }
     */
    public function snapshot(Request $request): JsonResponse
    {
        $triggeredBy = $request->input('triggered_by', 'manual_snapshot');

        $record = $this->backupService->runFullBackup($triggeredBy);

        if ($record->status !== 'success') {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'SNAPSHOT_FAILED',
                    'message' => $record->error ?? 'Snapshot gagal',
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Snapshot berhasil diambil.',
            'data'    => $record->toSummaryArray(),
        ], 202);
    }
}
