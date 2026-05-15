<?php

namespace App\Modules\RoomService\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use App\Modules\UserService\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * JwtAuthMiddleware (RoomService)
 *
 * Middleware JWT untuk RoomService.
 * Sesuai arsitektur: JWT secret di-share ke semua module —
 * sehingga RoomService bisa verifikasi token LANGSUNG
 * tanpa memanggil UserService.
 *
 * Setelah validasi, inject $request->user = User object.
 *
 * Usage: Route::middleware('jwt.auth')
 */
class JwtAuthMiddleware
{
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

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_TOKEN_FORMAT',
                    'message' => 'Format Authorization header tidak valid. Gunakan: Bearer <token>',
                ],
            ], 401);
        }

        $token = trim(substr($authHeader, 7));

        try {
            $secret  = config('jwt.secret');
            $payload = JWT::decode($token, new Key($secret, 'HS256'));

            if (($payload->type ?? '') !== 'access') {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'INVALID_TOKEN', 'message' => 'Bukan access token'],
                ], 401);
            }

            // Lookup user — RoomService butuh user object untuk validasi kepemilikan room
            $user = User::find($payload->sub);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'USER_NOT_FOUND', 'message' => 'User tidak ditemukan'],
                ], 401);
            }

            $request->user    = $user;
            $request->user_id = $payload->sub;

            return $next($request);

        } catch (ExpiredException) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TOKEN_EXPIRED', 'message' => 'Token sudah kadaluarsa'],
            ], 401);

        } catch (SignatureInvalidException) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_TOKEN', 'message' => 'Token tidak valid'],
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_TOKEN', 'message' => 'Token tidak dapat diproses'],
            ], 401);
        }
    }
}
