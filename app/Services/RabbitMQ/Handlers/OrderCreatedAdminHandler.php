<?php

namespace App\Services\RabbitMQ\Handlers;

use App\Models\ProcessedEvent;
use App\Models\RealtimeEvent;
use Illuminate\Support\Facades\DB;

class OrderCreatedAdminHandler
{
    public function handle(string $eventId, array $payload): void
    {
        DB::transaction(function () use ($eventId, $payload) {
            $exists = ProcessedEvent::where('consumer_name', 'admin')
                ->where('event_id', $eventId)
                ->exists();

            if ($exists) {
                return;
            }

            $orderId = (int) ($payload['order_id'] ?? 0);

            if ($orderId <= 0) {
                throw new \RuntimeException('Invalid order ID.');
            }

            RealtimeEvent::create([
                'event_id' => $eventId,
                'event_type' => 'order.created',
                'order_id' => $orderId,
                'user_id' => null,
                'payload' => [
                    'order_id' => $orderId,
                ],
            ]);

            ProcessedEvent::create([
                'consumer_name' => 'admin',
                'event_id' => $eventId,
                'processed_at' => now(),
            ]);
        });
    }
}
