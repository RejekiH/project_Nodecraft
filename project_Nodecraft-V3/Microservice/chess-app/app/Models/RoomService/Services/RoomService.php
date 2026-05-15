<?php

namespace App\Modules\RoomService\Services;

use App\Modules\RoomService\Exceptions\RoomException;
use App\Modules\RoomService\Models\Room;
use App\Modules\GameplayService\Models\GameSession;
use Illuminate\Support\Facades\Log;

class RoomService
{
    public function __construct(
        private UserServiceClient $userServiceClient,
    ) {}

    // ─────────────────────────────────────────────
    // CREATE ROOM
    // ─────────────────────────────────────────────

    /**
     * Buat room baru.
     * Host memilih time_control, room code di-generate otomatis.
     */
    public function createRoom(string $hostId, string $timeControl = '10+0'): Room
    {
        // Pastikan host tidak sedang aktif di room lain
        $activeRoom = Room::where('host_id', $hostId)
            ->whereIn('status', ['waiting', 'in_progress'])
            ->first();

        if ($activeRoom) {
            throw new RoomException('Anda masih memiliki room aktif. Batalkan terlebih dahulu.', 409);
        }

        $room = Room::create([
            'code'         => $this->generateRoomCode(),
            'host_id'      => $hostId,
            'guest_id'     => null,
            'status'       => 'waiting',
            'time_control' => $timeControl,
            'winner_id'    => null,
            'result'       => null,
            'end_reason'   => null,
            'pgn'          => null,
        ]);

        Log::info('[RoomService] Room dibuat', [
            'room_id'      => (string) $room->_id,
            'code'         => $room->code,
            'host_id'      => $hostId,
            'time_control' => $timeControl,
        ]);

        return $room;
    }

    // ─────────────────────────────────────────────
    // JOIN ROOM
    // ─────────────────────────────────────────────

    /**
     * Guest join room via kode.
     * Setelah join, status room berubah menjadi in_progress.
     */
    public function joinRoom(string $code, string $guestId): Room
    {
        $room = Room::where('code', strtoupper($code))->first();

        if (!$room) {
            throw new RoomException("Room dengan kode '{$code}' tidak ditemukan.", 404);
        }

        if ($room->status !== 'waiting') {
            throw new RoomException('Room sudah tidak tersedia (status: ' . $room->status . ').', 409);
        }

        if ((string) $room->host_id === $guestId) {
            throw new RoomException('Anda tidak bisa join room milik sendiri.', 403);
        }

        $room->addGuest($guestId);

        Log::info('[RoomService] Guest join room', [
            'room_id'  => (string) $room->_id,
            'guest_id' => $guestId,
        ]);

        return $room->fresh();
    }

    // ─────────────────────────────────────────────
    // CANCEL ROOM
    // ─────────────────────────────────────────────

    /**
     * Host batalkan room (hanya saat masih waiting).
     */
    public function cancelRoom(string $roomId, string $userId): Room
    {
        $room = $this->getRoomById($roomId);

        if ((string) $room->host_id !== $userId) {
            throw new RoomException('Hanya host yang bisa membatalkan room.', 403);
        }

        if ($room->status !== 'waiting') {
            throw new RoomException('Room tidak bisa dibatalkan (status: ' . $room->status . ').', 409);
        }

        $room->cancel();

        return $room->fresh();
    }

    // ─────────────────────────────────────────────
    // FINISH ROOM
    // ─────────────────────────────────────────────

    /**
     * Selesaikan room setelah game berakhir.
     * Dipanggil oleh GameplayService via internal API.
     *
     * FIX: winner_id dihitung secara internal dari GameSession,
     * tidak dipercaya dari request body langsung.
     */
    public function finishRoom(string $roomId, array $data): Room
    {
        $room = $this->getRoomById($roomId);

        if ($room->status !== 'in_progress') {
            throw new RoomException('Room tidak sedang dalam pertandingan (status: ' . $room->status . ').', 409);
        }

        // Normalisasi result dari GameplayService format
        $result = match ($data['result']) {
            'white_wins' => 'white',
            'black_wins' => 'black',
            default      => $data['result'], // 'white', 'black', 'draw'
        };

        $endReason = $data['end_reason'];
        $winnerId  = $this->resolveWinnerId($room, $result, $data['session_id'] ?? null);

        $room->finish($result, $endReason, $winnerId, $data['pgn'] ?? null);

        Log::info('[RoomService] Room selesai', [
            'room_id'   => (string) $room->_id,
            'result'    => $result,
            'end_reason'=> $endReason,
            'winner_id' => $winnerId,
        ]);

        return $room->fresh();
    }

    // ─────────────────────────────────────────────
    // REMATCH
    // ─────────────────────────────────────────────

