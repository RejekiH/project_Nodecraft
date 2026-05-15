<?php

namespace App\Modules\GameplayService\Services;

use App\Modules\GameplayService\Models\GameSession;
use App\Modules\GameplayService\Models\MoveRecord;
use App\Modules\GameplayService\Exceptions\GameplayException;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| FIX #1: Clock increment hilang setelah updateClock() dipanggil.
|
| Kode asli GameSession::updateClock() hanya menyimpan:
|   ['white' => $whiteSeconds, 'black' => $blackSeconds]
|
| Field 'increment' tidak disertakan → setiap move berikutnya
| increment = 0 karena $clock['increment'] return null → (int) null = 0.
| Pemain kehilangan increment setelah move pertama.
|
| Solusi: sertakan 'increment' saat memanggil updateClock() dan
| pastikan nilai dari clock sebelumnya selalu diteruskan.
|
| FIX #2: finishGame() menggunakan try/catch sinkron.
|
| Kode asli sudah benar menggunakan try/catch langsung (bukan
| dispatch()->afterResponse()). Ini sudah aman untuk dipanggil
| dari artisan command (CheckTimeoutsCommand) maupun HTTP context.
| Pertahankan pendekatan ini.
|--------------------------------------------------------------------------
*/
class GameplayService
{
    public function __construct(
        private ChessValidator    $validator,
        private RoomServiceClient $roomClient,
    ) {}

