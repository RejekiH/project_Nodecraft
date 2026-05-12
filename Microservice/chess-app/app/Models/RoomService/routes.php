<?php

use App\Modules\RoomService\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RoomService Routes
|--------------------------------------------------------------------------
|
| Sesuai arsitektur Modular Monolith: setiap module memiliki routes.php
| miliknya sendiri, di-load dari RoomServiceProvider.
|
| Endpoint sesuai spesifikasi deskripsi proyek:
|   /api/room  — manajemen room pertandingan catur
|
| Middleware:
|   jwt.auth      → App\Modules\RoomService\Middleware\JwtAuthMiddleware
|   internal.key  → App\Modules\RoomService\Middleware\InternalApiKeyMiddleware
|
*/

// ─── Public routes (butuh JWT) ────────────────────────────────────────────
Route::prefix('api/room')->middleware('jwt.auth')->group(function () {

    // POST   /api/room          - Buat room baru
    Route::post('/',    [RoomController::class, 'store']);

    // POST   /api/room/join     - Join room via kode
    // CATATAN: letakkan SEBELUM /{id} agar tidak tertangkap sebagai {id}
    Route::post('/join', [RoomController::class, 'join']);

    // GET    /api/room          - List room waiting
    Route::get('/',     [RoomController::class, 'index']);

    // GET    /api/room/code/{code}  - Detail room berdasarkan kode
    // CATATAN: letakkan SEBELUM /{id} untuk hindari ambiguitas
    Route::get('/code/{code}', [RoomController::class, 'showByCode'])
         ->where('code', '[A-Z2-9]{6}');

    // GET    /api/room/history/{userId} - Riwayat pertandingan user
    // CATATAN: letakkan SEBELUM /{id} agar tidak tertangkap sebagai {id}
    Route::get('/history/{userId}', [RoomController::class, 'history']);

    // GET    /api/room/{id}     - Detail room berdasarkan ID
    Route::get('/{id}',    [RoomController::class, 'show']);

    // DELETE /api/room/{id}    - Cancel room (host only, status waiting)
    Route::delete('/{id}', [RoomController::class, 'cancel']);
});

// ─── Internal routes — hanya bisa diakses oleh module/service lain ────────
Route::prefix('api/internal/room')->middleware('internal.key')->group(function () {

    // POST /api/internal/room/{id}/finish
    // Dipanggil GameplayService setelah pertandingan selesai
    Route::post('/{id}/finish', [RoomController::class, 'finish']);

    // POST /api/internal/room/{id}/match-result
    // Alias untuk /finish — mendukung GameplayService yang memanggil /match-result
    Route::post('/{id}/match-result', [RoomController::class, 'finish']);

    // GET  /api/internal/room/{id}
    // Dipanggil BackupService untuk ambil detail room dengan PGN
    Route::get('/{id}', [RoomController::class, 'internalShow']);
});
