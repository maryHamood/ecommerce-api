<?php

namespace App\Services\Notifications;

use App\Models\Notification;

class DatabaseNotificationSender implements NotificationSender
{
    public function send(int $userId, string $message): void
    {
        Notification::create([
            'user_id' => $userId,
            'title' => 'Order status updated',
            'body' => $message,
            'message' => $message,
        ]);
    }
}
