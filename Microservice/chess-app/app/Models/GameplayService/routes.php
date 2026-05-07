<?php

use App\Modules\GameplayService\Controllers\GameplayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GameplayService Routes
|--------------------------------------------------------------------------
|
| Sesuai arsitektur Modular Monolith: modul GameplayService memiliki
| routes.php miliknya sendiri, di-load dari RouteServiceProvider.
|
| Tanggung jawab GameplayService:
|   - Membuat dan mengelola sesi pertandingan catur
|   - Menerima dan memvalidasi move dari pemain
|   - Mendeteksi kondisi akhir game (checkmate, stalemate, timeout)
|   - Melaporkan hasil ke RoomService setelah game selesai
|   - Menyimpan riwayat move untuk replay/analisis
|
| Daftarkan di config/app.php:
|   App\Modules\GameplayService\GameplayServiceProvider::class,
|
*/

// ─── Health check publik ─────────────────────────────────────────────────────
Route::get('api/gameplay/health', [GameplayController::class, 'health']);

// ─── Endpoint internal — hanya bisa diakses module lain ─────────────────────
Route::prefix('api/internal/gameplay')->middleware('internal.key')->group(function () {

    // POST /api/internal/gameplay/session
    // Buat sesi game baru (dipanggil RoomService setelah matchmaking)
    Route::post('session', [GameplayController::class, 'createSession']);

    // POST /api/internal/gameplay/session/{id}/start
    // Tandai sesi aktif — kedua pemain sudah terhubung
    Route::post('session/{id}/start', [GameplayController::class, 'startSession']);

    // GET  /api/internal/gameplay/session/{id}/board
    // Ambil state board terkini (FEN, giliran, jam, dll.)
    Route::get('session/{id}/board', [GameplayController::class, 'getBoard']);

    // GET  /api/internal/gameplay/session/{id}/moves
    // Ambil daftar semua move (untuk replay/analisis)
    Route::get('session/{id}/moves', [GameplayController::class, 'getMoves']);

    // POST /api/internal/gameplay/session/{id}/move
    // Submit move dari pemain
    Route::post('session/{id}/move', [GameplayController::class, 'submitMove']);

    // POST /api/internal/gameplay/session/{id}/resign
    // Pemain menyerah
    Route::post('session/{id}/resign', [GameplayController::class, 'resign']);

    // POST /api/internal/gameplay/session/{id}/draw
    // Kedua pemain setuju draw
    Route::post('session/{id}/draw', [GameplayController::class, 'acceptDraw']);

    // POST /api/internal/gameplay/session/{id}/timeout
    // Handle timeout (dipanggil scheduler/WebSocket server)
    Route::post('session/{id}/timeout', [GameplayController::class, 'handleTimeout']);

    // GET  /api/internal/gameplay/room/{roomId}/active
    // Ambil sesi aktif berdasarkan room ID
    Route::get('room/{roomId}/active', [GameplayController::class, 'getActiveByRoom']);

    // GET  /api/internal/gameplay/user/{userId}/history
    // Riwayat game seorang user (query: limit, offset)
    Route::get('user/{userId}/history', [GameplayController::class, 'getUserHistory']);
});
