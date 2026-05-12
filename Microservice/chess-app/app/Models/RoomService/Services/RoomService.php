<?php

namespace App\Modules\RoomService\Services;

use App\Modules\RoomService\Models\Room;
use App\Modules\RoomService\Exceptions\RoomException;
use Illuminate\Support\Facades\Log;

/**
 * RoomService
 *
 * Core business logic untuk manajemen room pertandingan catur.
 * Controller hanya menangani HTTP — semua logika ada di sini.
 *
 * Tanggung jawab modul ini (sesuai deskripsi proyek):
 *   - Buat room baru (host memilih time control)
 *   - Join room via kode unik
 *   - Lihat daftar room tersedia (waiting)
 *   - Detail room berdasarkan ID atau kode
 *   - Simpan hasil akhir pertandingan + update rating user via UserService
 *   - Cancel room (host sebelum lawan masuk)
 *   - Endpoint internal untuk update hasil dari GameplayService
 */
class RoomService
{
    // Time control yang valid (format: menit+inkremen)
    private const VALID_TIME_CONTROLS = ['1+0', '3+0', '3+2', '5+0', '5+3', '10+0', '10+5', '15+10', '30+0'];

    public function __construct(private UserServiceClient $userServiceClient) {}

    // ─────────────────────────────────────────────
    // BUAT ROOM
    // ─────────────────────────────────────────────

