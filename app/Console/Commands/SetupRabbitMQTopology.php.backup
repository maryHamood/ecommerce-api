<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connection\ConnectionFactory;

class SetupRabbitMQTopology extends Command
{
    protected $signature = 'rabbitmq:setup';

    protected $description = 'Create RabbitMQ exchanges, queues, retry queues and DLQs';

    public function handle(): int
    {
        $connection = null;
        $channel = null;

        try {
            $config = Config::get('queue.connections.rabbitmq');

            $connection = ConnectionFactory::make($config);
            $channel = $connection->channel();

            $channel->exchange_declare(
                'ecommerce.events',
                'topic',
                false,
                true,
                false
            );

            $channel->exchange_declare(
                'ecommerce.events.dlx',
                'direct',
                false,
                true,
                false
            );

            $channel->exchange_declare(
                'ecommerce.events.retry',
                'direct',
                false,
                true,
                false
            );

            $queues = [
                [
                    'name' => 'ecommerce.order.created.email',
                    'routing_key' => 'order.created',
                    'retry' => 'ecommerce.order.created.email.retry',
                    'retry_key' => 'order.created.email.retry',
                    'dlq' => 'ecommerce.order.created.email.dlq',
                    'dlq_key' => 'order.created.email.failed',
                ],
                [
                    'name' => 'ecommerce.order.created.admin',
                    'routing_key' => 'order.created',
                    'retry' => 'ecommerce.order.created.admin.retry',
                    'retry_key' => 'order.created.admin.retry',
                    'dlq' => 'ecommerce.order.created.admin.dlq',
                    'dlq_key' => 'order.created.admin.failed',
                ],
                [
                    'name' => 'ecommerce.order.status_changed.customer',
                    'routing_key' => 'order.status_changed',
                    'retry' => 'ecommerce.order.status_changed.customer.retry',
                    'retry_key' => 'order.status_changed.customer.retry',
                    'dlq' => 'ecommerce.order.status_changed.customer.dlq',
                    'dlq_key' => 'order.status_changed.customer.failed',
                ],
            ];

            foreach ($queues as $queue) {
                // Final dead-letter queue.
                $channel->queue_declare(
                    $queue['dlq'],
                    false,
                    true,
                    false,
                    false
                );

                $channel->queue_bind(
                    $queue['dlq'],
                    'ecommerce.events.dlx',
                    $queue['dlq_key']
                );

                // Main queue: rejected messages go to the DLQ.
                $mainArguments = new AMQPTable([
                    'x-dead-letter-exchange' => 'ecommerce.events.dlx',
                    'x-dead-letter-routing-key' => $queue['dlq_key'],
                ]);

                $channel->queue_declare(
                    $queue['name'],
                    false,
                    true,
                    false,
                    false,
                    false,
                    $mainArguments
                );

                $channel->queue_bind(
                    $queue['name'],
                    'ecommerce.events',
                    $queue['routing_key']
                );

                // Retry queue: wait 10 seconds, then return to main exchange.
                $retryArguments = new AMQPTable([
                    'x-message-ttl' => 10000,
                    'x-dead-letter-exchange' => 'ecommerce.events',
                    'x-dead-letter-routing-key' => $queue['routing_key'],
                ]);

                $channel->queue_declare(
                    $queue['retry'],
                    false,
                    true,
                    false,
                    false,
                    false,
                    $retryArguments
                );

                $channel->queue_bind(
                    $queue['retry'],
                    'ecommerce.events.retry',
                    $queue['retry_key']
                );

                $this->info('Created main queue: ' . $queue['name']);
                $this->info('Created retry queue: ' . $queue['retry']);
                $this->info('Created DLQ: ' . $queue['dlq']);
            }

            $this->info('RabbitMQ topology created successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('RabbitMQ setup failed: ' . $e->getMessage());

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