    // ─────────────────────────────────────────────
    // SESSION MANAGEMENT
    // ─────────────────────────────────────────────

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
            'session_id'    => (string) $session->_id,
            'room_id'       => $roomId,
            'white_user_id' => $whiteUserId,
            'black_user_id' => $blackUserId,
            'time_control'  => $timeControl,
        ]);

        return $session;
    }

    public function startSession(string $sessionId): GameSession
    {
        $session = $this->getSessionOrFail($sessionId);

        if ($session->status !== 'waiting') {
            throw GameplayException::sessionNotWaiting($sessionId, $session->status);
        }

        $session->startGame();

        return $session->fresh();
    }

    // ─────────────────────────────────────────────
    // MOVE
    // ─────────────────────────────────────────────

    public function submitMove(
        string  $sessionId,
        string  $userId,
        string  $from,
        string  $to,
        ?string $promotion   = null,
        int     $timeSpentMs = 0
    ): array {
        $session = $this->getSessionOrFail($sessionId);

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

        $moveResult = $this->validator->validate($session->fen, $from, $to, $promotion);

        if (!$moveResult['legal']) {
            throw GameplayException::illegalMove($from, $to, $session->fen);
        }

        // FIX #1: Baca clock lengkap termasuk 'increment'
        // Kode asli: $clock['increment'] bisa null jika updateClock() sebelumnya
        // tidak menyertakan field ini → (int) null = 0 → increment hilang.
        $clock       = $session->clock ?? ['white' => 600, 'black' => 600, 'increment' => 0];
        $increment   = (int) ($clock['increment'] ?? 0);
        $timeSpentSec = (int) ceil($timeSpentMs / 1000);

        $remainingAfterMove = $clock[$playerColor] - $timeSpentSec;
        if ($remainingAfterMove > 0) {
            $newRemaining = $remainingAfterMove + $increment;
        } else {
            $newRemaining = 0;
        }

        // FIX #1: Teruskan increment agar field tidak hilang dari MongoDB
        // Kode asli GameSession::updateClock() hanya simpan white & black,
        // tidak increment → field increment hilang dari dokumen setelah move.
        $newWhite = $playerColor === 'white' ? $newRemaining : $clock['white'];
        $newBlack = $playerColor === 'black' ? $newRemaining : $clock['black'];

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

        $session->addMove([
            'from'      => $from,
            'to'        => $to,
            'san'       => $moveResult['san'],
            'fen'       => $moveResult['fen'],
            'promotion' => $promotion,
        ]);

        // FIX #1: Panggil updateClock dengan increment disertakan
        $this->updateClockWithIncrement($session, $newWhite, $newBlack, $increment);
        $session->refresh();

        Log::info('[GameplayService] Move dimainkan', [
            'session_id'  => $sessionId,
            'user_id'     => $userId,
            'san'         => $moveResult['san'],
            'move_number' => $moveRecord->move_number,
        ]);

        $gameOver = false;
        if ($moveResult['is_checkmate']) {
            $result = $playerColor === 'white' ? 'white_wins' : 'black_wins';
            $this->finishGame($session, $result, 'checkmate');
            $gameOver = true;
        } elseif ($moveResult['is_stalemate']) {
            $this->finishGame($session, 'draw', 'stalemate');
            $gameOver = true;
        } elseif ($moveResult['is_draw']) {
            $this->finishGame($session, 'draw', 'draw_rule');
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

        return $session->fresh();
    }

    public function acceptDraw(string $sessionId): GameSession
    {
        $session = $this->getSessionOrFail($sessionId);

        if ($session->status !== 'active') {
            throw GameplayException::gameNotActive($sessionId, $session->status);
        }

        $this->finishGame($session, 'draw', 'draw_agreement');

        return $session->fresh();
    }

    public function handleTimeout(string $sessionId, string $timedOutColor): GameSession
    {
        $session = $this->getSessionOrFail($sessionId);

        if ($session->status !== 'active') {
            return $session;
        }

        $result = $timedOutColor === 'white' ? 'black_wins' : 'white_wins';
        $this->finishGame($session, $result, 'timeout');

        return $session->fresh();
    }

    // ─────────────────────────────────────────────
    // QUERY
    // ─────────────────────────────────────────────

    public function getBoard(string $sessionId): array
    {
        return $this->getSessionOrFail($sessionId)->toBoardArray();
    }

    public function getMoves(string $sessionId): array
    {
        return MoveRecord::bySession($sessionId)
            ->orderBy('move_number', 'asc')
            ->get()
            ->map(fn($m) => $m->toMoveArray())
            ->values()
            ->toArray();
    }

    public function getUserHistory(string $userId, int $limit = 20, int $offset = 0): array
    {
        $limit   = min($limit, 50);
        $records = GameSession::byUser($userId)
            ->finished()
            ->orderBy('finished_at', 'desc')
            ->skip($offset)
            ->limit($limit)
            ->get();

        return [
            'data'   => $records->map(fn($s) => $s->toSummaryArray())->values()->toArray(),
            'total'  => GameSession::byUser($userId)->finished()->count(),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    public function getActiveByRoom(string $roomId): ?GameSession
    {
        return GameSession::byRoom($roomId)->active()->first();
    }

    // ─────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────

    /*
     * FIX #1: updateClockWithIncrement() memastikan field 'increment'
     * selalu ikut tersimpan ke MongoDB. GameSession::updateClock() asli
     * hanya menyimpan white dan black, sehingga increment hilang.
     */
    private function updateClockWithIncrement(
        GameSession $session,
        int $white,
        int $black,
        int $increment
    ): void {
        $session->update([
            'clock' => [
                'white'     => $white,
                'black'     => $black,
                'increment' => $increment,
            ],
        ]);
    }

    /*
     * FIX #2: finishGame() menggunakan try/catch sinkron, bukan
     * dispatch()->afterResponse(). Ini memastikan hasil selalu
     * dilaporkan ke RoomService terlepas dari context (HTTP atau CLI).
     *
     * Jika RoomService timeout, error di-log tapi tidak membatalkan
     * penyelesaian game — game tetap dianggap selesai di sisi GameplayService.
     */
    private function finishGame(GameSession $session, string $result, string $reason): void
    {
        $session->finishGame($result, $reason);

        Log::info('[GameplayService] Game selesai', [
            'session_id' => (string) $session->_id,
            'result'     => $result,
            'reason'     => $reason,
        ]);

        try {
            $this->roomClient->reportMatchResult(
                roomId:    $session->room_id,
                sessionId: (string) $session->_id,
                result:    $result,
                reason:    $reason,
                whiteId:   $session->white_user_id,
                blackId:   $session->black_user_id,
            );
        } catch (\Exception $e) {
            Log::error('[GameplayService] Gagal lapor hasil ke RoomService', [
                'session_id' => (string) $session->_id,
                'result'     => $result,
                'error'      => $e->getMessage(),
            ]);
        }
    }

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

    private function getSessionOrFail(string $sessionId): GameSession
    {
        $session = GameSession::find($sessionId);
        if (!$session) {
            throw GameplayException::sessionNotFound($sessionId);
        }
        return $session;
    }
}
