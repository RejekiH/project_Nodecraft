<?php

use App\Modules\UserService\Controllers\AuthController;
use App\Modules\UserService\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FIX #1: Tambahkan GET /api/user/health
|   BackupService/UserServiceClient::ping() memanggil endpoint ini.
|   Sebelumnya tidak ada → ping() selalu return false (404)
|   → BackupService tidak bisa verifikasi koneksi ke UserService.
|
| FIX #2: Pindahkan GET /api/user/leaderboard ke luar group jwt.auth
|   Leaderboard seharusnya dapat diakses tanpa login (publik).
|   Sebelumnya terkunci di jwt.auth → front-end tidak bisa tampilkan
|   leaderboard tanpa user login terlebih dahulu.
|--------------------------------------------------------------------------
*/

Route::prefix('api/user')->group(function () {

    // ─── Auth publik ────────────────────────────────────────────────────
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('refresh',  [AuthController::class, 'refresh']);

    // FIX #1: Health check endpoint untuk BackupService::ping()
    Route::get('health', function () {
        return response()->json([
            'success' => true,
            'service' => 'user-service',
            'status'  => 'healthy',
            'time'    => now()->toISOString(),
        ]);
    });

    // FIX #2: Leaderboard dipindah ke publik (tidak butuh JWT)
    // Sebelumnya ada di dalam group middleware('jwt.auth') di bawah
    Route::get('leaderboard', [UserController::class, 'leaderboard'])
        ->middleware('throttle:60,1');

    // Profil publik — tidak butuh token
    // CATATAN: tetap di bawah route static agar tidak tertangkap duluan
    Route::get('{username}', [UserController::class, 'show']);

    // ─── Protected routes — wajib JWT ───────────────────────────────────
    Route::middleware('jwt.auth')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::get('profile',  [UserController::class, 'me']);
        Route::put('profile',  [UserController::class, 'updateMe']);
    });
});

// ─── Internal routes ─────────────────────────────────────────────────────
Route::prefix('api/internal/user')->middleware('internal.key')->group(function () {
    Route::post('{id}/match-result', [UserController::class, 'applyMatchResult']);
    Route::post('batch',             [UserController::class, 'batchLookup']);
    Route::post('verify',            [AuthController::class, 'verify']);
});
