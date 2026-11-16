<?php

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use App\Services\RabbitMQ\ConfirmedPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishOutboxMessages extends Command
{
    protected $signature = 'outbox:publish {--limit=100}';

    protected $description = 'Publish pending Outbox messages to RabbitMQ';

    public function handle(ConfirmedPublisher $publisher): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));

        $published = 0;
        $failed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $messageId = null;

            try {
                $result = DB::transaction(
                    function () use ($publisher, &$messageId) {
                        $message = OutboxMessage::where('status', 'pending')
                            ->where(function ($query) {
                                $query->whereNull('available_at')
                                    ->orWhere('available_at', '<=', now());
                            })
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->first();

                        if (!$message) {
                            return 'empty';
                        }

                        $messageId = $message->id;

                        $routingKey = match ($message->event_type) {
                            'OrderCreated' => 'order.created',
                            'OrderStatusChanged' => 'order.status_changed',
                            default => throw new \RuntimeException(
                                'Unsupported event type: ' . $message->event_type
                            ),
                        };

                        $publisher->publish(
                            'ecommerce.events',
                            $routingKey,
                            json_encode([
                                'event_id' => $message->event_id,
                                'event_type' => $message->event_type,
                                'aggregate_type' => $message->aggregate_type,
                                'aggregate_id' => $message->aggregate_id,
                                'payload' => $message->payload,
                            ], JSON_THROW_ON_ERROR)
                        );

                        $message->update([
                            'status' => 'published',
                            'published_at' => now(),
                            'last_error' => null,
                        ]);

                        return 'published';
                    },
                    1
                );

                if ($result === 'empty') {
                    break;
                }

                $published++;
            } catch (Throwable $e) {
                $failed++;

                $this->error(
                    'Outbox publishing failed: ' . $e->getMessage()
                );

                Log::error('Outbox publishing failed.', [
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);

                // Record the failure outside the failed transaction.
                try {
                    if ($messageId !== null) {
                        $failedMessage = OutboxMessage::whereKey($messageId)
                            ->where('status', 'pending')
                            ->first();

                        if ($failedMessage) {
                            $nextAttempt = $failedMessage->attempts + 1;

                            // Exponential backoff: 5, 10, 20, 40...
                            // Maximum delay: 300 seconds.
                            $delay = min(
                                300,
                                5 * (2 ** min($nextAttempt - 1, 6))
                            );

                            $failedMessage->update([
                                'attempts' => $nextAttempt,
                                'last_error' => substr(
                                    $e->getMessage(),
                                    0,
                                    60000
                                ),
                                'available_at' => now()->addSeconds($delay),
                            ]);

                            Log::warning(
                                'Outbox message scheduled for retry.',
                                [
                                    'message_id' => $messageId,
                                    'attempts' => $nextAttempt,
                                    'retry_after_seconds' => $delay,
                                ]
                            );
                        }
                    }
                } catch (Throwable $recordError) {
                    Log::critical(
                        'Failed to record Outbox publishing failure.',
                        [
                            'message_id' => $messageId,
                            'error' => $recordError->getMessage(),
                        ]
                    );
                }

                // Stop this run; the next scheduled run will retry.
                break;
            }
        }

        $this->info("Published: {$published}, Failed: {$failed}");

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
