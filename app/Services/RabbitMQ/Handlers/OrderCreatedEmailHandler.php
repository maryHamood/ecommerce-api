<?php

namespace App\Services\RabbitMQ\Handlers;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\ProcessedEvent;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OrderCreatedEmailHandler
{
    public function handle(string $eventId, array $payload): void
    {
        $alreadyProcessed = ProcessedEvent::where(
            'consumer_name',
            'email'
        )->where('event_id', $eventId)->exists();

        if ($alreadyProcessed) {
            return;
        }

        $orderId = $payload['order_id'] ?? null;

        if (!$orderId) {
            throw new RuntimeException('Order ID is missing from the event payload.');
        }

        $order = Order::with([
            'user',
            'items.product',
        ])->find($orderId);

        if (!$order) {
            throw new RuntimeException("Order {$orderId} was not found.");
        }

        if (!$order->user || !$order->user->email) {
            throw new RuntimeException("Order {$orderId} has no customer email.");
        }

        Mail::to($order->user->email)
            ->send(new OrderConfirmationMail($order));

        ProcessedEvent::create([
            'consumer_name' => 'email',
            'event_id' => $eventId,
            'processed_at' => now(),
        ]);
    }
}
