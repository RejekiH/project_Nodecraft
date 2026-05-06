<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * InternalApiKeyMiddleware
 * 
 * Melindungi endpoint internal yang hanya boleh dipanggil
 * oleh service lain dalam ekosistem NodeChess.
 * 
 * Header yang diperlukan: X-Internal-Key: <INTERNAL_API_KEY dari .env>
 * 
 * Usage di routes: Route::middleware('internal.key')
 */
class InternalApiKeyMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $providedKey = $request->header('X-Internal-Key');
        $validKey    = config('app.internal_api_key');

        if (!$providedKey || !hash_equals($validKey, $providedKey)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UNAUTHORIZED_SERVICE',
                    'message' => 'Akses ditolak. Internal API Key tidak valid.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
