<?php

namespace App\Modules\GameplayService\Controllers;

use App\Modules\GameplayService\Services\GameplayService;
use App\Modules\GameplayService\Requests\CreateSessionRequest;
use App\Modules\GameplayService\Requests\SubmitMoveRequest;
use App\Modules\GameplayService\Requests\ResignRequest;
use App\Modules\GameplayService\Exceptions\GameplayException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GameplayController
 *
 * Endpoint:
 *   GET  /api/gameplay/health                              - Health check publik
 *   POST /api/internal/gameplay/session                    - Buat sesi game baru
 *   POST /api/internal/gameplay/session/{id}/start         - Mulai sesi (kedua pemain connected)
 *   GET  /api/internal/gameplay/session/{id}/board         - Ambil state board
 *   GET  /api/internal/gameplay/session/{id}/moves         - Ambil list semua move
 *   POST /api/internal/gameplay/session/{id}/move          - Submit move
 *   POST /api/internal/gameplay/session/{id}/resign        - Resign
 *   POST /api/internal/gameplay/session/{id}/draw          - Setuju draw
 *   POST /api/internal/gameplay/session/{id}/timeout       - Handle timeout (dari scheduler)
 *   GET  /api/internal/gameplay/room/{roomId}/active       - Ambil sesi aktif berdasarkan room
 *   GET  /api/internal/gameplay/user/{userId}/history      - Riwayat game user
 */
class GameplayController extends Controller
{
    public function __construct(
        private GameplayService $gameplayService,
    ) {}

    // ─────────────────────────────────────────────
    // GET /api/gameplay/health
    // ─────────────────────────────────────────────

