<?php

namespace App\Exceptions;

use App\Modules\UserService\Exceptions\AuthException;
use App\Modules\UserService\Exceptions\UserException;
use App\Modules\RoomService\Exceptions\RoomException;
use App\Modules\GameplayService\Exceptions\GameplayException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Throwable;

/*
|--------------------------------------------------------------------------
| FIX: Tambahkan handler untuk RoomException dan GameplayException.
|
| Sebelumnya kedua exception ini jatuh ke catch-all Throwable
| dan selalu menghasilkan 500 INTERNAL_ERROR, meskipun exception
| sudah punya HTTP status code yang tepat (404, 409, 422, 403).
|
| Urutan PENTING: handler paling spesifik harus di atas catch-all Throwable.
| Laravel mengevaluasi dari atas ke bawah, berhenti di match pertama.
|--------------------------------------------------------------------------
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

        // FIX: RoomException sebelumnya tidak di-handle → selalu 500
        // Sekarang menggunakan HTTP status dari $e->getCode() yang sudah
        // di-set dengan benar di RoomException (404, 409, 403, 400, dll.)
        $this->renderable(function (RoomException $e, Request $request) {
            $code = $e->getCode();
            $errorCode = match ($code) {
                404 => 'ROOM_NOT_FOUND',
                409 => 'ROOM_CONFLICT',
                403 => 'FORBIDDEN',
                422 => 'VALIDATION_ERROR',
                400 => 'BAD_REQUEST',
                default => 'ROOM_ERROR',
            };

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $code ?: 400);
        });

        // FIX: GameplayException sebelumnya tidak di-handle → selalu 500
        // GameplayException sudah memiliki getHttpStatus() yang mengembalikan
        // HTTP status tepat (404, 409, 422, 403, 500) per jenis error.
        $this->renderable(function (GameplayException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                ],
            ], $e->getHttpStatus());
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

        // Catch-all Throwable HARUS di urutan terakhir
        // Sebelumnya ini menangkap RoomException dan GameplayException
        // karena tidak ada handler spesifik di atasnya.
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
