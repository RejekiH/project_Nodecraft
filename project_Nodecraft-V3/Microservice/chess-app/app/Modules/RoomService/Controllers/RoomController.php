<?php

namespace App\Modules\RoomService\Controllers;

use App\Modules\RoomService\Services\RoomService;
use App\Modules\RoomService\Requests\CreateRoomRequest;
use App\Modules\RoomService\Requests\JoinRoomRequest;
use App\Modules\RoomService\Requests\FinishRoomRequest;
use App\Modules\RoomService\Exceptions\RoomException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RoomController extends Controller
{
    public function __construct(private RoomService $roomService) {}

    // ── Health ────────────────────────────────────────────────────────────

    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'room-service',
            'status'  => 'healthy',
            'time'    => now()->toISOString(),
        ]);
    }

    // ── POST /api/room ────────────────────────────────────────────────────

    public function store(CreateRoomRequest $request): JsonResponse
    {
        $room = $this->roomService->createRoom(
            hostId      : (string) $request->user->_id,
            timeControl : $request->time_control,
        );

        return response()->json([
            'success' => true,
            'message' => 'Room berhasil dibuat',
            'data'    => $room->toPublicArray(),
        ], 201);
    }

    // ── POST /api/room/join ───────────────────────────────────────────────

    public function join(JoinRoomRequest $request): JsonResponse
    {
        $room = $this->roomService->joinRoom(
            code   : $request->code,
            guestId: (string) $request->user->_id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Berhasil masuk ke room',
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ── GET /api/room ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $result = $this->roomService->getWaitingRooms(
            limit      : (int) $request->query('limit', 20),
            offset     : (int) $request->query('offset', 0),
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

    // ── GET /api/room/{id} ────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $room = $this->roomService->getRoomById($id);

        return response()->json([
            'success' => true,
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ── GET /api/room/code/{code} ─────────────────────────────────────────

    public function showByCode(string $code): JsonResponse
    {
        $room = $this->roomService->getRoomByCode($code);

        return response()->json([
            'success' => true,
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ── DELETE /api/room/{id} ─────────────────────────────────────────────

    public function cancel(Request $request, string $id): JsonResponse
    {
        $room = $this->roomService->cancelRoom($id, (string) $request->user->_id);

        return response()->json([
            'success' => true,
            'message' => 'Room dibatalkan',
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ── GET /api/room/history/{userId} ────────────────────────────────────

    public function history(Request $request, string $userId): JsonResponse
    {
        $result = $this->roomService->getUserHistory(
            userId: $userId,
            limit : (int) $request->query('limit', 20),
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

    // ── POST /api/internal/room/{id}/match-result ─────────────────────────

    public function finish(FinishRoomRequest $request, string $id): JsonResponse
    {
        $room = $this->roomService->finishRoom($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil disimpan',
            'data'    => $room->toPublicArray(),
        ]);
    }

    // ── GET /api/internal/room/{id} ───────────────────────────────────────

    public function internalShow(string $id): JsonResponse
    {
        $room = $this->roomService->getRoomById($id);

        return response()->json([
            'success' => true,
            'data'    => $room->toDetailArray(),
        ]);
    }

    // ── POST /api/room/{id}/rematch ───────────────────────────────────────

    /**
     * Vote rematch setelah pertandingan selesai.
     * Jika kedua pemain vote → buat room baru, return room baru.
     *
     * Header: Authorization: Bearer <token>
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "voted": true,
     *     "rematch_room": { ...room } | null   ← null jika lawan belum vote
     *   }
     * }
     */
    public function rematch(Request $request, string $id): JsonResponse
    {
        $result = $this->roomService->voteRematch($id, (string) $request->user->_id);

        return response()->json([
            'success' => true,
            'message' => $result['rematch_room']
                ? 'Rematch dimulai! Room baru telah dibuat.'
                : 'Vote rematch tercatat. Menunggu pemain lain.',
            'data'    => [
                'voted'        => $result['voted'],
                'rematch_room' => $result['rematch_room']?->toPublicArray(),
            ],
        ]);
    }
}
