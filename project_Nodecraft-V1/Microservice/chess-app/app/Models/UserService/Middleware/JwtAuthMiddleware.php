<?php

namespace App\Modules\UserService\Middleware;

use App\Modules\UserService\Services\JwtService;
use App\Modules\UserService\Exceptions\AuthException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * JwtAuthMiddleware
 * 
 * Middleware untuk route yang membutuhkan autentikasi JWT.
 * Jika token valid, inject $request->user = User object.
 * 
 * Sesuai arsitektur:
 *   UserService menyediakan JWT token → diverifikasi langsung
 *   via shared middleware oleh semua module lain.
 * 
 * Usage di routes.php: Route::middleware('jwt.auth')
 */
class JwtAuthMiddleware
{
    public function __construct(private JwtService $jwtService) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'MISSING_TOKEN',
                    'message' => 'Authorization header diperlukan',
                ],
            ], 401);
        }

        try {
            $token = $this->jwtService->extractBearerToken($authHeader);
            $user  = $this->jwtService->validateAccessToken($token);

            // Inject user ke request untuk dipakai controller
            $request->user = $user;

            return $next($request);

        } catch (AuthException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_TOKEN',
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode() ?: 401);
        }
    }
}
