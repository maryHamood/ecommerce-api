<?php

namespace App\Console\Commands;

use App\Services\RabbitMQ\ConfirmedPublisher;
use App\Services\RabbitMQ\Handlers\OrderCreatedEmailHandler;
use App\Services\RabbitMQ\Handlers\OrderCreatedAdminHandler;
use App\Services\RabbitMQ\Handlers\OrderStatusChangedCustomerHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connection\ConnectionFactory;

class ConsumeRabbitMQMessages extends Command
{
    protected $signature = 'rabbitmq:consume
        {queue : Queue name}
        {--max-retries=3 : Maximum retry attempts}';

    protected $description = 'Consume RabbitMQ messages';

    private const QUEUES = [
        'ecommerce.order.created.email' => [
            'consumer' => 'email',
            'retry_key' => 'order.created.email.retry',
            'handler' => OrderCreatedEmailHandler::class,
        ],
        'ecommerce.order.created.admin' => [
            'consumer' => 'admin',
            'retry_key' => 'order.created.admin.retry',
            'handler' => OrderCreatedAdminHandler::class,
        ],
        'ecommerce.order.status_changed.customer' => [
            'consumer' => 'customer',
            'retry_key' => 'order.status_changed.customer.retry',
            'handler' => OrderStatusChangedCustomerHandler::class,
        ],
    ];

    public function handle(ConfirmedPublisher $publisher): int
    {
        $queue = (string) $this->argument('queue');

        if (!isset(self::QUEUES[$queue])) {
            $this->error('Unsupported queue: ' . $queue);
            return self::FAILURE;
        }

        $definition = self::QUEUES[$queue];
        $maxRetries = max(0, (int) $this->option('max-retries'));

        $connection = null;
        $channel = null;

        try {
            $config = Config::get('queue.connections.rabbitmq');

            $connection = ConnectionFactory::make($config);
            $channel = $connection->channel();

            $channel->basic_qos(null, 1, null);

            $channel->basic_consume(
                $queue,
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use (
                    $channel,
                    $publisher,
                    $definition,
                    $maxRetries,
                    $queue
                ): void {
                    $tag = $message->getDeliveryTag();

                    try {
                        $envelope = json_decode(
                            $message->getBody(),
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );

                        if (
                            !is_array($envelope) ||
                            empty($envelope['event_id']) ||
                            empty($envelope['event_type']) ||
                            !isset($envelope['payload']) ||
                            !is_array($envelope['payload'])
                        ) {
                            throw new \RuntimeException(
                                'Invalid RabbitMQ event envelope.'
                            );
                        }

                        $eventId = (string) $envelope['event_id'];
                        $eventType = (string) $envelope['event_type'];
                        $payload = $envelope['payload'];

                        $expectedType = match ($definition['consumer']) {
                            'email', 'admin' => 'OrderCreated',
                            'customer' => 'OrderStatusChanged',
                        };

                        if ($eventType !== $expectedType) {
                            throw new \RuntimeException(
                                'Unexpected event type for this queue.'
                            );
                        }

                        $handler = app($definition['handler']);
                        $handler->handle($eventId, $payload);

                        $channel->basic_ack($tag);
                    } catch (Throwable $e) {
                        Log::error('RabbitMQ consumer processing failed.', [
                            'queue' => $queue,
                            'error' => $e->getMessage(),
                        ]);

                        /*
                         * حماية الرسائل التالفة:
                         * إذا لم يكن محتوى الرسالة JSON صالحاً،
                         * نرفضها دون إعادة وضعها في الطابور،
                         * حتى لا تدخل في حلقة إعادة المحاولة إلى ما لا نهاية.
                         */
                        $checkEnvelope = json_decode(
                            $message->getBody(),
                            true
                        );

                        if (!is_array($checkEnvelope)) {
                            $channel->basic_reject($tag, false);

                            Log::error(
                                'Malformed RabbitMQ message moved to DLQ.',
                                ['queue' => $queue]
                            );

                            return;
                        }

                        try {
                            $retryCount = (int) (
                                $checkEnvelope['_retry_count'] ?? 0
                            );

                            if ($retryCount < $maxRetries) {
                                $checkEnvelope['_retry_count'] = $retryCount + 1;

                                $publisher->publish(
                                    'ecommerce.events.retry',
                                    $definition['retry_key'],
                                    json_encode(
                                        $checkEnvelope,
                                        JSON_THROW_ON_ERROR
                                    )
                                );

                                $channel->basic_ack($tag);

                                Log::warning(
                                    'RabbitMQ message sent to retry queue.',
                                    [
                                        'queue' => $queue,
                                        'retry_count' => $retryCount + 1,
                                    ]
                                );
                            } else {
                                $channel->basic_reject($tag, false);

                                Log::error(
                                    'RabbitMQ message moved to DLQ.',
                                    [
                                        'queue' => $queue,
                                        'retry_count' => $retryCount,
                                    ]
                                );
                            }
                        } catch (Throwable $retryError) {
                            Log::critical(
                                'RabbitMQ retry publishing failed.',
                                [
                                    'queue' => $queue,
                                    'error' => $retryError->getMessage(),
                                ]
                            );

                            $channel->basic_nack($tag, false, true);
                            $channel->stop_consuming();
                        }
                    }
                }
            );

            $this->info('Consuming queue: ' . $queue);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Consumer stopped: ' . $e->getMessage());

            Log::error('RabbitMQ consumer stopped.', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } finally {
            try {
                $channel?->close();
            } catch (Throwable) {
            }

            try {
                $connection?->close();
            } catch (Throwable) {
            }
        }
    }
}
