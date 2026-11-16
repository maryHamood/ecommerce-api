<?php


namespace App\Services\RabbitMQ\Handlers;

use App\Models\ProcessedEvent;
use App\Models\RealtimeEvent;
use Illuminate\Support\Facades\DB;

class OrderStatusChangedCustomerHandler
{
    public function handle(string $eventId, array $payload): void
    {
        DB::transaction(function () use ($eventId, $payload) {
            $exists = ProcessedEvent::where('consumer_name', 'customer')
                ->where('event_id', $eventId)
                ->exists();

            if ($exists) {
                return;
            }

            $orderId = (int)($payload['order_id'] ?? 0);
            $userId = (int)($payload['user_id'] ?? 0);
            $status = $payload['status'] ?? null;

            if ($orderId <= 0 || $userId <= 0 || !is_string($status)) {
                throw new \RuntimeException('Invalid order status event payload.');
            }

            RealtimeEvent::create([
                'event_id' => $eventId,
                'event_type' => 'order.status_changed',
                'order_id' => $orderId,
                'user_id' => $userId,
                'payload' => [
                    'order_id' => $orderId,
                    'status' => $status,
                ],
            ]);

            ProcessedEvent::create([
                'consumer_name' => 'customer',
                'event_id' => $eventId,
                'processed_at' => now(),
            ]);
        });
    }
}
