<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use App\Exceptions\AuthException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * JwtAuthMiddleware
 * 
 * Middleware untuk route yang membutuhkan autentikasi JWT.
 * Jika valid, menambahkan $request->user = User object.
 * 
 * Usage di routes: Route::middleware('jwt.auth')
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
