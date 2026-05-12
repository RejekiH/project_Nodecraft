<?php

namespace App\Modules\RoomService\Services;

use App\Modules\RoomService\Exceptions\RoomException;
use App\Modules\RoomService\Models\Room;
use App\Modules\GameplayService\Models\GameSession;

class RoomService
{
    /**
     * Selesaikan room setelah game berakhir.
     *
     * FIX #10: winner_id sebelumnya diterima langsung dari request body
     * tanpa verifikasi apapun. Pemain bisa mengirim winner_id sembarang
     * dan data korup akan tersimpan ke MongoDB.
     *
     * Sekarang winner_id dihitung secara internal dari data GameSession
     * berdasarkan result ('white'/'black'/'draw') yang dikirim client.
     * Client hanya boleh mengirim 'result', bukan winner_id langsung.
     */
    public function finishRoom(Room $room, array $data): Room
    {
        if ($room->status !== 'active') {
            throw RoomException::roomNotActive();
        }

        $result    = $data['result'];    // 'white' | 'black' | 'draw'
        $endReason = $data['end_reason']; // 'checkmate' | 'timeout' | 'resign' | 'draw'

        // FIX #10: Resolve winner_id secara internal dari GameSession
        // Jangan percaya winner_id yang datang dari request body
        $winnerId = $this->resolveWinnerId($room, $result);

        $room->update([
            'status'      => 'finished',
            'winner_id'   => $winnerId,
            'end_reason'  => $endReason,
            'finished_at' => now(),
            'updated_at'  => now(),
        ]);

        return $room->fresh();
    }

    /**
     * Hitung winner_id secara internal berdasarkan warna pemenang dan data room.
     *
     * FIX #10: Satu-satunya cara menentukan pemenang yang aman.
     * host_color menentukan apakah host bermain putih atau hitam.
     */
    private function resolveWinnerId(Room $room, string $result): ?string
    {
        if ($result === 'draw') {
            return null;
        }

        // Cari GameSession untuk mendapatkan white_player_id dan black_player_id
        $session = GameSession::where('room_id', $room->_id)->first();

        if (! $session) {
            // Fallback ke logika room jika session tidak ditemukan
            if ($result === 'white') {
                return $room->host_color === 'white'
                    ? (string) $room->host_id
                    : (string) $room->guest_id;
            }

            return $room->host_color === 'black'
                ? (string) $room->host_id
                : (string) $room->guest_id;
        }

        // Resolusi dari session yang lebih akurat
        if ($result === 'white') {
            $winnerId = (string) $session->white_player_id;
        } else {
            $winnerId = (string) $session->black_player_id;
        }

        // Sanity check: pastikan winner adalah salah satu pemain di room ini
        $validPlayerIds = [
            (string) $room->host_id,
            (string) $room->guest_id,
        ];

        if (! in_array($winnerId, array_filter($validPlayerIds))) {
            throw RoomException::invalidWinner();
        }

        return $winnerId;
    }

    public function createRoom(string $userId, array $data): Room
    {
        return Room::create([
            'room_code'    => $this->generateRoomCode(),
            'host_id'      => $userId,
            'host_color'   => $data['color'] ?? 'white',
            'status'       => 'waiting',
            'rematch_votes'=> ['host' => false, 'guest' => false],
            'server_id'    => gethostname(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function joinRoom(Room $room, string $userId): Room
    {
        if ($room->status !== 'waiting') {
            throw RoomException::roomNotAvailable();
        }

        if ((string) $room->host_id === $userId) {
            throw RoomException::cannotJoinOwnRoom();
        }

        $room->update([
            'guest_id'  => $userId,
            'status'    => 'active',
            'started_at'=> now(),
            'updated_at'=> now(),
        ]);

        return $room->fresh();
    }

    private function generateRoomCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
        } while (Room::where('room_code', $code)->exists());

        return $code;
    }
}
