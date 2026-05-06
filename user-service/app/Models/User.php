<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * User Model
 * 
 * Collection MongoDB: users
 * Digunakan oleh: User Service (Fase 2), Backup Service (Fase 3)
 * 
 * @property string $_id            MongoDB ObjectId
 * @property string $username       Unique, lowercase, 3-20 karakter
 * @property string $email          Unique, terverifikasi
 * @property string $password       bcrypt hash
 * @property int    $rating         Poin ELO (mulai dari 0, +15 menang, -5 kalah)
 * @property int    $wins           Total kemenangan
 * @property int    $losses         Total kekalahan
 * @property int    $draws          Total seri
 * @property string $status         online | offline | in_game
 * @property array  $last_match_preview Preview match terakhir (untuk leaderboard)
 * @property string $created_at
 * @property string $updated_at
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    /**
     * Field yang boleh di-mass assign
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'rating',
        'wins',
        'losses',
        'draws',
        'status',
        'last_match_preview',
    ];

    /**
     * Field yang disembunyikan dari response JSON
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Cast tipe data
     */
    protected $casts = [
        'rating'  => 'integer',
        'wins'    => 'integer',
        'losses'  => 'integer',
        'draws'   => 'integer',
        'last_match_preview' => 'array',
    ];

    /**
     * Default values saat user baru dibuat
     */
    protected $attributes = [
        'rating'  => 0,
        'wins'    => 0,
        'losses'  => 0,
        'draws'   => 0,
        'status'  => 'offline',
        'last_match_preview' => null,
    ];

    // ─────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────

    /**
     * Scope: leaderboard (urut rating tertinggi)
     */
    public function scopeLeaderboard($query, int $limit = 20)
    {
        return $query->orderBy('rating', 'desc')->limit($limit);
    }

    /**
     * Scope: cari berdasarkan username atau email
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('username', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    // ─────────────────────────────────────────────
    // ACCESSORS & MUTATORS
    // ─────────────────────────────────────────────

    /**
     * Total games yang sudah dimainkan
     */
    public function getTotalGamesAttribute(): int
    {
        return $this->wins + $this->losses + $this->draws;
    }

    /**
     * Win rate dalam persen
     */
    public function getWinRateAttribute(): float
    {
        $total = $this->total_games;
        return $total > 0 ? round(($this->wins / $total) * 100, 1) : 0.0;
    }

    // ─────────────────────────────────────────────
    // BUSINESS LOGIC
    // ─────────────────────────────────────────────

    /**
     * Update rating setelah match selesai
     * 
     * @param string $result 'win' | 'loss' | 'draw'
     */
    public function applyMatchResult(string $result): void
    {
        $ratingChange = match ($result) {
            'win'  => +15,
            'loss' => -5,
            'draw' => 0,
        };

        $fieldMap = [
            'win'  => 'wins',
            'loss' => 'losses',
            'draw' => 'draws',
        ];

        // Rating tidak boleh negatif
        $newRating = max(0, $this->rating + $ratingChange);

        $this->update([
            'rating'          => $newRating,
            $fieldMap[$result] => $this->{$fieldMap[$result]} + 1,
        ]);
    }

    /**
     * Update preview match terakhir (untuk leaderboard)
     * 
     * @param array $preview {match_id, opponent_username, result, fen_final, moves_count}
     */
    public function updateLastMatchPreview(array $preview): void
    {
        $this->update(['last_match_preview' => $preview]);
    }

    /**
     * Set status user (online/offline/in_game)
     */
    public function setStatus(string $status): void
    {
        $allowed = ['online', 'offline', 'in_game'];
        if (!in_array($status, $allowed)) {
            throw new \InvalidArgumentException("Status tidak valid: {$status}");
        }
        $this->update(['status' => $status]);
    }

    /**
     * Return data publik (untuk leaderboard, profil orang lain)
     */
    public function toPublicArray(): array
    {
        return [
            'id'                 => (string) $this->_id,
            'username'           => $this->username,
            'rating'             => $this->rating,
            'wins'               => $this->wins,
            'losses'             => $this->losses,
            'draws'              => $this->draws,
            'total_games'        => $this->total_games,
            'win_rate'           => $this->win_rate,
            'status'             => $this->status,
            'last_match_preview' => $this->last_match_preview,
        ];
    }

    /**
     * Return data pribadi (untuk profil sendiri)
     */
    public function toPrivateArray(): array
    {
        return array_merge($this->toPublicArray(), [
            'email'      => $this->email,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ]);
    }
}
