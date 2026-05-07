<?php

namespace App\Modules\RoomService\Controllers;

use App\Modules\RoomService\Services\RoomService;
use App\Modules\RoomService\Requests\CreateRoomRequest;
use App\Modules\RoomService\Requests\JoinRoomRequest;
use App\Modules\RoomService\Requests\FinishRoomRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RoomController
 *
 * Endpoint publik (butuh JWT):
 *   POST /api/room                    - Buat room baru
 *   POST /api/room/join               - Join room via kode
 *   GET  /api/room                    - List room waiting
 *   GET  /api/room/{id}               - Detail room berdasarkan ID
 *   GET  /api/room/code/{code}        - Detail room berdasarkan kode
 *   DELETE /api/room/{id}             - Cancel room (host only)
 *   GET  /api/room/history/{userId}   - Riwayat pertandingan user
 *
 * Endpoint internal (butuh X-Internal-Key):
 *   POST /api/internal/room/{id}/finish - Simpan hasil akhir pertandingan
 *   GET  /api/internal/room/{id}        - Detail room dengan PGN
 */
class RoomController extends Controller
{
    public function __construct(private RoomService $roomService) {}

    // ─────────────────────────────────────────────
    // POST /api/room
    // ─────────────────────────────────────────────

    /**
     * Buat room baru
     *
     * Header: Authorization: Bearer <token>
     * Body: { time_control: "5+0" }
     *
     * Response 201:
     * {
     *   "success": true,
     *   "message": "Room berhasil dibuat",
     *   "data": { id, code, host_id, status, time_control, ... }
     * }
     */
    public function store(CreateRoomRequest $request): JsonResponse
    {
        $room = $this->roomService->createRoom(
            hostId:      (string) $request->user->_id,
            timeControl: $request->time_control,
        );

        return response()->json([
            'success' => true,
            'message' => 'Room berhasil dibuat',
            'data'    => $room->toPublicArray(),
        ], 201);
    }

    // ─────────────────────────────────────────────
    // POST /api/room/join
    // ─────────────────────────────────────────────

    /**
     * Join room via kode
     *
     * Header: Authorization: Bearer <token>
     * Body: { code: "AB3X9Z" }
     *
     * Response 200:
     * { "success": true, "message": "Berhasil masuk ke room", "data": { ...room } }
     */
    public function join(JoinRoomRequest $request): JsonResponse
    {
        $room = $this->roomService->joinRoom(
            code:    $request->code,
            guestId: (string) $request->user->_id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Berhasil masuk ke room',
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/room
    // ─────────────────────────────────────────────

    /**
     * List room waiting
     *
     * Query params: limit, offset, time_control
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": [ { ...room, host_info: { username, rating } } ],
     *   "meta": { total, limit, offset }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->roomService->getWaitingRooms(
            limit:       (int) $request->query('limit', 20),
            offset:      (int) $request->query('offset', 0),
            timeControl: $request->query('time_control'),
        );

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
    // GET /api/room/{id}
    // ─────────────────────────────────────────────

    /**
     * Detail room berdasarkan ID
     *
     * Response 200:
     * { "success": true, "data": { ...room } }
     */
    public function show(string $id): JsonResponse
    {
        $room = $this->roomService->getRoomById($id);

        return response()->json([
            'success' => true,
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/room/code/{code}
    // ─────────────────────────────────────────────

    /**
     * Detail room berdasarkan kode
     *
     * Response 200:
     * { "success": true, "data": { ...room } }
     */
    public function showByCode(string $code): JsonResponse
    {
        $room = $this->roomService->getRoomByCode($code);

        return response()->json([
            'success' => true,
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // DELETE /api/room/{id}
    // ─────────────────────────────────────────────

    /**
     * Cancel room (host only, hanya bisa saat waiting)
     *
     * Header: Authorization: Bearer <token>
     *
     * Response 200:
     * { "success": true, "message": "Room dibatalkan", "data": { ...room } }
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $room = $this->roomService->cancelRoom($id, (string) $request->user->_id);

        return response()->json([
            'success' => true,
            'message' => 'Room dibatalkan',
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/room/history/{userId}
    // ─────────────────────────────────────────────

    /**
     * Riwayat pertandingan selesai milik seorang user
     *
     * Header: Authorization: Bearer <token>
     * Query params: limit, offset
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": [ { ...room } ],
     *   "meta": { total, limit, offset }
     * }
     */
    public function history(Request $request, string $userId): JsonResponse
    {
        $result = $this->roomService->getUserHistory(
            userId: $userId,
            limit:  (int) $request->query('limit', 20),
            offset: (int) $request->query('offset', 0),
        );

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
    // INTERNAL: POST /api/internal/room/{id}/finish
    // ─────────────────────────────────────────────

    /**
     * Simpan hasil akhir pertandingan.
     * Dipanggil oleh GameplayService setelah match selesai.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     * Body: {
     *   result: 'white'|'black'|'draw',
     *   end_reason: 'checkmate'|'timeout'|'resign'|'draw_agreement',
     *   winner_id?: string,
     *   pgn?: array
     * }
     *
     * Response 200:
     * { "success": true, "data": { ...room } }
     */
    public function finish(FinishRoomRequest $request, string $id): JsonResponse
    {
        $room = $this->roomService->finishRoom($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil disimpan',
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // INTERNAL: GET /api/internal/room/{id}
    // ─────────────────────────────────────────────

    /**
     * Detail room lengkap dengan PGN — untuk BackupService / replay.
     *
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     *
     * Response 200:
     * { "success": true, "data": { ...room, pgn: [...] } }
     */
    public function internalShow(string $id): JsonResponse
    {
        $room = $this->roomService->getRoomById($id);

        return response()->json([
            'success' => true,
            'data'    => $room->toDetailArray(),
        ]);
    }
}
