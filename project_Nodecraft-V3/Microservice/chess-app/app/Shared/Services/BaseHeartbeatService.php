<?php

namespace App\Shared\Services;

use Illuminate\Support\Facades\Log;

/**
 * FIX #12: HeartbeatService di UserService dan RoomService sebelumnya
 * duplikasi 100% — hanya berbeda di $moduleName. Bug fix harus dilakukan
 * di dua tempat secara manual.
 *
 * Solusi: abstract class ini menjadi satu-satunya implementasi.
 * Masing-masing module cukup extend dan set $moduleName.
 */
abstract class BaseHeartbeatService
{
    /**
     * Nama module untuk identifikasi di koleksi heartbeats.
     * Wajib di-override oleh child class.
     */
    abstract protected function getModuleName(): string;

    /**
     * Data tambahan spesifik per module (opsional untuk di-override).
     */
    protected function getAdditionalMetrics(): array
    {
        return [];
    }

    /**
     * Kirim heartbeat ke MongoDB dan log hasilnya.
     */
    public function send(): void
    {
        try {
            $metrics = array_merge(
                [
                    'source_module' => $this->getModuleName(),
                    'server_id'     => gethostname(),
                    'status'        => 'ok',
                    'load_percent'  => $this->getSystemLoad(),
                    'sent_at'       => now(),
                ],
                $this->getAdditionalMetrics()
            );

            \App\Modules\BackupService\Models\Heartbeat::create($metrics);

            Log::debug("[{$this->getModuleName()}] Heartbeat terkirim", $metrics);

        } catch (\Throwable $e) {
            Log::error("[{$this->getModuleName()}] Gagal kirim heartbeat: {$e->getMessage()}");
        }
    }

    /**
     * Ambil persentase load CPU sistem saat ini.
     */
    private function getSystemLoad(): float
    {
        $load = sys_getloadavg();
        return round(($load[0] ?? 0) * 100 / max(1, (int) shell_exec('nproc')), 1);
    }
}
