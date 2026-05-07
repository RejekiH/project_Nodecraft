<?php

namespace App\Modules\GameplayService\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RoomServiceClient
 *
 * HTTP client untuk berkomunikasi dengan RoomService via internal API.
 * GameplayService memanggil RoomService setelah game selesai untuk melaporkan
 * hasil pertandingan. RoomService kemudian meneruskan ke BackupService
 * (untuk update rating di UserService).
 *
 * Endpoint yang digunakan:
 *   POST /api/internal/room/{roomId}/match-result
 *   Header: X-Internal-Key: <INTERNAL_API_KEY>
 */
class RoomServiceClient
{
    private string $baseUrl;
    private string $internalKey;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl     = config('gameplay.room_service_url', config('app.url'));
        $this->internalKey = config('app.internal_api_key', '');
        $this->timeout     = config('gameplay.http_timeout', 10);
    }

    // ─────────────────────────────────────────────
    // MATCH RESULT
    // ─────────────────────────────────────────────

    /**
     * Laporkan hasil pertandingan ke RoomService.
     *
     * @param string $roomId
     * @param string $sessionId
     * @param string $result      'white_wins' | 'black_wins' | 'draw'
     * @param string $reason      'checkmate' | 'resign' | 'timeout' | 'draw_agreement' | 'stalemate'
     * @param string $whiteId     User ID pemain putih
     * @param string $blackId     User ID pemain hitam
     *
     * @throws \RuntimeException jika request gagal
     */
    public function reportMatchResult(
        string $roomId,
        string $sessionId,
        string $result,
        string $reason,
        string $whiteId,
        string $blackId
    ): void {
        $url = "{$this->baseUrl}/api/internal/room/{$roomId}/match-result";

        $body = [
            'session_id' => $sessionId,
            'result'     => $result,
            'reason'     => $reason,
            'white_id'   => $whiteId,
            'black_id'   => $blackId,
        ];

        Log::info('[GameplayService → RoomService] Melaporkan hasil match', [
            'room_id'    => $roomId,
            'session_id' => $sessionId,
            'result'     => $result,
            'reason'     => $reason,
        ]);

        $response = Http::withHeaders([
            'X-Internal-Key' => $this->internalKey,
            'Accept'         => 'application/json',
        ])
        ->timeout($this->timeout)
        ->post($url, $body);

        if ($response->failed()) {
            $errorMsg = $response->json('error.message') ?? $response->body();

            Log::error('[GameplayService → RoomService] Gagal lapor match result', [
                'room_id'     => $roomId,
                'status_code' => $response->status(),
                'error'       => $errorMsg,
            ]);

            throw new \RuntimeException(
                "RoomService gagal menerima match result untuk room {$roomId}: {$errorMsg}",
                $response->status()
            );
        }

        Log::info('[GameplayService → RoomService] Hasil match berhasil dilaporkan', [
            'room_id' => $roomId,
        ]);
    }

    // ─────────────────────────────────────────────
    // HEALTH CHECK
    // ─────────────────────────────────────────────

    /**
     * Cek apakah RoomService dapat dijangkau.
     */
    public function ping(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/room/health");
            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }
}
