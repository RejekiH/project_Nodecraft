<?php

namespace App\Exceptions;

use App\Modules\UserService\Exceptions\AuthException;
use App\Modules\UserService\Exceptions\UserException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Throwable;

/**
 * Handler
 * 
 * Mengembalikan semua error dalam format JSON yang konsisten:
 * {
 *   "success": false,
 *   "error": {
 *     "code": "ERROR_CODE",
 *     "message": "Pesan error",
 *     "details": {}   // opsional, untuk validation errors
 *   }
 * }
 */
class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    public function register(): void
    {
        $this->renderable(function (AuthException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'AUTH_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode() ?: 401);
        });

        $this->renderable(function (UserException $e, Request $request) {
            $code = $e->getCode();
            $errorCode = match ($code) {
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                400 => 'BAD_REQUEST',
                default => 'USER_ERROR',
            };

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $code ?: 400);
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Data yang dikirim tidak valid',
                    'details' => $e->errors(),
                ],
            ], 422);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            $message = config('app.debug')
                ? $e->getMessage()
                : 'Terjadi kesalahan internal. Silakan coba lagi.';

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_ERROR',
                    'message' => $message,
                ],
            ], 500);
        });
    }
}
