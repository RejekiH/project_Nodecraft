<?php

namespace App\Modules\BackupService\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * NodeStatus Model
 *
 * Collection MongoDB: node_statuses
 * Menyimpan status terkini setiap module yang memantau heartbeat.
 * Satu dokumen per module — upsert saat heartbeat diterima.
 *
 * @property string $_id          MongoDB ObjectId
 * @property string $module       Nama module (e.g. 'user-service', 'room-service')
 * @property string $status       'online' | 'offline' | 'degraded'
 * @property string $hostname     Hostname node yang mengirim heartbeat
 * @property string $last_seen    Waktu heartbeat terakhir diterima (ISO string)
 * @property int    $missed_beats Jumlah heartbeat yang terlewat berturut-turut
 * @property string $created_at
 * @property string $updated_at
 */
class NodeStatus extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'node_statuses';

    protected $fillable = [
        'module',
        'status',
        'hostname',
        'last_seen',
        'missed_beats',
    ];

    protected $casts = [
        'missed_beats' => 'integer',
    ];

    protected $attributes = [
        'status'       => 'offline',
        'missed_beats' => 0,
    ];

    // ─────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    // ─────────────────────────────────────────────
    // BUSINESS LOGIC
    // ─────────────────────────────────────────────

    /**
     * Update status saat heartbeat diterima.
     * Reset missed_beats ke 0 karena node baru saja melaporkan diri.
     */
    public function recordHeartbeat(string $hostname): void
    {
        $this->update([
            'status'       => 'online',
            'hostname'     => $hostname,
            'last_seen'    => now()->toISOString(),
            'missed_beats' => 0,
        ]);
    }

    /**
     * Tandai node sebagai degraded atau offline berdasarkan jumlah missed beats.
     * Dipanggil oleh scheduler pengecekan berkala.
     */
    public function incrementMissedBeats(): void
    {
        $missed = $this->missed_beats + 1;
        $status = $missed >= config('backup.offline_threshold', 5)
            ? 'offline'
            : 'degraded';

        $this->update([
            'missed_beats' => $missed,
            'status'       => $status,
        ]);
    }

    // ─────────────────────────────────────────────
    // SERIALIZATION
    // ─────────────────────────────────────────────

    public function toStatusArray(): array
    {
        return [
            'module'       => $this->module,
            'status'       => $this->status,
            'hostname'     => $this->hostname,
            'last_seen'    => $this->last_seen,
            'missed_beats' => $this->missed_beats,
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
