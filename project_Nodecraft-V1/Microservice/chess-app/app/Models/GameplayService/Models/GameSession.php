<?php

namespace App\Modules\GameplayService\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * GameSession Model
 *
 * Collection MongoDB: game_sessions
 * Menyimpan satu sesi pertandingan catur dari awal hingga selesai.
 *
 * @property string      $_id            MongoDB ObjectId
 * @property string      $room_id        ID room dari RoomService
 * @property string      $white_user_id  User yang main putih
 * @property string      $black_user_id  User yang main hitam
 * @property string      $status         'waiting' | 'active' | 'finished' | 'aborted'
 * @property string      $result         'white_wins' | 'black_wins' | 'draw' | null
 * @property string|null $result_reason  'checkmate' | 'resign' | 'timeout' | 'draw_agreement' | 'stalemate' | null
 * @property string      $fen            Posisi board saat ini (FEN notation)
 * @property string      $turn           'white' | 'black' — giliran bermain sekarang
 * @property array       $moves          Array semua move yang sudah dimainkan
 * @property int         $move_count     Total jumlah half-moves
 * @property array       $clock          { white: detik, black: detik } — sisa waktu
 * @property string      $time_control   Format '10+5' (menit+increment) atau '0' untuk no limit
 * @property array       $meta           Info tambahan (variant, dll.)
 * @property string|null $started_at     Waktu move pertama dimainkan
 * @property string|null $finished_at    Waktu game selesai
 * @property string      $created_at
 * @property string      $updated_at
 */
class GameSession extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'game_sessions';

    protected $fillable = [
        'room_id',
        'white_user_id',
        'black_user_id',
        'status',
        'result',
        'result_reason',
        'fen',
        'turn',
        'moves',
        'move_count',
        'clock',
        'time_control',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'moves'      => 'array',
        'clock'      => 'array',
        'meta'       => 'array',
        'move_count' => 'integer',
    ];

    protected $attributes = [
        'status'        => 'waiting',
        'result'        => null,
        'result_reason' => null,
        'fen'           => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
        'turn'          => 'white',
        'moves'         => [],
        'move_count'    => 0,
        'meta'          => [],
        'started_at'    => null,
        'finished_at'   => null,
    ];

    // ─────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFinished($query)
    {
        return $query->where('status', 'finished');
    }

    public function scopeByRoom($query, string $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('white_user_id', $userId)
              ->orWhere('black_user_id', $userId);
        });
    }

    // ─────────────────────────────────────────────
    // BUSINESS LOGIC
    // ─────────────────────────────────────────────

    /**
     * Mulai game — ubah status menjadi active dan catat waktu mulai.
     */
    public function startGame(): void
    {
        $this->update([
            'status'     => 'active',
            'started_at' => now()->toISOString(),
        ]);
    }

    /**
     * Tambahkan move baru ke dalam sesi.
     *
     * @param array $moveData  { from, to, san, fen, promotion? }
     */
    public function addMove(array $moveData): void
    {
        $moves   = $this->moves ?? [];
        $moves[] = array_merge($moveData, [
            'played_at' => now()->toISOString(),
            'move_no'   => count($moves) + 1,
        ]);

        $this->update([
            'moves'      => $moves,
            'move_count' => count($moves),
            'fen'        => $moveData['fen'],
            'turn'       => $this->turn === 'white' ? 'black' : 'white',
        ]);
    }

    /**
     * Akhiri game — set status, hasil, dan waktu selesai.
     *
     * @param string $result  'white_wins' | 'black_wins' | 'draw'
     * @param string $reason  'checkmate' | 'resign' | 'timeout' | 'draw_agreement' | 'stalemate'
     */
    public function finishGame(string $result, string $reason): void
    {
        $this->update([
            'status'        => 'finished',
            'result'        => $result,
            'result_reason' => $reason,
            'finished_at'   => now()->toISOString(),
        ]);
    }

    /**
     * Batalkan game (disconnect, server error, dll.).
     */
    public function abortGame(string $reason = 'aborted'): void
    {
        $this->update([
            'status'        => 'aborted',
            'result_reason' => $reason,
            'finished_at'   => now()->toISOString(),
        ]);
    }

    /**
     * Update sisa waktu kedua pemain.
     */
    public function updateClock(int $whiteSeconds, int $blackSeconds): void
    {
        $this->update([
            'clock' => [
                'white' => $whiteSeconds,
                'black' => $blackSeconds,
            ],
        ]);
    }

    /**
     * Dapatkan user_id pemenang, atau null jika draw/belum selesai.
     */
    public function getWinnerId(): ?string
    {
        return match ($this->result) {
            'white_wins' => $this->white_user_id,
            'black_wins' => $this->black_user_id,
            default      => null,
        };
    }

    /**
     * Dapatkan user_id yang kalah, atau null jika draw/belum selesai.
     */
    public function getLoserId(): ?string
    {
        return match ($this->result) {
            'white_wins' => $this->black_user_id,
            'black_wins' => $this->white_user_id,
            default      => null,
        };
    }

    /**
     * Apakah user tertentu bermain di sesi ini?
     */
    public function hasPlayer(string $userId): bool
    {
        return $this->white_user_id === $userId || $this->black_user_id === $userId;
    }

    /**
     * Warna pemain berdasarkan user_id.
     */
    public function getPlayerColor(string $userId): ?string
    {
        if ($this->white_user_id === $userId) return 'white';
        if ($this->black_user_id === $userId) return 'black';
        return null;
    }

    // ─────────────────────────────────────────────
    // SERIALIZATION
    // ─────────────────────────────────────────────

    public function toSummaryArray(): array
    {
        return [
            'id'             => (string) $this->_id,
            'room_id'        => $this->room_id,
            'white_user_id'  => $this->white_user_id,
            'black_user_id'  => $this->black_user_id,
            'status'         => $this->status,
            'result'         => $this->result,
            'result_reason'  => $this->result_reason,
            'turn'           => $this->turn,
            'move_count'     => $this->move_count,
            'time_control'   => $this->time_control,
            'started_at'     => $this->started_at,
            'finished_at'    => $this->finished_at,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }

    public function toBoardArray(): array
    {
        return [
            'id'             => (string) $this->_id,
            'room_id'        => $this->room_id,
            'white_user_id'  => $this->white_user_id,
            'black_user_id'  => $this->black_user_id,
            'status'         => $this->status,
            'result'         => $this->result,
            'result_reason'  => $this->result_reason,
            'fen'            => $this->fen,
            'turn'           => $this->turn,
            'moves'          => $this->moves,
            'move_count'     => $this->move_count,
            'clock'          => $this->clock,
            'time_control'   => $this->time_control,
            'started_at'     => $this->started_at,
            'finished_at'    => $this->finished_at,
        ];
    }
}
