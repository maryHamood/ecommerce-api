<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Services\InventoryService;
use App\Services\Notifications\NotificationSender;
use App\Services\OutboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private NotificationSender $notificationSender,
        private OutboxService $outboxService,
    ) {
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user->email) {
            return ApiResponse::error(
                'You must add an email address before placing an order.',
                null,
                403
            );
        }

        if (!$user->email_verified_at) {
            return ApiResponse::error(
                'You must verify your email address before placing an order.',
                null,
                403
            );
        }

        $total = 0;
        $orderItems = [];

        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);

            $total += $product->price * $item['quantity'];

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'price' => $product->price,
            ];
        }

        // Create the order first to obtain its ID for inventory reservation.
        $order = DB::transaction(function () use ($user, $total) {
            return $user->orders()->create([
                'total' => $total,
                'status' => 'pending',
            ]);
        });

        try {
            $isReserved = $this->inventoryService->reserveStock(
                $order->id,
                $request->items
            );

            if (!$isReserved) {
                $order->delete();

                return ApiResponse::error(
                    'Insufficient stock for one or more items.',
                    null,
                    400
                );
            }
        } catch (Throwable $e) {
            Log::error('Inventory reservation failed.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->delete();

            return ApiResponse::error(
                'Inventory service is currently unavailable. Please try again later.',
                null,
                503
            );
        }

        try {
            DB::transaction(function () use ($order, $orderItems, $user) {
                $order->items()->createMany($orderItems);

                $order->update([
                    'inventory_reserved_at' => now(),
                ]);

                // Store the event in the same transaction as the order items.
                $this->outboxService->record(
                    'OrderCreated',
                    'Order',
                    (int) $order->id,
                    [
                        'order_id' => (int) $order->id,
                        'user_id' => (int) $user->id,
                    ]
                );
            });

            return ApiResponse::success(
                $order->load('items'),
                'Order created successfully.',
                201
            );

        } catch (Throwable $e) {
            Log::error('Order persistence failed after inventory reservation.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            try {
                $this->inventoryService->releaseStock($order->id);
                $order->delete();
            } catch (Throwable $releaseError) {
                Log::critical('Inventory compensation failed.', [
                    'order_id' => $order->id,
                    'error' => $releaseError->getMessage(),
                ]);
            }

            return ApiResponse::error(
                'Failed to create order. Please try again later.',
                null,
                500
            );
        }
    }

    public function myOrders(): JsonResponse
    {
        $orders = auth('api')->user()
            ->orders()
            ->with('items.product')
            ->latest()
            ->get();

        return ApiResponse::success(
            $orders,
            'My orders retrieved successfully.'
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success(
            $order->load('items.product'),
            'Order details retrieved.'
        );
    }

    public function index(): JsonResponse
    {
        $orders = Order::with([
            'user:id,name,phone',
            'items.product',
        ])->latest()->paginate(15);

        return ApiResponse::success(
            $orders,
            'All orders retrieved successfully (Admin).'
        );
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order
    ): JsonResponse {
        $newStatus = $request->validated('status');

        if ($order->status === $newStatus) {
            return ApiResponse::success(
                $order,
                'Order status is already set to ' . $newStatus . '.'
            );
        }

        try {
            DB::transaction(function () use ($order, $newStatus) {
                $order->update([
                    'status' => $newStatus,
                ]);

                // Preserve the existing database notification.
                $this->notificationSender->send(
                    (int) $order->user_id,
                    'Your order #' . $order->id . ' is now ' . $newStatus . '.'
                );

                // Record the status event atomically with the status update.
                $this->outboxService->record(
                    'OrderStatusChanged',
                    'Order',
                    (int) $order->id,
                    [
                        'order_id' => (int) $order->id,
                        'user_id' => (int) $order->user_id,
                        'status' => $newStatus,
                    ]
                );
            });

        } catch (Throwable $e) {
            Log::error('Order status update failed.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Server Error', null, 500);
        }

        $order->refresh();

        return ApiResponse::success(
            $order,
            'Order status updated successfully.'
        );
    }
}
