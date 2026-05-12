<?php

use App\Modules\BackupService\Controllers\BackupController;
use App\Modules\BackupService\Controllers\HeartbeatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BackupService Routes
|--------------------------------------------------------------------------
|
| Sesuai arsitektur Modular Monolith: modul BackupService memiliki
| routes.php miliknya sendiri, di-load dari RouteServiceProvider.
|
| Tanggung jawab BackupService:
|   - Menerima heartbeat dari semua module via RabbitMQ
|   - Monitoring ketersediaan node (online/offline/degraded)
|   - Trigger backup data otomatis (MongoDB dump)
|   - Update rating user setelah match (via UserService internal API)
|   - Endpoint status untuk health check
|
| Daftarkan di config/app.php:
|   App\Modules\BackupService\BackupServiceProvider::class,
|
*/

// ─── Health check publik ─────────────────────────────────────────────────────
Route::get('api/backup/health', [BackupController::class, 'health']);

// ─── Endpoint internal — hanya bisa diakses module lain ─────────────────────
Route::prefix('api/internal/backup')->middleware('internal.key')->group(function () {

    // GET  /api/internal/backup/status
    // Melihat status semua node yang dipantau
    Route::get('status', [HeartbeatController::class, 'status']);

    // GET  /api/internal/backup/status/{module}
    // Status spesifik satu module
    Route::get('status/{module}', [HeartbeatController::class, 'moduleStatus']);

    // POST /api/internal/backup/trigger
    // Trigger backup manual (dipanggil oleh admin atau scheduler lain)
    Route::post('trigger', [BackupController::class, 'trigger']);

    // GET  /api/internal/backup/history
    // Riwayat backup yang sudah dilakukan
    Route::get('history', [BackupController::class, 'history']);

    // POST /api/internal/backup/match-result
    // Terima hasil match dari RoomService, teruskan ke UserService
    Route::post('match-result', [BackupController::class, 'applyMatchResult']);
});
