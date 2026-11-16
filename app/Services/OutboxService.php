<?php

namespace App\Services;

use App\Models\OutboxMessage;
use Illuminate\Support\Str;

class OutboxService
{
    public function record(
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        array $payload
    ): OutboxMessage {
        return OutboxMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
        ]);
    }
}
