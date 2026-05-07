<?php

namespace App\Modules\RoomService\Services;

use Illuminate\Support\Facades\Log;

/**
 * HeartbeatService
 *
 * Sesuai arsitektur: semua module mengirim event "heartbeat" ke RabbitMQ.
 * BackupService menerima heartbeat ini untuk monitoring ketersediaan node.
 *
 * Diagram alur data:
 *   Semua module → RabbitMQ (exchange: heartbeat, type: fanout) → BackupService
 */
class HeartbeatService
{
    private string $exchangeName = 'heartbeat';
    private string $moduleName   = 'room-service';

    public function publish(): void
    {
        $payload = [
            'module'    => $this->moduleName,
            'status'    => 'healthy',
            'timestamp' => now()->toISOString(),
            'hostname'  => gethostname(),
        ];

        try {
            $connection = $this->connect();
            $channel    = $connection->channel();

            $channel->exchange_declare(
                $this->exchangeName,
                'fanout',
                false, // passive
                true,  // durable
                false  // auto_delete
            );

            $message = new \PhpAmqpLib\Message\AMQPMessage(
                json_encode($payload),
                ['content_type' => 'application/json', 'delivery_mode' => 2]
            );

            $channel->basic_publish($message, $this->exchangeName);
            $channel->close();
            $connection->close();

            Log::debug('Heartbeat dikirim', $payload);

        } catch (\Exception $e) {
            // Heartbeat gagal tidak boleh crash aplikasi utama
            Log::warning('Heartbeat gagal dikirim', [
                'module' => $this->moduleName,
                'error'  => $e->getMessage(),
            ]);
        }
    }

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
