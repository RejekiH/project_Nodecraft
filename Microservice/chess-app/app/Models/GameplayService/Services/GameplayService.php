<?php

namespace App\Modules\GameplayService\Services;

use App\Modules\GameplayService\Models\GameSession;
use App\Modules\GameplayService\Models\MoveRecord;
use App\Modules\GameplayService\Exceptions\GameplayException;
use Illuminate\Support\Facades\Log;

/**
 * GameplayService
 *
 * Mengelola semua logika permainan catur: membuat sesi, memvalidasi dan
 * mencatat move, mendeteksi kondisi akhir game, serta mengelola waktu.
 *
 * Alur permainan normal:
 *   1. RoomService memanggil createSession() setelah dua pemain matched
 *   2. Pemain mengirm move via API → submitMove() dipanggil
 *   3. GameplayService memvalidasi move, update FEN, catat ke DB
 *   4. Jika checkmate/timeout/resign → finishGame() dipanggil
 *   5. GameplayService memanggil RoomServiceClient untuk lapor hasil
 *   6. RoomService meneruskan ke BackupService untuk update rating
 *
 * Catatan validasi chess:
 *   Validasi legalitas move (apakah move legal di posisi FEN saat ini)
 *   diserahkan ke chess engine / library. Service ini menerima move yang
 *   sudah divalidasi dari client (WebSocket), atau memanggil ChessValidator
 *   yang menggunakan php-chess atau panggilan ke Node.js chess.js.
 */
class GameplayService
{
    public function __construct(
        private ChessValidator   $validator,
        private RoomServiceClient $roomClient,
    ) {}

    // ─────────────────────────────────────────────
    // SESSION MANAGEMENT
    // ─────────────────────────────────────────────

    /**
     * Buat sesi game baru.
     * Dipanggil oleh RoomService setelah dua pemain matched.
     *
     * @param string $roomId
     * @param string $whiteUserId
     * @param string $blackUserId
     * @param string $timeControl  Format '10+5' atau '0' untuk unlimited
     * @return GameSession
     */
    public function createSession(
        string $roomId,
        string $whiteUserId,
        string $blackUserId,
        string $timeControl = '10+0'
    ): GameSession {
        $clock = $this->parseTimeControl($timeControl);

        $session = GameSession::create([
            'room_id'       => $roomId,
            'white_user_id' => $whiteUserId,
            'black_user_id' => $blackUserId,
            'time_control'  => $timeControl,
            'clock'         => [
                'white'     => $clock['minutes'] * 60,
                'black'     => $clock['minutes'] * 60,
                'increment' => $clock['increment'],
            ],
        ]);

        Log::info('[GameplayService] Sesi game dibuat', [
            'session_id'     => (string) $session->_id,
            'room_id'        => $roomId,
            'white_user_id'  => $whiteUserId,
            'black_user_id'  => $blackUserId,
            'time_control'   => $timeControl,
        ]);

        return $session;
    }

    /**
     * Tandai sesi sebagai aktif — dipanggil saat kedua pemain terhubung.
     *
     * @param string $sessionId
     * @return GameSession
     * @throws GameplayException
     */
    public function startSession(string $sessionId): GameSession
    {
        $session = $this->getSessionOrFail($sessionId);

        if ($session->status !== 'waiting') {
            throw GameplayException::sessionNotWaiting($sessionId, $session->status);
        }

        $session->startGame();

        Log::info('[GameplayService] Sesi game dimulai', [
            'session_id' => $sessionId,
        ]);

        return $session->fresh();
    }

    // ─────────────────────────────────────────────
    // MOVE
    // ─────────────────────────────────────────────

