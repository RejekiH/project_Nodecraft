<?php

namespace App\Modules\BackupService\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UserServiceClient
 *
 * HTTP client untuk berkomunikasi dengan UserService via internal API.
 * BackupService memanggil endpoint ini setelah menerima hasil match
 * dari RoomService (atau sumber lain), lalu meneruskannya ke UserService
 * untuk update rating.
 *
 * Endpoint yang digunakan:
 *   POST /api/internal/user/{id}/match-result
 *   Header: X-Internal-Key: <INTERNAL_API_KEY>
 *
 * Karena ini Modular Monolith (satu app Laravel), pemanggilan bisa dilakukan
 * langsung via HTTP ke diri sendiri (localhost) atau via service layer internal.
 * Implementasi ini menggunakan HTTP agar mudah diubah jika arsitektur berubah
 * menjadi microservice di masa depan.
 */
class UserServiceClient
{
    private string $baseUrl;
    private string $internalKey;
    private int    $timeout;

    public function __construct()
    {
        // Dalam modular monolith, base URL adalah aplikasi itu sendiri
        $this->baseUrl     = config('backup.user_service_url', config('app.url'));
        $this->internalKey = config('app.internal_api_key', '');
        $this->timeout     = config('backup.http_timeout', 10);
    }

    // ─────────────────────────────────────────────
    // MATCH RESULT
    // ─────────────────────────────────────────────

    /**
     * Kirim hasil match ke UserService untuk update rating.
     *
     * @param string $userId   MongoDB ObjectId user
     * @param string $result   'win' | 'loss' | 'draw'
     * @param array  $preview  Data preview match (opsional)
     *
     * @return array{user_id: string, result: string, new_rating: int}
     * @throws \RuntimeException jika request gagal
     */
    public function applyMatchResult(string $userId, string $result, array $preview = []): array
    {
        $url  = "{$this->baseUrl}/api/internal/user/{$userId}/match-result";
        $body = ['result' => $result];

        if (!empty($preview)) {
            $body['preview'] = $preview;
        }

        Log::info('[BackupService → UserService] Mengirim match result', [
            'user_id' => $userId,
            'result'  => $result,
        ]);

        $response = Http::withHeaders([
            'X-Internal-Key' => $this->internalKey,
            'Accept'         => 'application/json',
        ])
        ->timeout($this->timeout)
        ->post($url, $body);

        if ($response->failed()) {
            $errorMsg = $response->json('error.message')
                ?? $response->body();

            Log::error('[BackupService → UserService] Gagal apply match result', [
                'user_id'     => $userId,
                'status_code' => $response->status(),
                'error'       => $errorMsg,
            ]);

            throw new \RuntimeException(
                "UserService gagal memproses match result untuk user {$userId}: {$errorMsg}",
                $response->status()
            );
        }

        $data = $response->json('data');

        Log::info('[BackupService → UserService] Match result berhasil', [
            'user_id'    => $userId,
            'new_rating' => $data['new_rating'] ?? null,
        ]);

        return $data;
    }

    /**
     * Batch apply match result untuk beberapa user sekaligus.
     * Berguna setelah match selesai — update semua pemain dalam satu operasi.
     *
     * @param array $results Array of ['user_id', 'result', 'preview']
     * @return array         Array hasil per user
     */
    public function batchApplyMatchResults(array $results): array
    {
        $responses = [];

        foreach ($results as $item) {
            try {
                $responses[$item['user_id']] = $this->applyMatchResult(
                    $item['user_id'],
                    $item['result'],
                    $item['preview'] ?? []
                );
            } catch (\RuntimeException $e) {
                // Catat error tapi lanjutkan untuk user lain
                $responses[$item['user_id']] = [
                    'error'   => true,
                    'message' => $e->getMessage(),
                ];

                Log::error('[BackupService] Batch match result partial failure', [
                    'user_id' => $item['user_id'],
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $responses;
    }

    // ─────────────────────────────────────────────
    // HEALTH CHECK
    // ─────────────────────────────────────────────

    /**
     * Cek apakah UserService dapat dijangkau.
     * Digunakan oleh HeartbeatMonitor untuk validasi konektivitas.
     */
    public function ping(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/backup/health");
            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }
}
