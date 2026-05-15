<?php

namespace App\Modules\BackupService\Services;

use App\Modules\BackupService\Models\BackupRecord;
use App\Modules\BackupService\Exceptions\BackupException;
use Illuminate\Support\Facades\Log;

/**
 * RestoreService
 *
 * Mengelola proses restore MongoDB dari file backup.
 * Dipanggil via endpoint POST /api/internal/backup/restore
 */
class RestoreService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = config('backup.storage_path', storage_path('backups'));
    }

    /**
     * Restore dari backup record ID tertentu.
     *
     * @param string $backupId  MongoDB ObjectId dari BackupRecord
     * @throws BackupException
     */
    public function restoreFromId(string $backupId): BackupRecord
    {
        $record = BackupRecord::find($backupId);

        if (!$record) {
            throw new BackupException("Backup record '{$backupId}' tidak ditemukan.", 404);
        }

        if ($record->status !== 'success') {
            throw new BackupException("Backup record '{$backupId}' statusnya {$record->status}, bukan success.", 422);
        }

        if (!$record->file_path || !file_exists($record->file_path)) {
            throw new BackupException("File backup tidak ditemukan di path: {$record->file_path}", 404);
        }

        return $this->executeRestore($record);
    }

    /**
     * Restore dari backup terbaru yang berhasil.
     *
     * @throws BackupException
     */
    public function restoreLatest(): BackupRecord
    {
        $record = BackupRecord::successful()->orderBy('created_at', 'desc')->first();

        if (!$record) {
            throw new BackupException('Tidak ada backup yang berhasil ditemukan.', 404);
        }

        return $this->executeRestore($record);
    }

    /**
     * Jalankan mongorestore dari file backup.
     *
     * @throws BackupException
     */
    private function executeRestore(BackupRecord $record): BackupRecord
    {
        $mongoHost = config('database.connections.mongodb.host', 'localhost');
        $mongoPort = config('database.connections.mongodb.port', 27017);
        $username  = config('database.connections.mongodb.username');
        $password  = config('database.connections.mongodb.password');

        $cmd = "mongorestore --host={$mongoHost} --port={$mongoPort}";

        if ($username && $password) {
            $cmd .= ' --username=' . escapeshellarg($username);
            $cmd .= ' --password=' . escapeshellarg($password);
            $cmd .= ' --authenticationDatabase=admin';
        }

        // --drop: hapus collection lama sebelum restore
        // --gzip --archive: sesuai format backup yang dibuat BackupService
        $cmd .= ' --drop --gzip --archive=' . escapeshellarg($record->file_path);
        $cmd .= ' 2>&1';

        Log::warning('[RestoreService] Memulai restore', [
            'backup_id'  => (string) $record->_id,
            'file_path'  => $record->file_path,
            'created_at' => $record->created_at,
        ]);

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $errorMsg = implode("\n", $output);
            Log::error('[RestoreService] Restore gagal', [
                'backup_id' => (string) $record->_id,
                'error'     => $errorMsg,
            ]);
            throw new BackupException('mongorestore gagal (exit ' . $exitCode . '): ' . $errorMsg, 500);
        }

        Log::warning('[RestoreService] Restore selesai', [
            'backup_id' => (string) $record->_id,
        ]);

        return $record;
    }
}
