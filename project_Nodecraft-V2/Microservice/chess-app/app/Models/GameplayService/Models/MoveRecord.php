<?php

namespace App\Modules\GameplayService\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * MoveRecord Model
 *
 * Collection MongoDB: move_records
 * Menyimpan setiap move dalam format terpisah untuk keperluan analisis
 * dan replay. Dalam sesi aktif, moves juga disimpan embedded di GameSession.
 *
 * @property string  $_id           MongoDB ObjectId
 * @property string  $session_id    ID GameSession
 * @property string  $room_id       ID room (denormalisasi untuk query cepat)
 * @property string  $user_id       Siapa yang membuat move ini
 * @property string  $color         'white' | 'black'
 * @property int     $move_number   Nomor move (1-based half-moves)
 * @property string  $from          Square asal, e.g. 'e2'
 * @property string  $to            Square tujuan, e.g. 'e4'
 * @property string  $san           Standard Algebraic Notation, e.g. 'e4', 'Nf3', 'O-O'
 * @property string  $fen           FEN board setelah move ini
 * @property string|null $promotion Piece jika promosi pion: 'q' | 'r' | 'b' | 'n'
 * @property bool    $is_check      Apakah move ini menghasilkan check
 * @property bool    $is_checkmate  Apakah move ini menghasilkan checkmate
 * @property int     $time_spent_ms Waktu yang dihabiskan untuk move ini (ms)
 * @property string  $played_at     Waktu move dimainkan (ISO string)
 * @property string  $created_at
 */
class MoveRecord extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'move_records';

    protected $fillable = [
        'session_id',
        'room_id',
        'user_id',
        'color',
        'move_number',
        'from',
        'to',
        'san',
        'fen',
        'promotion',
        'is_check',
        'is_checkmate',
        'time_spent_ms',
        'played_at',
    ];

    protected $casts = [
        'move_number'   => 'integer',
        'is_check'      => 'boolean',
        'is_checkmate'  => 'boolean',
        'time_spent_ms' => 'integer',
    ];

    protected $attributes = [
        'promotion'     => null,
        'is_check'      => false,
        'is_checkmate'  => false,
        'time_spent_ms' => 0,
    ];

    // ─────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────

    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByRoom($query, string $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    // ─────────────────────────────────────────────
    // SERIALIZATION
    // ─────────────────────────────────────────────

    public function toMoveArray(): array
    {
        return [
            'id'            => (string) $this->_id,
            'move_number'   => $this->move_number,
            'color'         => $this->color,
            'from'          => $this->from,
            'to'            => $this->to,
            'san'           => $this->san,
            'fen'           => $this->fen,
            'promotion'     => $this->promotion,
            'is_check'      => $this->is_check,
            'is_checkmate'  => $this->is_checkmate,
            'time_spent_ms' => $this->time_spent_ms,
            'played_at'     => $this->played_at,
        ];
    }
}
