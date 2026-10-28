<?php

namespace App\Services\Notifications;

interface NotificationSender
{
    public function send(int $userId, string $message): void;
}
