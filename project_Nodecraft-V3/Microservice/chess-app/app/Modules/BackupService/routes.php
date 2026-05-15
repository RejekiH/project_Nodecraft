<?php

use App\Modules\BackupService\Controllers\BackupController;
use App\Modules\BackupService\Controllers\HeartbeatController;
use App\Modules\BackupService\Controllers\RestoreController;
use Illuminate\Support\Facades\Route;

// ─── Health check publik ─────────────────────────────────────────────────────
Route::get('api/backup/health', [BackupController::class, 'health']);

// ─── Endpoint internal ───────────────────────────────────────────────────────
Route::prefix('api/internal/backup')->middleware('internal.key')->group(function () {

    // Heartbeat & node status
    Route::get('status',          [HeartbeatController::class, 'status']);
    Route::get('status/{module}', [HeartbeatController::class, 'moduleStatus']);

    // Backup
    Route::post('trigger', [BackupController::class, 'trigger']);
    Route::get('history',  [BackupController::class, 'history']);

    // Match result relay
    Route::post('match-result', [BackupController::class, 'applyMatchResult']);

    // ── Baru: Snapshot & Restore ─────────────────────────────────────────
    // POST /api/internal/backup/snapshot
    // Ambil snapshot database sekarang (full backup on-demand)
    Route::post('snapshot', [RestoreController::class, 'snapshot']);

    // POST /api/internal/backup/restore/latest
    // Restore dari backup terbaru — HARUS di atas /{id} agar tidak tertangkap
    Route::post('restore/latest', [RestoreController::class, 'restoreLatest']);

    // POST /api/internal/backup/restore/{id}
    // Restore dari backup ID tertentu
    Route::post('restore/{id}', [RestoreController::class, 'restore']);
});