    /**
     * Submit dan proses move dari pemain.
     *
     * @param string $sessionId
     * @param string $userId         User yang mengirim move
     * @param string $from           Square asal, e.g. 'e2'
     * @param string $to             Square tujuan, e.g. 'e4'
     * @param string|null $promotion Piece promosi jika ada: 'q'|'r'|'b'|'n'
     * @param int    $timeSpentMs    Waktu yang dihabiskan (ms)
     *
     * @return array{ session: GameSession, move: MoveRecord, game_over: bool }
     * @throws GameplayException
     */
    public function submitMove(
        string  $sessionId,
        string  $userId,
        string  $from,
        string  $to,
        ?string $promotion  = null,
        int     $timeSpentMs = 0
    ): array {
        $session = $this->getSessionOrFail($sessionId);

        // Validasi status dan giliran
        if ($session->status !== 'active') {
            throw GameplayException::gameNotActive($sessionId, $session->status);
        }

        $playerColor = $session->getPlayerColor($userId);
        if ($playerColor === null) {
            throw GameplayException::playerNotInSession($userId, $sessionId);
        }

        if ($session->turn !== $playerColor) {
            throw GameplayException::notYourTurn($userId, $session->turn);
        }

        // Validasi legalitas move di posisi FEN saat ini
        $moveResult = $this->validator->validate($session->fen, $from, $to, $promotion);

        if (!$moveResult['legal']) {
            throw GameplayException::illegalMove($from, $to, $session->fen);
        }

        // Update jam (kurangi waktu, tambah increment)
        $clock       = $session->clock ?? ['white' => 600, 'black' => 600, 'increment' => 0];
        $increment   = (int) ($clock['increment'] ?? 0);
        $timeSpentSec = (int) ceil($timeSpentMs / 1000);

        $clock[$playerColor] = max(0, $clock[$playerColor] - $timeSpentSec + $increment);

        // Simpan move record
        $moveRecord = MoveRecord::create([
            'session_id'    => $sessionId,
            'room_id'       => $session->room_id,
            'user_id'       => $userId,
            'color'         => $playerColor,
            'move_number'   => $session->move_count + 1,
            'from'          => $from,
            'to'            => $to,
            'san'           => $moveResult['san'],
            'fen'           => $moveResult['fen'],
            'promotion'     => $promotion,
            'is_check'      => $moveResult['is_check'],
            'is_checkmate'  => $moveResult['is_checkmate'],
            'time_spent_ms' => $timeSpentMs,
            'played_at'     => now()->toISOString(),
        ]);

        // Update sesi
        $session->addMove([
            'from'      => $from,
            'to'        => $to,
            'san'       => $moveResult['san'],
            'fen'       => $moveResult['fen'],
            'promotion' => $promotion,
        ]);
        $session->updateClock($clock['white'], $clock['black']);
        $session->refresh();

        Log::info('[GameplayService] Move dimainkan', [
            'session_id'  => $sessionId,
            'user_id'     => $userId,
            'san'         => $moveResult['san'],
            'move_number' => $moveRecord->move_number,
        ]);

        // Cek kondisi game over
        $gameOver = false;
        if ($moveResult['is_checkmate']) {
            $result = $playerColor === 'white' ? 'white_wins' : 'black_wins';
            $this->finishGame($session, $result, 'checkmate');
            $gameOver = true;
        } elseif ($moveResult['is_stalemate'] || $moveResult['is_draw']) {
            $this->finishGame($session, 'draw', $moveResult['is_stalemate'] ? 'stalemate' : 'draw_agreement');
            $gameOver = true;
        }

        return [
            'session'   => $session->fresh(),
            'move'      => $moveRecord,
            'game_over' => $gameOver,
        ];
    }

    // ─────────────────────────────────────────────
    // RESIGN & DRAW
    // ─────────────────────────────────────────────

    /**
     * Pemain menyerah (resign).
     *
     * @param string $sessionId
     * @param string $userId     User yang menyerah
     * @return GameSession
     * @throws GameplayException
     */
    public function resign(string $sessionId, string $userId): GameSession
    {
        $session = $this->getSessionOrFail($sessionId);

        if ($session->status !== 'active') {
            throw GameplayException::gameNotActive($sessionId, $session->status);
        }

        $playerColor = $session->getPlayerColor($userId);
        if ($playerColor === null) {
            throw GameplayException::playerNotInSession($userId, $sessionId);
        }

        $result = $playerColor === 'white' ? 'black_wins' : 'white_wins';
        $this->finishGame($session, $result, 'resign');

        Log::info('[GameplayService] Pemain menyerah', [
            'session_id' => $sessionId,
            'user_id'    => $userId,
            'color'      => $playerColor,
        ]);

        return $session->fresh();
    }

    /**
     * Pemain meminta draw dan lawan menyetujui.
     * Endpoint ini dipanggil setelah kedua pemain setuju.
     *
     * @param string $sessionId
     * @return GameSession
     * @throws GameplayException
     */
    public function acceptDraw(string $sessionId): GameSession
    {
        $session = $this->getSessionOrFail($sessionId);

        if ($session->status !== 'active') {
            throw GameplayException::gameNotActive($sessionId, $session->status);
        }

        $this->finishGame($session, 'draw', 'draw_agreement');

        Log::info('[GameplayService] Draw disepakati', [
            'session_id' => $sessionId,
        ]);

        return $session->fresh();
    }

