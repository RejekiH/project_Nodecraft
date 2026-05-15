<?php

namespace App\Modules\UserService\Controllers;

use App\Modules\UserService\Services\UserService;
use App\Modules\UserService\Services\JwtService;
use App\Modules\UserService\Requests\RegisterRequest;
use App\Modules\UserService\Requests\LoginRequest;
use App\Modules\UserService\Requests\RefreshTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * AuthController
 * 
 * Endpoint:
 *   POST /api/user/register  - Daftar akun baru
 *   POST /api/user/login     - Login dan dapat token
 *   POST /api/user/logout    - Logout (set status offline)
 *   POST /api/user/refresh   - Perbarui access token
 *   POST /api/user/verify    - Verifikasi token (untuk inter-service)
 * 
 * Prefix /api/user sesuai deskripsi proyek: /api/user/register, /api/user/login, /api/user/profile
 */
class AuthController extends Controller
{
    public function __construct(
        private UserService $userService,
        private JwtService  $jwtService,
    ) {}

    // ─────────────────────────────────────────────
    // POST /api/user/register
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
     *     "user": { ...profil + email },
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
    // POST /api/user/login
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
    // POST /api/user/logout
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
        // $request->user diisi oleh JwtAuthMiddleware
        $this->userService->logout($request->user);

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/user/refresh
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
    // POST /api/user/verify  (internal)
    // ─────────────────────────────────────────────

    /**
     * Verifikasi token JWT — dipakai oleh module lain
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