    /**
     * Health check publik.
     *
     * Response 200:
     * { "success": true, "service": "gameplay-service", "status": "healthy" }
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'gameplay-service',
            'status'  => 'healthy',
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/gameplay/session
    // ─────────────────────────────────────────────

    /**
     * Buat sesi game baru.
     * Dipanggil oleh RoomService setelah matchmaking berhasil.
     *
     * Body:
     * {
     *   "room_id":        "...",
     *   "white_user_id":  "...",
     *   "black_user_id":  "...",
     *   "time_control":   "10+5"   (opsional, default "10+0")
     * }
     *
     * Response 201:
     * { "success": true, "data": { ...session } }
     */
    public function createSession(CreateSessionRequest $request): JsonResponse
    {
        $session = $this->gameplayService->createSession(
            roomId:       $request->input('room_id'),
            whiteUserId:  $request->input('white_user_id'),
            blackUserId:  $request->input('black_user_id'),
            timeControl:  $request->input('time_control', '10+0'),
        );

        return response()->json([
            'success' => true,
            'data'    => $session->toBoardArray(),
        ], 201);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/gameplay/session/{id}/start
    // ─────────────────────────────────────────────

    /**
     * Tandai sesi sebagai aktif — kedua pemain telah terhubung.
     *
     * Response 200:
     * { "success": true, "data": { ...session } }
     */
    public function startSession(string $id): JsonResponse
    {
        try {
            $session = $this->gameplayService->startSession($id);
        } catch (GameplayException $e) {
            return $this->gameplayError($e);
        }

        return response()->json([
            'success' => true,
            'data'    => $session->toBoardArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/internal/gameplay/session/{id}/board
    // ─────────────────────────────────────────────

    /**
     * Ambil state board sesi tertentu.
     *
     * Response 200:
     * { "success": true, "data": { fen, turn, moves, clock, ... } }
     */
    public function getBoard(string $id): JsonResponse
    {
        try {
            $board = $this->gameplayService->getBoard($id);
        } catch (GameplayException $e) {
            return $this->gameplayError($e);
        }

        return response()->json([
            'success' => true,
            'data'    => $board,
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/internal/gameplay/session/{id}/moves
    // ─────────────────────────────────────────────

    /**
     * Ambil semua move dalam sesi — berguna untuk replay/analisis.
     *
     * Response 200:
     * { "success": true, "data": [ ...moves ] }
     */
    public function getMoves(string $id): JsonResponse
    {
        $moves = $this->gameplayService->getMoves($id);

        return response()->json([
            'success' => true,
            'data'    => $moves,
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/gameplay/session/{id}/move
    // ─────────────────────────────────────────────

    /**
     * Submit move dari pemain.
     *
     * Body:
     * {
     *   "user_id":       "...",
     *   "from":          "e2",
     *   "to":            "e4",
     *   "promotion":     "q"      (opsional)
     *   "time_spent_ms": 1500     (opsional)
     * }
     *
     * Response 200:
     * {
     *   "success":   true,
     *   "data": {
     *     "session":   { ...state terbaru },
     *     "move":      { ...move yang baru dimainkan },
     *     "game_over": false
     *   }
     * }
     */
    public function submitMove(SubmitMoveRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->gameplayService->submitMove(
                sessionId:   $id,
                userId:      $request->input('user_id'),
                from:        $request->input('from'),
                to:          $request->input('to'),
                promotion:   $request->input('promotion'),
                timeSpentMs: (int) $request->input('time_spent_ms', 0),
            );
        } catch (GameplayException $e) {
            return $this->gameplayError($e);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'session'   => $result['session']->toBoardArray(),
                'move'      => $result['move']->toMoveArray(),
                'game_over' => $result['game_over'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/gameplay/session/{id}/resign
    // ─────────────────────────────────────────────

    /**
     * Pemain menyerah.
     *
     * Body: { "user_id": "..." }
     *
     * Response 200:
     * { "success": true, "data": { ...session } }
     */
    public function resign(ResignRequest $request, string $id): JsonResponse
    {
        try {
            $session = $this->gameplayService->resign($id, $request->input('user_id'));
        } catch (GameplayException $e) {
            return $this->gameplayError($e);
        }

        return response()->json([
            'success' => true,
            'data'    => $session->toBoardArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/gameplay/session/{id}/draw
    // ─────────────────────────────────────────────

    /**
     * Kedua pemain menyetujui draw.
     *
     * Response 200:
     * { "success": true, "data": { ...session } }
     */
    public function acceptDraw(string $id): JsonResponse
    {
        try {
            $session = $this->gameplayService->acceptDraw($id);
        } catch (GameplayException $e) {
            return $this->gameplayError($e);
        }

        return response()->json([
            'success' => true,
            'data'    => $session->toBoardArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/internal/gameplay/session/{id}/timeout
    // ─────────────────────────────────────────────

    /**
     * Handle timeout — dipanggil scheduler/WebSocket server.
     *
     * Body: { "timed_out_color": "white" | "black" }
     *
     * Response 200:
     * { "success": true, "data": { ...session } }
     */
    public function handleTimeout(Request $request, string $id): JsonResponse
    {
        $color   = $request->input('timed_out_color');
        $session = $this->gameplayService->handleTimeout($id, $color);

        return response()->json([
            'success' => true,
            'data'    => $session->toBoardArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/internal/gameplay/room/{roomId}/active
    // ─────────────────────────────────────────────

    /**
     * Ambil sesi aktif berdasarkan room ID.
     *
     * Response 200:
     * { "success": true, "data": { ...session } | null }
     */
    public function getActiveByRoom(string $roomId): JsonResponse
    {
        $session = $this->gameplayService->getActiveByRoom($roomId);

        return response()->json([
            'success' => true,
            'data'    => $session ? $session->toBoardArray() : null,
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/internal/gameplay/user/{userId}/history
    // ─────────────────────────────────────────────

    /**
     * Riwayat game seorang user.
     *
     * Query: limit (default 20, max 50), offset (default 0)
     *
     * Response 200:
     * { "success": true, "data": [...], "meta": { total, limit, offset } }
     */
    public function getUserHistory(Request $request, string $userId): JsonResponse
    {
        $limit  = (int) $request->query('limit', 20);
        $offset = (int) $request->query('offset', 0);

        $result = $this->gameplayService->getUserHistory($userId, $limit, $offset);

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => [
                'total'  => $result['total'],
                'limit'  => $result['limit'],
                'offset' => $result['offset'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // ERROR HELPER
    // ─────────────────────────────────────────────

    private function gameplayError(GameplayException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => [
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
            ],
        ], $e->getHttpStatus());
    }
}
