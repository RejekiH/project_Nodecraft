<?php

namespace App\Modules\BackupService\Services;

use App\Modules\BackupService\Models\BackupRecord;
use Illuminate\Support\Facades\Log;

/**
 * BackupService
 *
 * Mengelola pembuatan backup MongoDB menggunakan mongodump.
 *
 * Alur backup:
 *   1. Buat BackupRecord dengan status 'running'
 *   2. Jalankan mongodump → file .gz di storage/backups/
 *   3. Update BackupRecord: status 'success' + path file
 *   4. Hapus backup lama jika melebihi batas retensi
 *
 * Dipanggil oleh:
 *   - Scheduler (backup otomatis harian/jam)
 *   - BackupController (trigger manual via API internal)
 *   - UserServiceClient (setelah proses match-result selesai)
 */
class BackupService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = config('backup.storage_path', storage_path('backups'));
    }

    // ─────────────────────────────────────────────
    // BACKUP
    // ─────────────────────────────────────────────

    /**
     * Jalankan backup penuh semua database MongoDB.
     *
     * @param string $triggeredBy  'scheduler' | 'manual' | 'match_result'
     * @return BackupRecord
     */
    public function runFullBackup(string $triggeredBy = 'scheduler'): BackupRecord
    {
        $record = BackupRecord::create([
            'type'         => 'full',
            'status'       => 'running',
            'triggered_by' => $triggeredBy,
            'file_path'    => '',
            'meta'         => ['databases' => $this->getDatabases()],
        ]);

        Log::info('[BackupService] Mulai backup penuh', [
            'record_id'    => (string) $record->_id,
            'triggered_by' => $triggeredBy,
        ]);

        try {
            $filePath = $this->executeMongodump($record);
            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

            $record->markSuccess($filePath, $fileSize, [
                'duration_seconds' => $this->getDuration($record),
            ]);

            Log::info('[BackupService] Backup selesai', [
                'record_id' => (string) $record->_id,
                'file_path' => $filePath,
                'file_size' => $fileSize,
            ]);

        } catch (\Exception $e) {
            $record->markFailed($e->getMessage());

            Log::error('[BackupService] Backup gagal', [
                'record_id' => (string) $record->_id,
                'error'     => $e->getMessage(),
            ]);
        }

        // Bersihkan backup lama
        $this->pruneOldBackups();

        return $record->fresh();
    }

    /**
     * Backup incremental — hanya collection yang berubah sejak backup terakhir.
     * Menggunakan oplog MongoDB untuk menangkap perubahan.
     *
     * @param string $triggeredBy
     * @return BackupRecord
     */
    public function runIncrementalBackup(string $triggeredBy = 'scheduler'): BackupRecord
    {
        $record = BackupRecord::create([
            'type'         => 'incremental',
            'status'       => 'running',
            'triggered_by' => $triggeredBy,
            'file_path'    => '',
            'meta'         => [],
        ]);

        Log::info('[BackupService] Mulai backup incremental', [
            'record_id' => (string) $record->_id,
        ]);

        try {
            $filePath = $this->executeMongodump($record, incremental: true);
            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

            $record->markSuccess($filePath, $fileSize, [
                'duration_seconds' => $this->getDuration($record),
            ]);

            Log::info('[BackupService] Backup incremental selesai', [
                'record_id' => (string) $record->_id,
                'file_path' => $filePath,
            ]);

        } catch (\Exception $e) {
            $record->markFailed($e->getMessage());

            Log::error('[BackupService] Backup incremental gagal', [
                'record_id' => (string) $record->_id,
                'error'     => $e->getMessage(),
            ]);
        }

        // Fix: pruneOldBackups() sebelumnya hanya dipanggil di runFullBackup().
        // Backup incremental yang dijalankan setiap jam akan menumpuk tanpa batas
        // jika tidak dibersihkan. Tambahkan pruning di sini juga.
        $this->pruneOldBackups();

        return $record->fresh();
    }

    // ─────────────────────────────────────────────
    // HISTORY
    // ─────────────────────────────────────────────

    /**
     * Ambil riwayat backup
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getHistory(int $limit = 20, int $offset = 0): array
    {
        $limit   = min($limit, 100);
        $records = BackupRecord::orderBy('created_at', 'desc')
            ->skip($offset)
            ->limit($limit)
            ->get();

        $total = BackupRecord::count();

        return [
            'data'   => $records->map(fn($r) => $r->toSummaryArray())->values()->toArray(),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Ambil backup terakhir yang berhasil
     */
    public function getLastSuccessful(): ?BackupRecord
    {
        return BackupRecord::successful()->orderBy('created_at', 'desc')->first();
    }

    // ─────────────────────────────────────────────
    // PRUNE
    // ─────────────────────────────────────────────

    /**
     * Hapus backup file lama yang melebihi batas retensi.
     * Konfigurasi: backup.retention_days (default: 7 hari)
     */
    public function pruneOldBackups(): void
    {
        $retentionDays = config('backup.retention_days', 7);
        $cutoff        = now()->subDays($retentionDays);

        $oldRecords = BackupRecord::successful()
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($oldRecords as $record) {
            // Hapus file fisik
            if ($record->file_path && file_exists($record->file_path)) {
                unlink($record->file_path);
                Log::info('[BackupService] File backup lama dihapus', [
                    'file_path' => $record->file_path,
                ]);
            }

            // Hapus record dari database
            $record->delete();
        }

        if ($oldRecords->count() > 0) {
            Log::info('[BackupService] Pruning selesai', [
                'deleted_count' => $oldRecords->count(),
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────

    /**
     * Jalankan mongodump dan return path file hasil backup.
     *
     * @throws \RuntimeException jika mongodump gagal
     */
    private function executeMongodump(BackupRecord $record, bool $incremental = false): string
    {
        $timestamp = now()->format('Ymd_His');
        $type      = $incremental ? 'incremental' : 'full';
        $filename  = "backup_{$type}_{$timestamp}.gz";
        $filePath  = rtrim($this->backupDir, '/') . '/' . $filename;

        // Pastikan direktori backup ada
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $mongoHost = config('database.connections.mongodb.host', 'localhost');
        $mongoPort = config('database.connections.mongodb.port', 27017);
        $username  = config('database.connections.mongodb.username');
        $password  = config('database.connections.mongodb.password');

        $cmd = "mongodump --host={$mongoHost} --port={$mongoPort}";

        if ($username && $password) {
            $cmd .= " --username=" . escapeshellarg($username);
            $cmd .= " --password=" . escapeshellarg($password);
            $cmd .= " --authenticationDatabase=admin";
        }

        if ($incremental) {
            // Oplog untuk incremental — hanya tersedia di replica set
            $cmd .= " --oplog";
        }

        $cmd .= " --gzip --archive=" . escapeshellarg($filePath);
        $cmd .= " 2>&1";

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'mongodump gagal (exit code ' . $exitCode . '): ' . implode("\n", $output)
            );
        }

        return $filePath;
    }

    /**
     * Hitung durasi backup dalam detik sejak record dibuat
     */
    private function getDuration(BackupRecord $record): int
    {
        return now()->diffInSeconds($record->created_at);
    }

    /**
     * Daftar database yang akan di-backup (dari konfigurasi)
     */
    private function getDatabases(): array
    {
        return config('backup.databases', ['nodechess_users', 'nodechess_rooms']);
    }
}
