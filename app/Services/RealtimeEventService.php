<?php

namespace App\Services;

use App\Models\RealtimeEvent;
use Illuminate\Support\Str;

class RealtimeEventService
{
    public function record(
        string $eventType,
        int $orderId,
        ?int $userId,
        array $payload
    ): RealtimeEvent {
        return RealtimeEvent::create([
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'order_id' => $orderId,
            'user_id' => $userId,
            'payload' => $payload,
        ]);
    }
}
