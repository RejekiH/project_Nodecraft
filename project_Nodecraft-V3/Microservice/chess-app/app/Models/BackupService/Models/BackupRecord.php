<?php

namespace App\Modules\BackupService\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * BackupRecord Model
 *
 * Collection MongoDB: backup_records
 * Menyimpan riwayat setiap backup yang pernah dijalankan.
 *
 * @property string $_id          MongoDB ObjectId
 * @property string $type         'full' | 'incremental' | 'manual'
 * @property string $status       'running' | 'success' | 'failed'
 * @property string $triggered_by 'scheduler' | 'manual' | 'match_result'
 * @property string $file_path    Path file hasil backup
 * @property int    $file_size    Ukuran file dalam bytes (0 jika belum selesai)
 * @property string|null $error   Pesan error jika gagal
 * @property array  $meta         Info tambahan (database, durasi, dll.)
 * @property string $created_at
 * @property string $updated_at
 */
class BackupRecord extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'backup_records';

    protected $fillable = [
        'type',
        'status',
        'triggered_by',
        'file_path',
        'file_size',
        'error',
        'meta',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'meta'      => 'array',
    ];

    protected $attributes = [
        'status'    => 'running',
        'file_size' => 0,
        'error'     => null,
        'meta'      => [],
    ];

    // ─────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ─────────────────────────────────────────────
    // BUSINESS LOGIC
    // ─────────────────────────────────────────────

    public function markSuccess(string $filePath, int $fileSize, array $meta = []): void
    {
        $this->update([
            'status'    => 'success',
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'meta'      => array_merge($this->meta ?? [], $meta),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error'  => $error,
        ]);
    }

    // ─────────────────────────────────────────────
    // SERIALIZATION
    // ─────────────────────────────────────────────

    public function toSummaryArray(): array
    {
        return [
            'id'           => (string) $this->_id,
            'type'         => $this->type,
            'status'       => $this->status,
            'triggered_by' => $this->triggered_by,
            'file_path'    => $this->file_path,
            'file_size'    => $this->file_size,
            'error'        => $this->error,
            'meta'         => $this->meta,
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
