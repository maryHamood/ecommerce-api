<?php

namespace App\Http\Controllers\Api;

use App\Models\RealtimeEvent;
use Illuminate\Http\Request;

class RealtimeStreamController
{
    public function admin(Request $request)
    {
        return $this->stream($request, 'admin');
    }

    public function customer(Request $request)
    {
        return $this->stream($request, 'customer');
    }

    private function stream(Request $request, string $audience)
    {
        $cursor = $request->header('Last-Event-ID')
            ?? $request->query('after', 0);

        if (! is_numeric($cursor) || (int) $cursor < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid event cursor.',
            ], 422);
        }

        $lastId = (int) $cursor;
        $userId = (int) $request->user('api')->id;

        return response()->stream(function () use ($audience, $lastId, $userId) {
            set_time_limit(0);

            $cursor = $lastId;
            $startedAt = time();
            $lastHeartbeat = time();

            while (! connection_aborted() && (time() - $startedAt) < 55) {
                // Read events in global ID order.
                // Advance the cursor even for events this audience cannot see.
                $events = RealtimeEvent::query()
                    ->where('id', '>', $cursor)
                    ->orderBy('id')
                    ->limit(100)
                    ->get();

                foreach ($events as $event) {
                    if (connection_aborted()) {
                        break 2;
                    }

                    // Move past every scanned event to avoid rescanning it.
                    $cursor = (int) $event->id;

                    // Admin receives order creation events only.
                    if ($audience === 'admin') {
                        $shouldSend = $event->event_type === 'order.created';
                    } else {
                        // Customer receives status changes for their own orders only.
                        $shouldSend =
                            $event->event_type === 'order.status_changed'
                            && (int) $event->user_id === $userId;
                    }

                    if (! $shouldSend) {
                        continue;
                    }

                    $data = [
                        'event_id' => $event->event_id,
                        'order_id' => $event->order_id,
                        'type' => $event->event_type,
                        'payload' => $event->payload,
                        'created_at' => $event->created_at?->toISOString(),
                    ];

                    echo "id: {$event->id}\n";
                    echo "event: {$event->event_type}\n";
                    echo 'data: ' . json_encode(
                            $data,
                            JSON_UNESCAPED_UNICODE
                        ) . "\n\n";
                }

                if (ob_get_level() > 0) {
                    @ob_flush();
                }

                flush();

                if (time() - $lastHeartbeat >= 15) {
                    echo ": heartbeat\n\n";

                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }

                    flush();
                    $lastHeartbeat = time();
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