    /**
     * Pemain kehabisan waktu — timeout.
     * Dipanggil oleh scheduler atau WebSocket server saat jam habis.
     *
     * @param string $sessionId
     * @param string $timedOutColor  'white' | 'black'
     * @return GameSession
     */
    public function handleTimeout(string $sessionId, string $timedOutColor): GameSession
    {
        $session = $this->getSessionOrFail($sessionId);

        if ($session->status !== 'active') {
            return $session;
        }

        $result = $timedOutColor === 'white' ? 'black_wins' : 'white_wins';
        $this->finishGame($session, $result, 'timeout');

        Log::warning('[GameplayService] Timeout', [
            'session_id'     => $sessionId,
            'timed_out_color' => $timedOutColor,
        ]);

        return $session->fresh();
    }

    // ─────────────────────────────────────────────
    // QUERY
    // ─────────────────────────────────────────────

    /**
     * Ambil state board sesi tertentu.
     *
     * @param string $sessionId
     * @return array
     * @throws GameplayException
     */
    public function getBoard(string $sessionId): array
    {
        $session = $this->getSessionOrFail($sessionId);
        return $session->toBoardArray();
    }

    /**
     * Ambil daftar move sesi (dari move_records, diurutkan).
     *
     * @param string $sessionId
     * @return array
     */
    public function getMoves(string $sessionId): array
    {
        return MoveRecord::bySession($sessionId)
            ->orderBy('move_number', 'asc')
            ->get()
            ->map(fn($m) => $m->toMoveArray())
            ->values()
            ->toArray();
    }

    /**
     * Ambil riwayat game seorang user.
     *
     * @param string $userId
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public function getUserHistory(string $userId, int $limit = 20, int $offset = 0): array
    {
        $limit   = min($limit, 50);
        $records = GameSession::byUser($userId)
            ->finished()
            ->orderBy('finished_at', 'desc')
            ->skip($offset)
            ->limit($limit)
            ->get();

        $total = GameSession::byUser($userId)->finished()->count();

        return [
            'data'   => $records->map(fn($s) => $s->toSummaryArray())->values()->toArray(),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Ambil sesi aktif berdasarkan room.
     *
     * @param string $roomId
     * @return GameSession|null
     */
    public function getActiveByRoom(string $roomId): ?GameSession
    {
        return GameSession::byRoom($roomId)->active()->first();
    }

    // ─────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────

    /**
     * Selesaikan game: update record, laporkan ke RoomService.
     *
     * @param GameSession $session
     * @param string      $result  'white_wins' | 'black_wins' | 'draw'
     * @param string      $reason
     */
    private function finishGame(GameSession $session, string $result, string $reason): void
    {
        $session->finishGame($result, $reason);

        Log::info('[GameplayService] Game selesai', [
            'session_id' => (string) $session->_id,
            'result'     => $result,
            'reason'     => $reason,
        ]);

        // Laporkan hasil ke RoomService (non-blocking)
        dispatch(function () use ($session, $result, $reason) {
            $this->roomClient->reportMatchResult(
                roomId:    $session->room_id,
                sessionId: (string) $session->_id,
                result:    $result,
                reason:    $reason,
                whiteId:   $session->white_user_id,
                blackId:   $session->black_user_id,
            );
        })->afterResponse();
    }

    /**
     * Parse time control string ke menit dan increment.
     * Format: '10+5' → { minutes: 10, increment: 5 }
     *         '0'   → { minutes: 0, increment: 0 } (no limit)
     */
    private function parseTimeControl(string $timeControl): array
    {
        if ($timeControl === '0') {
            return ['minutes' => 0, 'increment' => 0];
        }

        $parts = explode('+', $timeControl);
        return [
            'minutes'   => (int) ($parts[0] ?? 10),
            'increment' => (int) ($parts[1] ?? 0),
        ];
    }

    /**
     * Ambil sesi atau lempar exception jika tidak ditemukan.
     *
     * @throws GameplayException
     */
    private function getSessionOrFail(string $sessionId): GameSession
    {
        $session = GameSession::find($sessionId);

        if (!$session) {
            throw GameplayException::sessionNotFound($sessionId);
        }

        return $session;
    }
}
