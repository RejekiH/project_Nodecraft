<?php

namespace App\Modules\BackupService\Services;

use App\Modules\BackupService\Models\NodeStatus;
use Illuminate\Support\Facades\Log;

/**
 * HeartbeatMonitorService
 *
 * Menerima event heartbeat dari RabbitMQ dan memperbarui status node.
 *
 * Diagram alur data:
 *   Semua module → RabbitMQ (exchange: heartbeat, type: fanout)
 *     → BackupService (consumer) → NodeStatus collection
 *
 * Heartbeat payload dari module pengirim:
 * {
 *   "module":    "user-service",
 *   "status":    "healthy",
 *   "timestamp": "2026-05-07T03:00:00.000Z",
 *   "hostname":  "node-01"
 * }
 */
class HeartbeatMonitorService
{
    private string $exchangeName   = 'heartbeat';
    private string $queueName      = 'backup-service.heartbeat';

    // ─────────────────────────────────────────────
    // CONSUME (dijalankan via artisan command)
    // ─────────────────────────────────────────────

    /**
     * Mulai consume heartbeat dari RabbitMQ.
     * Blocking — jalankan sebagai long-running process (supervisor).
     *
     * @param callable|null $onMessage  Callback opsional untuk testing
     */
    public function startConsuming(?callable $onMessage = null): void
    {
        $connection = $this->connect();
        $channel    = $connection->channel();

        // Deklarasi exchange fanout (harus sama dengan pengirim)
        $channel->exchange_declare(
            $this->exchangeName,
            'fanout',
            false,  // passive
            true,   // durable
            false   // auto_delete
        );

        // Queue khusus BackupService — durable agar tidak hilang saat restart
        $channel->queue_declare(
            $this->queueName,
            false,  // passive
            true,   // durable
            false,  // exclusive
            false   // auto_delete
        );

        // Bind queue ke exchange
        $channel->queue_bind($this->queueName, $this->exchangeName);

        Log::info('[BackupService] Mulai mendengarkan heartbeat', [
            'exchange' => $this->exchangeName,
            'queue'    => $this->queueName,
        ]);

        $callback = function (\PhpAmqpLib\Message\AMQPMessage $msg) use ($onMessage) {
            $this->processHeartbeat($msg);
            $msg->ack();

            if ($onMessage) {
                $onMessage($msg);
            }
        };

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume(
            $this->queueName,
            '',     // consumer tag
            false,  // no_local
            false,  // no_ack — kita manual ack
            false,  // exclusive
            false,  // nowait
            $callback
        );

        // Blocking loop
        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    // ─────────────────────────────────────────────
    // PROCESS
    // ─────────────────────────────────────────────

    /**
     * Proses satu pesan heartbeat dari RabbitMQ.
     * Upsert NodeStatus berdasarkan nama module.
     */
    public function processHeartbeat(\PhpAmqpLib\Message\AMQPMessage $msg): void
    {
        $payload = json_decode($msg->getBody(), true);

        if (!isset($payload['module'], $payload['hostname'])) {
            Log::warning('[BackupService] Heartbeat payload tidak valid', [
                'body' => $msg->getBody(),
            ]);
            return;
        }

        $module   = $payload['module'];
        $hostname = $payload['hostname'];

        // Upsert: buat dokumen baru jika belum ada, update jika sudah ada
        $node = NodeStatus::firstOrCreate(['module' => $module]);
        $node->recordHeartbeat($hostname);

        Log::debug('[BackupService] Heartbeat diterima', [
            'module'   => $module,
            'hostname' => $hostname,
        ]);
    }

    // ─────────────────────────────────────────────
    // STATUS QUERY
    // ─────────────────────────────────────────────

    /**
     * Ambil status semua node yang dimonitor
     */
    public function getAllStatuses(): array
    {
        return NodeStatus::orderBy('module')
            ->get()
            ->map(fn($n) => $n->toStatusArray())
            ->values()
            ->toArray();
    }

    /**
     * Ambil status satu module spesifik
     */
    public function getModuleStatus(string $module): ?array
    {
        $node = NodeStatus::where('module', $module)->first();
        return $node ? $node->toStatusArray() : null;
    }

    /**
     * Cek node mana saja yang tidak mengirim heartbeat dalam batas waktu.
     * Dipanggil oleh scheduler untuk menandai node degraded/offline.
     */
    public function checkStaleNodes(): void
    {
        $thresholdMinutes = config('backup.heartbeat_stale_minutes', 3);
        $cutoff           = now()->subMinutes($thresholdMinutes)->toISOString();

        $staleNodes = NodeStatus::where('status', 'online')
            ->where('last_seen', '<', $cutoff)
            ->get();

        foreach ($staleNodes as $node) {
            $node->incrementMissedBeats();

            Log::warning('[BackupService] Node tidak mengirim heartbeat', [
                'module'       => $node->module,
                'last_seen'    => $node->last_seen,
                'missed_beats' => $node->missed_beats,
                'new_status'   => $node->fresh()->status,
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // CONNECTION
    // ─────────────────────────────────────────────

    private function connect(): \PhpAmqpLib\Connection\AMQPStreamConnection
    {
        return new \PhpAmqpLib\Connection\AMQPStreamConnection(
            host:     config('queue.connections.rabbitmq.host', 'localhost'),
            port:     config('queue.connections.rabbitmq.port', 5672),
            user:     config('queue.connections.rabbitmq.login', 'guest'),
            password: config('queue.connections.rabbitmq.password', 'guest'),
            vhost:    config('queue.connections.rabbitmq.vhost', '/'),
        );
    }
}