    /**
     * Catat vote rematch dari seorang pemain.
     * Jika kedua pemain vote → buat room baru dengan setting sama.
     *
     * @return array{ voted: bool, rematch_room: Room|null }
     */
    public function voteRematch(string $roomId, string $userId): array
    {
        $room = $this->getRoomById($roomId);

        if ($room->status !== 'finished') {
            throw new RoomException('Rematch hanya bisa diminta setelah pertandingan selesai.', 409);
        }

        // Tentukan role voter
        $isHost  = (string) $room->host_id  === $userId;
        $isGuest = (string) $room->guest_id === $userId;

        if (!$isHost && !$isGuest) {
            throw new RoomException('Anda bukan pemain di room ini.', 403);
        }

        // Update votes di meta
        $meta = $room->meta ?? [];
        $rematchVotes = $meta['rematch_votes'] ?? ['host' => false, 'guest' => false];

        if ($isHost)  $rematchVotes['host']  = true;
        if ($isGuest) $rematchVotes['guest'] = true;

        $meta['rematch_votes'] = $rematchVotes;
        $room->update(['meta' => $meta]);

        // Kedua sudah vote → buat room baru
        if ($rematchVotes['host'] && $rematchVotes['guest']) {
            // Tukar warna (host yang tadi putih jadi hitam, dst.)
            $newRoom = $this->createRoom(
                hostId      : (string) $room->guest_id,  // guest jadi host baru
                timeControl : $room->time_control,
            );

            // Langsung set guest_id ke host lama
            $newRoom->addGuest((string) $room->host_id);

            Log::info('[RoomService] Rematch room dibuat', [
                'old_room_id' => (string) $room->_id,
                'new_room_id' => (string) $newRoom->_id,
            ]);

            return ['voted' => true, 'rematch_room' => $newRoom->fresh()];
        }

        return ['voted' => true, 'rematch_room' => null];
    }

    // ─────────────────────────────────────────────
    // QUERY
    // ─────────────────────────────────────────────

    public function getRoomById(string $roomId): Room
    {
        $room = Room::find($roomId);
        if (!$room) {
            throw new RoomException("Room '{$roomId}' tidak ditemukan.", 404);
        }
        return $room;
    }

    public function getRoomByCode(string $code): Room
    {
        $room = Room::where('code', strtoupper($code))->first();
        if (!$room) {
            throw new RoomException("Room dengan kode '{$code}' tidak ditemukan.", 404);
        }
        return $room;
    }

    public function getWaitingRooms(int $limit = 20, int $offset = 0, ?string $timeControl = null): array
    {
        $limit = min($limit, 100);

        $query = Room::waiting();
        if ($timeControl) {
            $query->where('time_control', $timeControl);
        }

        $rooms = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->limit($limit)
            ->get();

        // Enrich dengan data host dari UserService
        $hostIds = $rooms->pluck('host_id')->map(fn($id) => (string) $id)->unique()->values()->toArray();
        $users   = [];

        if (!empty($hostIds)) {
            try {
                $users = $this->userServiceClient->batchLookup($hostIds);
            } catch (\Exception $e) {
                Log::warning('[RoomService] Gagal ambil data host', ['error' => $e->getMessage()]);
            }
        }

        $data = $rooms->map(function ($room) use ($users) {
            $arr = $room->toPublicArray();
            $arr['host_info'] = $users[(string) $room->host_id] ?? null;
            return $arr;
        })->values()->toArray();

        return [
            'data'   => $data,
            'total'  => Room::waiting()->count(),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    public function getUserHistory(string $userId, int $limit = 20, int $offset = 0): array
    {
        $limit = min($limit, 50);

        $rooms = Room::byPlayer($userId)
            ->whereIn('status', ['finished', 'cancelled'])
            ->orderBy('updated_at', 'desc')
            ->skip($offset)
            ->limit($limit)
            ->get();

        return [
            'data'   => $rooms->map(fn($r) => $r->toPublicArray())->values()->toArray(),
            'total'  => Room::byPlayer($userId)->whereIn('status', ['finished', 'cancelled'])->count(),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    // ─────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────

    /**
     * Hitung winner_id secara internal dari GameSession.
     * Tidak mempercaya winner_id dari request body.
     */
    private function resolveWinnerId(Room $room, string $result, ?string $sessionId = null): ?string
    {
        if ($result === 'draw') {
            return null;
        }

        // Cari GameSession untuk mendapatkan white/black player id
        $session = $sessionId
            ? GameSession::find($sessionId)
            : GameSession::where('room_id', (string) $room->_id)->latest()->first();

        if ($session) {
            $winnerId = $result === 'white'
                ? (string) $session->white_user_id
                : (string) $session->black_user_id;

            // Sanity check: pastikan winner adalah salah satu pemain room ini
            $validIds = array_filter([(string) $room->host_id, (string) $room->guest_id]);

            if (in_array($winnerId, $validIds)) {
                return $winnerId;
            }

            Log::error('[RoomService] Winner ID tidak cocok dengan pemain room', [
                'winner_id' => $winnerId,
                'valid_ids' => $validIds,
            ]);
        }

        // Fallback: tidak bisa tentukan winner
        Log::warning('[RoomService] GameSession tidak ditemukan, winner_id null', [
            'room_id'    => (string) $room->_id,
            'session_id' => $sessionId,
        ]);

        return null;
    }

    private function generateRoomCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Room::where('code', $code)->exists());

        return $code;
    }
}
