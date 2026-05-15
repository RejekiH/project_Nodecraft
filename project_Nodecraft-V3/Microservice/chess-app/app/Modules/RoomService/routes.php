<?php

use App\Modules\RoomService\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

// ─── Health check publik ─────────────────────────────────────────────────────
Route::get('api/room/health', [RoomController::class, 'health']);

// ─── Public routes (butuh JWT) ────────────────────────────────────────────────
Route::prefix('api/room')->middleware('jwt.auth')->group(function () {

    Route::post('/',     [RoomController::class, 'store']);
    Route::get('/',      [RoomController::class, 'index']);

    // Static routes HARUS di atas /{id} agar tidak tertangkap sebagai ID
    Route::post('/join',              [RoomController::class, 'join']);
    Route::get('/history/{userId}',   [RoomController::class, 'history']);
    Route::get('/code/{code}',        [RoomController::class, 'showByCode'])
         ->where('code', '[A-Z2-9]{6}');

    // Dynamic routes
    Route::get('/{id}',              [RoomController::class, 'show']);
    Route::delete('/{id}',           [RoomController::class, 'cancel']);

    // Rematch — hanya pemain room yang bisa vote
    Route::post('/{id}/rematch',     [RoomController::class, 'rematch']);
});

// ─── Internal routes ──────────────────────────────────────────────────────────
Route::prefix('api/internal/room')->middleware('internal.key')->group(function () {
    Route::post('/{id}/finish',       [RoomController::class, 'finish']);
    Route::post('/{id}/match-result', [RoomController::class, 'finish']);
    Route::get('/{id}',               [RoomController::class, 'internalShow']);
});
