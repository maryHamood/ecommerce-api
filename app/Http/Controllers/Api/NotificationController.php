<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Notification::where('user_id', auth('api')->id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'message', 'created_at'])
            ->map(fn (Notification $notification) => [
                'id' => $notification->id,
                'message' => $notification->message,
                'createdAt' => $notification->created_at->utc()->toISOString(),
            ]);

        return response()->json($notifications);
    }
}
