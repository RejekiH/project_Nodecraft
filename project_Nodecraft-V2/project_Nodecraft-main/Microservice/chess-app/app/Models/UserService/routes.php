<?php

use App\Modules\UserService\Controllers\AuthController;
use App\Modules\UserService\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| UserService Routes
|--------------------------------------------------------------------------
|
| Sesuai arsitektur Modular Monolith: setiap module memiliki routes.php
| miliknya sendiri, di-load dari RouteServiceProvider atau bootstrap/app.php.
|
| Endpoint sesuai spesifikasi deskripsi proyek:
|   /api/user/register, /api/user/login, /api/user/profile
|
| Middleware:
|   jwt.auth      → App\Modules\UserService\Middleware\JwtAuthMiddleware
|   internal.key  → App\Modules\UserService\Middleware\InternalApiKeyMiddleware
|
*/

// ─── Auth (tidak perlu token) ─────────────────────────────────────────────
Route::prefix('api/user')->group(function () {

    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('refresh',  [AuthController::class, 'refresh']);

    // ─── Butuh JWT ────────────────────────────────────────────────────────
    Route::middleware('jwt.auth')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);

        // Profil — GET & PUT /api/user/profile
        Route::get('profile',  [UserController::class, 'me']);
        Route::put('profile',  [UserController::class, 'updateMe']);

        // Leaderboard — GET /api/user/leaderboard
        Route::get('leaderboard', [UserController::class, 'leaderboard']);
    });

    // Profil publik — tidak perlu token
    // GET /api/user/{username}
    // CATATAN: letakkan SETELAH route static (profile, leaderboard) agar tidak bertabrakan
    Route::get('{username}', [UserController::class, 'show']);
});

// ─── Endpoint internal — hanya bisa diakses oleh module/service lain ─────
Route::prefix('api/internal/user')->middleware('internal.key')->group(function () {

    // POST /api/internal/user/{id}/match-result
    // Dipanggil RoomService atau BackupService setelah match selesai
    Route::post('{id}/match-result', [UserController::class, 'applyMatchResult']);

    // POST /api/internal/user/batch
    // Digunakan RoomService untuk lookup data beberapa pemain sekaligus
    Route::post('batch', [UserController::class, 'batchLookup']);

    // POST /api/internal/user/verify
    // Verifikasi JWT token — alternatif dari shared middleware
    Route::post('verify', [AuthController::class, 'verify']);
});
