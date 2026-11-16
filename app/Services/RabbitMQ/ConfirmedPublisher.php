<?php

namespace App\Services\RabbitMQ;

use Illuminate\Support\Facades\Config;
use RuntimeException;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connection\ConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;

class ConfirmedPublisher
{
    public function publish(
        string $exchange,
        string $routingKey,
        string $message
    ): void {
        $config = Config::get('queue.connections.rabbitmq');

        $connection = ConnectionFactory::make($config);
        $channel = null;

        try {
            $channel = $connection->channel();
            $channel->confirm_select();

            $acked = false;
            $nacked = false;
            $returned = false;

            $channel->set_ack_handler(
                function () use (&$acked): void {
                    $acked = true;
                }
            );

            $channel->set_nack_handler(
                function () use (&$nacked): void {
                    $nacked = true;
                }
            );

            $channel->set_return_listener(
                function () use (&$returned): void {
                    $returned = true;
                }
            );

            $channel->basic_publish(
                new AMQPMessage($message, [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]),
                $exchange,
                $routingKey,
                true
            );

            $channel->wait_for_pending_acks_returns(5);

            if ($nacked) {
                throw new RuntimeException(
                    'RabbitMQ negatively acknowledged the message.'
                );
            }

            if ($returned) {
                throw new RuntimeException(
                    'RabbitMQ returned the message because it could not be routed.'
                );
            }

            if (!$acked) {
                throw new RuntimeException(
                    'RabbitMQ did not confirm the published message.'
                );
            }
        } finally {
            try {
                $channel?->close();
            } catch (Throwable) {
                // Preserve the original exception.
            }

            try {
                $connection->close();
            } catch (Throwable) {
                // Preserve the original exception.
            }
        }
    }
}
