<?php

namespace App\Modules\RoomService\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Room Model
 *
 * Collection MongoDB: rooms
 * Digunakan oleh: RoomService module
 *
 * @property string      $_id            MongoDB ObjectId
 * @property string      $code           Kode unik room, 6 karakter uppercase (untuk join)
 * @property string      $host_id        User ID pembuat room
 * @property string|null $guest_id       User ID pemain kedua (null jika belum ada)
 * @property string      $status         waiting | in_progress | finished | cancelled
 * @property string      $time_control   '5+0' | '10+0' | '3+2' | dll (menit+inkremen)
 * @property string|null $winner_id      User ID pemenang (null jika belum/draw)
 * @property string|null $result         'white' | 'black' | 'draw' | null
 * @property string|null $end_reason     'checkmate' | 'timeout' | 'resign' | 'draw_agreement' | null
 * @property array|null  $pgn            Data PGN pertandingan setelah selesai
 * @property string      $created_at
 * @property string      $updated_at
 */
class Room extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rooms';

    protected $fillable = [
        'code',
        'host_id',
        'guest_id',
        'status',
        'time_control',
        'winner_id',
        'result',
        'end_reason',
        'pgn',
    ];

    protected $casts = [
        'pgn' => 'array',
    ];

    protected $attributes = [
        'status'     => 'waiting',
        'guest_id'   => null,
        'winner_id'  => null,
        'result'     => null,
        'end_reason' => null,
        'pgn'        => null,
    ];

    // ─────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeByPlayer($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('host_id', $userId)
              ->orWhere('guest_id', $userId);
        });
    }

    // ─────────────────────────────────────────────
    // ACCESSORS
    // ─────────────────────────────────────────────

    public function getIsFullAttribute(): bool
    {
        return $this->guest_id !== null;
    }

    public function getIsFinishedAttribute(): bool
    {
        return in_array($this->status, ['finished', 'cancelled']);
    }

    // ─────────────────────────────────────────────
    // BUSINESS LOGIC
    // ─────────────────────────────────────────────

    /**
     * Tambah guest ke room
     */
    public function addGuest(string $guestId): void
    {
        $this->update([
            'guest_id' => $guestId,
            'status'   => 'in_progress',
        ]);
    }

    /**
     * Selesaikan pertandingan dengan hasil akhir
     *
     * @param string      $result    'white' | 'black' | 'draw'
     * @param string      $endReason 'checkmate' | 'timeout' | 'resign' | 'draw_agreement'
     * @param string|null $winnerId  User ID pemenang (null jika draw)
     * @param array|null  $pgn       Data PGN
     */
    public function finish(string $result, string $endReason, ?string $winnerId, ?array $pgn = null): void
    {
        $this->update([
            'status'     => 'finished',
            'result'     => $result,
            'end_reason' => $endReason,
            'winner_id'  => $winnerId,
            'pgn'        => $pgn,
        ]);
    }

    /**
     * Batalkan room (host cancel sebelum mulai, atau guest disconnect)
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // ─────────────────────────────────────────────
    // SERIALIZATION
    // ─────────────────────────────────────────────

    /**
     * Data publik room (untuk list & detail)
     */
    public function toPublicArray(): array
    {
        return [
            'id'           => (string) $this->_id,
            'code'         => $this->code,
            'host_id'      => $this->host_id,
            'guest_id'     => $this->guest_id,
            'status'       => $this->status,
            'time_control' => $this->time_control,
            'winner_id'    => $this->winner_id,
            'result'       => $this->result,
            'end_reason'   => $this->end_reason,
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Detail room dengan PGN (untuk replay / backup)
     */
    public function toDetailArray(): array
    {
        return array_merge($this->toPublicArray(), [
            'pgn' => $this->pgn,
        ]);
    }
}