    /**
     * Buat room baru. Host tidak boleh punya room aktif.
     *
     * @param string $hostId      User ID pembuat room
     * @param string $timeControl Format: '5+0', '10+0', dll
     * @return Room
     * @throws RoomException
     */
    public function createRoom(string $hostId, string $timeControl): Room
    {
        $this->validateTimeControl($timeControl);
        $this->assertNotInActiveRoom($hostId);

        $room = Room::create([
            'code'         => $this->generateUniqueCode(),
            'host_id'      => $hostId,
            'time_control' => $timeControl,
            'status'       => 'waiting',
        ]);

        Log::info('Room dibuat', [
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
     * Bergabung ke room via kode.
     * Guest tidak boleh join room miliknya sendiri.
     * Guest tidak boleh punya room aktif lain.
     *
     * @param string $code    Kode room 6 karakter
     * @param string $guestId User ID yang join
     * @return Room
     * @throws RoomException
     */
    public function joinRoom(string $code, string $guestId): Room
    {
        $room = Room::where('code', strtoupper($code))->first();

        if (!$room) {
            throw new RoomException('Room tidak ditemukan', 404);
        }

        if ($room->status !== 'waiting') {
            throw new RoomException('Room sudah penuh atau tidak dapat dimasuki', 409);
        }

        if ($room->host_id === $guestId) {
            throw new RoomException('Tidak bisa join room milik sendiri', 400);
        }

        $this->assertNotInActiveRoom($guestId);

        $room->addGuest($guestId);
        $room->refresh();

        Log::info('Guest join room', [
            'room_id'  => (string) $room->_id,
            'code'     => $room->code,
            'guest_id' => $guestId,
        ]);

        return $room;
    }

    // ─────────────────────────────────────────────
    // LIST ROOM
    // ─────────────────────────────────────────────

    /**
     * Daftar room yang sedang menunggu pemain.
     *
     * @param int $limit   max 50
     * @param int $offset
     * @param string|null $timeControl Filter berdasarkan time control
     */
    public function getWaitingRooms(int $limit = 20, int $offset = 0, ?string $timeControl = null): array
    {
        $limit = min($limit, 50);

        $query = Room::waiting()->orderBy('created_at', 'desc');

        if ($timeControl) {
            $query->where('time_control', $timeControl);
        }

        $rooms = $query->skip($offset)->limit($limit)->get();
        $total = Room::waiting()->when($timeControl, fn($q) => $q->where('time_control', $timeControl))->count();

        // Enrich dengan data user dari UserService
        $hostIds = $rooms->pluck('host_id')->unique()->values()->toArray();
        $users   = $this->safeUserBatch($hostIds);

        return [
            'data'   => $rooms->map(fn($r) => $this->enrichRoom($r, $users))->values()->toArray(),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    // ─────────────────────────────────────────────
    // DETAIL ROOM
    // ─────────────────────────────────────────────

    /**
     * Detail room berdasarkan ID
     *
     * @throws RoomException
     */
    public function getRoomById(string $roomId): Room
    {
        $room = Room::find($roomId);
        if (!$room) {
            throw new RoomException('Room tidak ditemukan', 404);
        }
        return $room;
    }

    /**
     * Detail room berdasarkan kode
     *
     * @throws RoomException
     */
    public function getRoomByCode(string $code): Room
    {
        $room = Room::where('code', strtoupper($code))->first();
        if (!$room) {
            throw new RoomException('Room tidak ditemukan', 404);
        }
        return $room;
    }

    // ─────────────────────────────────────────────
    // CANCEL ROOM
    // ─────────────────────────────────────────────

    /**
     * Host bisa cancel room selama masih waiting (belum ada lawan).
     *
     * @throws RoomException
     */
    public function cancelRoom(string $roomId, string $requesterId): Room
    {
        $room = $this->getRoomById($roomId);

        if ($room->host_id !== $requesterId) {
            throw new RoomException('Hanya host yang bisa cancel room', 403);
        }

        if ($room->status !== 'waiting') {
            throw new RoomException('Room tidak dapat dibatalkan (sudah dimulai atau selesai)', 409);
        }

        $room->cancel();
        $room->refresh();

        Log::info('Room dibatalkan', [
            'room_id' => (string) $room->_id,
            'host_id' => $requesterId,
        ]);

        return $room;
    }

    // ─────────────────────────────────────────────
    // FINISH ROOM (internal / dari GameplayService)
    // ─────────────────────────────────────────────

    /**
     * Simpan hasil akhir pertandingan dan update rating pemain.
     * Dipanggil oleh GameplayService setelah pertandingan selesai.
     *
     * @param string $roomId
     * @param array  $data {result, end_reason, winner_id?, pgn?}
     * @return Room
     * @throws RoomException
     */
    public function finishRoom(string $roomId, array $data): Room
    {
        $room = $this->getRoomById($roomId);

        if ($room->status !== 'in_progress') {
            throw new RoomException("Room tidak dalam status in_progress (status: {$room->status})", 409);
        }

        $result    = $data['result'];      // 'white' | 'black' | 'draw' | 'white_wins' | 'black_wins'
        $endReason = $data['end_reason'];  // 'checkmate' | 'timeout' | 'resign' | 'draw_agreement' | 'stalemate' | 'draw_rule'
        $winnerId  = $data['winner_id'] ?? null;
        $pgn       = $data['pgn'] ?? null;
        $sessionId = $data['session_id'] ?? null;

        // Normalisasi hasil dari GameplayService ('white_wins'/'black_wins' → 'white'/'black')
        // Fix: GameplayService mengirim 'white_wins'/'black_wins', RoomService menerima 'white'/'black'
        $result = match ($result) {
            'white_wins' => 'white',
            'black_wins' => 'black',
            default      => $result,
        };

        $room->finish($result, $endReason, $winnerId, $pgn);
        $room->refresh();

        // Update rating kedua pemain di UserService (non-blocking)
        $this->updatePlayerRatings($room, $result, $winnerId, $sessionId);

        Log::info('Room selesai', [
            'room_id'    => (string) $room->_id,
            'result'     => $result,
            'end_reason' => $endReason,
            'winner_id'  => $winnerId,
        ]);

        return $room;
    }

    // ─────────────────────────────────────────────
    // RIWAYAT ROOM USER
    // ─────────────────────────────────────────────

    /**
     * Riwayat pertandingan selesai milik seorang user
     *
     * @param string $userId
     * @param int    $limit
     * @param int    $offset
     */
    public function getUserHistory(string $userId, int $limit = 20, int $offset = 0): array
    {
        $limit = min($limit, 50);

        $rooms = Room::byPlayer($userId)
                     ->whereIn('status', ['finished', 'cancelled'])
                     ->orderBy('updated_at', 'desc')
                     ->skip($offset)
                     ->limit($limit)
                     ->get();

        $total = Room::byPlayer($userId)
                     ->whereIn('status', ['finished', 'cancelled'])
                     ->count();

        return [
            'data'   => $rooms->map(fn($r) => $r->toPublicArray())->values()->toArray(),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    // ─────────────────────────────────────────────
    // HELPERS — PRIVATE
    // ─────────────────────────────────────────────

    /**
     * Pastikan user tidak punya room aktif (waiting atau in_progress)
     *
     * @throws RoomException
     */
    private function assertNotInActiveRoom(string $userId): void
    {
        $active = Room::byPlayer($userId)
                      ->whereIn('status', ['waiting', 'in_progress'])
                      ->exists();

        if ($active) {
            throw new RoomException('Kamu masih memiliki room aktif. Selesaikan atau batalkan dulu.', 409);
        }
    }

    /**
     * Generate kode room unik 6 karakter uppercase
     */
    private function generateUniqueCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa karakter ambigu (0,O,1,I)
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Room::where('code', $code)->exists());

        return $code;
    }

    /**
     * Validasi time control
     *
     * @throws RoomException
     */
    private function validateTimeControl(string $tc): void
    {
        if (!in_array($tc, self::VALID_TIME_CONTROLS)) {
            throw new RoomException(
                'Time control tidak valid. Pilihan: ' . implode(', ', self::VALID_TIME_CONTROLS),
                422
            );
        }
    }

    /**
     * Panggil UserService batchLookup, tapi tidak crash jika gagal
     */
    private function safeUserBatch(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        try {
            return $this->userServiceClient->batchLookup($userIds);
        } catch (\Exception $e) {
            Log::warning('Gagal fetch user data dari UserService', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Enrich data room dengan info user (username, rating)
     */
    private function enrichRoom(Room $room, array $users): array
    {
        $data              = $room->toPublicArray();
        $data['host_info'] = $users[$room->host_id] ?? null;
        return $data;
    }

    /**
     * Update rating kedua pemain setelah match selesai.
     * Dipanggil asinkron — error tidak propagate.
     *
     * @param string      $result    'white' | 'black' | 'draw'
     * @param string|null $sessionId match_id dari GameplayService, untuk field preview.match_id
     */
    private function updatePlayerRatings(Room $room, string $result, ?string $winnerId, ?string $sessionId = null): void
    {
        $hostId  = $room->host_id;
        $guestId = $room->guest_id;

        if (!$guestId) {
            return;
        }

        // Tentukan hasil untuk masing-masing pemain
        if ($result === 'draw') {
            $hostResult  = 'draw';
            $guestResult = 'draw';
        } elseif ($winnerId === $hostId) {
            $hostResult  = 'win';
            $guestResult = 'loss';
        } else {
            $hostResult  = 'loss';
            $guestResult = 'win';
        }

        // Fix: sertakan match_id (session_id dari GameplayService) agar preview konsisten
        // Sebelumnya hanya ada room_id — field match_id di ApplyMatchResultRequest tidak terisi
        $preview = [
            'room_id'    => (string) $room->_id,
            'match_id'   => $sessionId,
            'result'     => $result,
            'end_reason' => $room->end_reason,
        ];

        $this->userServiceClient->applyMatchResult($hostId, $hostResult, array_merge($preview, ['opponent_id' => $guestId]));
        $this->userServiceClient->applyMatchResult($guestId, $guestResult, array_merge($preview, ['opponent_id' => $hostId]));
    }
}
