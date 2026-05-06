<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\InternalApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Service API Routes
|--------------------------------------------------------------------------
|
| Base URL: http://localhost:8001/api
|
| Publik  : Tidak butuh token
| Private : Butuh header  Authorization: Bearer <access_token>
| Internal: Butuh header  X-Internal-Key: <INTERNAL_API_KEY>
|
*/

// ─── Health Check ──────────────────────────────────────────────────────────
Route::get('/health', fn() => response()->json([
    'service' => 'user-service',
    'status'  => 'ok',
    'version' => '1.0.0',
    'timestamp' => now()->toISOString(),
]));

// ─── Auth Routes (Publik) ──────────────────────────────────────────────────
Route::prefix('auth')->group(function () {

    // POST /api/auth/register
    Route::post('/register', [AuthController::class, 'register'])
         ->middleware('throttle:' . env('RATE_LIMIT_REGISTER', 10) . ',1');

    // POST /api/auth/login
    Route::post('/login', [AuthController::class, 'login'])
         ->middleware('throttle:' . env('RATE_LIMIT_LOGIN', 20) . ',1');

    // POST /api/auth/refresh
    Route::post('/refresh', [AuthController::class, 'refresh']);

});

// ─── Auth Routes (Private - butuh JWT) ────────────────────────────────────
Route::prefix('auth')->middleware(JwtAuthMiddleware::class)->group(function () {

    // POST /api/auth/logout
    Route::post('/logout', [AuthController::class, 'logout']);

});

// ─── User Routes (Publik) ─────────────────────────────────────────────────
Route::prefix('users')->group(function () {

    // GET /api/users/leaderboard  -- HARUS sebelum /{username} agar tidak bentrok
    Route::get('/leaderboard', [UserController::class, 'leaderboard']);

    // GET /api/users/{username}
    Route::get('/{username}', [UserController::class, 'show'])
         ->where('username', '[a-zA-Z0-9_]+');

});

// ─── User Routes (Private - butuh JWT) ───────────────────────────────────
Route::prefix('users')->middleware(JwtAuthMiddleware::class)->group(function () {

    // GET  /api/users/me
    Route::get('/me', [UserController::class, 'me']);

    // PUT  /api/users/me
    Route::put('/me', [UserController::class, 'updateMe']);

});

// ─── Internal Routes (Service-to-Service) ─────────────────────────────────
Route::prefix('internal')->middleware(InternalApiKeyMiddleware::class)->group(function () {

    // POST /api/internal/auth/verify
    // Dipakai Room/Gameplay Service untuk verifikasi token user
    Route::post('/auth/verify', [AuthController::class, 'verify']);

    // POST /api/internal/users/{id}/match-result
    // Dipakai Room/Backup Service untuk update rating
    Route::post('/users/{userId}/match-result', [UserController::class, 'applyMatchResult']);

    // POST /api/internal/users/batch
    // Dipakai Room Service untuk mendapat info pemain dalam batch
    Route::post('/users/batch', [UserController::class, 'batchLookup']);

});
