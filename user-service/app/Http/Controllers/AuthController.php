<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Services\JwtService;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AuthController
 * 
 * Endpoint:
 * POST /api/auth/register  - Daftar akun baru
 * POST /api/auth/login     - Login dan dapat token
 * POST /api/auth/logout    - Logout (set status offline)
 * POST /api/auth/refresh   - Perbarui access token
 * POST /api/auth/verify    - Verifikasi token (untuk inter-service)
 */
class AuthController extends Controller
{
    public function __construct(
        private UserService $userService,
        private JwtService  $jwtService,
    ) {}

    // ─────────────────────────────────────────────
    // POST /api/auth/register
    // ─────────────────────────────────────────────

    /**
     * Registrasi akun baru
     * 
     * Body: { username, email, password, password_confirmation }
     * 
     * Response 201:
     * {
     *   "success": true,
     *   "message": "Akun berhasil dibuat",
     *   "data": {
     *     "user": { ...profil publik + email },
     *     "access_token": "eyJ...",
     *     "refresh_token": "eyJ...",
     *     "expires_in": 3600,
     *     "token_type": "Bearer"
     *   }
     * }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->userService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dibuat',
            'data'    => [
                'user'          => $result['user']->toPrivateArray(),
                'access_token'  => $result['tokens']['access_token'],
                'refresh_token' => $result['tokens']['refresh_token'],
                'expires_in'    => $result['tokens']['expires_in'],
                'token_type'    => $result['tokens']['token_type'],
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────
    // POST /api/auth/login
    // ─────────────────────────────────────────────

    /**
     * Login
     * 
     * Body: { login: "username atau email", password }
     * 
     * Response 200: sama struktur dengan register
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->userService->login($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data'    => [
                'user'          => $result['user']->toPrivateArray(),
                'access_token'  => $result['tokens']['access_token'],
                'refresh_token' => $result['tokens']['refresh_token'],
                'expires_in'    => $result['tokens']['expires_in'],
                'token_type'    => $result['tokens']['token_type'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/auth/logout
    // ─────────────────────────────────────────────

    /**
     * Logout
     * 
     * Header: Authorization: Bearer <access_token>
     * 
     * Response 200:
     * { "success": true, "message": "Logout berhasil" }
     */
    public function logout(Request $request): JsonResponse
    {
        // $request->user sudah diisi oleh JwtAuthMiddleware
        $this->userService->logout($request->user);

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/auth/refresh
    // ─────────────────────────────────────────────

    /**
     * Perbarui access token menggunakan refresh token
     * 
     * Body: { refresh_token: "eyJ..." }
     * 
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "access_token": "eyJ...",
     *     "refresh_token": "eyJ...",
     *     "expires_in": 3600,
     *     "token_type": "Bearer"
     *   }
     * }
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->userService->refreshTokens($request->refresh_token);

        return response()->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/auth/verify
    // ─────────────────────────────────────────────

    /**
     * Verifikasi token JWT — dipakai oleh service lain
     * 
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     * Body: { token: "eyJ..." }
     * 
     * Response 200:
     * { "success": true, "data": { "valid": true, "user_id": "...", "username": "..." } }
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $result = $this->userService->verifyToken($request->token);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }
}
