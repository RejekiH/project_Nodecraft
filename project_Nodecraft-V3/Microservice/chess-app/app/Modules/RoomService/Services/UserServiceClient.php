<?php

namespace App\Modules\RoomService\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\RoomService\Exceptions\RoomException;

/**
 * UserServiceClient
 *
 * HTTP client untuk memanggil endpoint internal UserService.
 *
 * Sesuai arsitektur Modular Monolith:
 *   RoomService memanggil UserService via internal HTTP
 *   menggunakan X-Internal-Key header.
 *
 * Endpoint yang digunakan:
 *   POST /api/internal/user/batch        - Lookup data beberapa pemain
 *   POST /api/internal/user/{id}/match-result - Update rating setelah match
 *   POST /api/internal/user/verify       - Verifikasi JWT token
 */
class UserServiceClient
{
    private string $baseUrl;
    private string $internalKey;
    private int    $timeoutSeconds = 5;

    public function __construct()
    {
        $this->baseUrl     = config('services.user_service.url', 'http://localhost:8000');
        $this->internalKey = config('app.internal_api_key');
    }

    // ─────────────────────────────────────────────
    // BATCH LOOKUP
    // ─────────────────────────────────────────────

    /**
     * Ambil data beberapa user sekaligus berdasarkan IDs.
     *
     * @param  string[] $userIds
     * @return array<string, array>  keyed by user_id
     * @throws RoomException
     */
    public function batchLookup(array $userIds): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['X-Internal-Key' => $this->internalKey])
                ->post("{$this->baseUrl}/api/internal/user/batch", [
                    'ids' => $userIds,
                ]);

            if ($response->failed()) {
                Log::error('UserServiceClient batchLookup failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new RoomException('Gagal mengambil data user dari UserService', 502);
            }

            return $response->json('data', []);

        } catch (RoomException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('UserServiceClient batchLookup exception', ['error' => $e->getMessage()]);
            throw new RoomException('UserService tidak dapat dihubungi', 503);
        }
    }

    // ─────────────────────────────────────────────
    // APPLY MATCH RESULT
    // ─────────────────────────────────────────────

    /**
     * Kirim hasil match ke UserService untuk update rating.
     *
     * @param string $userId
     * @param string $result   'win' | 'loss' | 'draw'
     * @param array  $preview  Data preview match terakhir
     * @throws RoomException
     */
    public function applyMatchResult(string $userId, string $result, array $preview = []): void
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['X-Internal-Key' => $this->internalKey])
                ->post("{$this->baseUrl}/api/internal/user/{$userId}/match-result", [
                    'result'  => $result,
                    'preview' => $preview,
                ]);

            if ($response->failed()) {
                Log::error('UserServiceClient applyMatchResult failed', [
                    'user_id' => $userId,
                    'result'  => $result,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
                // Jangan throw — rating update tidak boleh block room finish
                // BackupService yang akan handle retry jika gagal
            }

        } catch (\Exception $e) {
            Log::error('UserServiceClient applyMatchResult exception', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            // Non-critical: log saja, jangan crash
        }
    }

    // ─────────────────────────────────────────────
    // VERIFY TOKEN
    // ─────────────────────────────────────────────

    /**
     * Verifikasi JWT token ke UserService.
     * Alternatif dari validasi langsung via shared middleware.
     *
     * @return array{valid: bool, user_id: string, username: string}
     * @throws RoomException
     */
    public function verifyToken(string $token): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['X-Internal-Key' => $this->internalKey])
                ->post("{$this->baseUrl}/api/internal/user/verify", [
                    'token' => $token,
                ]);

            if ($response->failed()) {
                throw new RoomException('Token tidak valid', 401);
            }

            return $response->json('data');

        } catch (RoomException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('UserServiceClient verifyToken exception', ['error' => $e->getMessage()]);
            throw new RoomException('UserService tidak dapat dihubungi', 503);
        }
    }
}
