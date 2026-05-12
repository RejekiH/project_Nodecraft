<?php

use App\Modules\RoomService\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FIX: Tambahkan GET /api/room/health
|
| GameplayService/RoomServiceClient::ping() memanggil endpoint ini
| untuk mengecek apakah RoomService dapat dijangkau.
| Sebelumnya tidak ada → ping() selalu return false (404)
| → fault-tolerance GameplayService tidak berfungsi.
|--------------------------------------------------------------------------
*/

// FIX: Health check publik — di luar semua middleware group
Route::get('api/room/health', function () {
    return response()->json([
        'success' => true,
        'service' => 'room-service',
        'status'  => 'healthy',
        'time'    => now()->toISOString(),
    ]);
});

// ─── Public routes (butuh JWT) ────────────────────────────────────────────
Route::prefix('api/room')->middleware('jwt.auth')->group(function () {

    Route::post('/',    [RoomController::class, 'store']);

    // CATATAN: /join dan /history harus SEBELUM /{id} agar tidak tertangkap
    Route::post('/join', [RoomController::class, 'join']);

    Route::get('/',     [RoomController::class, 'index']);

    Route::get('/code/{code}', [RoomController::class, 'showByCode'])
         ->where('code', '[A-Z2-9]{6}');

    Route::get('/history/{userId}', [RoomController::class, 'history']);

    Route::get('/{id}',    [RoomController::class, 'show']);
    Route::delete('/{id}', [RoomController::class, 'cancel']);
});

// ─── Internal routes ─────────────────────────────────────────────────────
Route::prefix('api/internal/room')->middleware('internal.key')->group(function () {
    Route::post('/{id}/finish',       [RoomController::class, 'finish']);
    Route::post('/{id}/match-result', [RoomController::class, 'finish']);
    Route::get('/{id}',               [RoomController::class, 'internalShow']);
});
