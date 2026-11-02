<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $total = 0;
        $orderItems = [];
        foreach ($request->items as $item) {
            $product = \App\Models\Product::findOrFail($item['product_id']);
            $total += $product->price * $item['quantity'];
            $orderItems[] = [
                'product_id' => $product->id,
                'quantity'   => $item['quantity'],
                'price'      => $product->price,
            ];
        }

        $order = auth('api')->user()->orders()->create([
            'total'  => $total,
            'status' => 'pending',
        ]);

        try {
            $isReserved = $this->inventoryService->reserveStock($order->id, $request->items);

            if (!$isReserved) {
                $order->delete();
                return ApiResponse::error('Insufficient stock for one or more items.', null, 400);
            }
        } catch (\Throwable $e) {
            $order->delete();
            return ApiResponse::error('Inventory service is currently unavailable. Please try again later.', null, 503);
        }

        try {
            $order->items()->createMany($orderItems);
            $order->update(['status' => 'confirmed']);
            return ApiResponse::success($order->load('items'), 'Order created and confirmed successfully.', 201);
        } catch (Exception $e) {
            $this->inventoryService->releaseStock($order->id);
            $order->delete();
            return ApiResponse::error('Failed to create order, stock has been released.', null, 500);
        }
    }

    public function myOrders(): JsonResponse
    {
        $orders = auth('api')->user()->orders()->with('items.product')->latest()->get();
        return ApiResponse::success($orders, 'My orders retrieved successfully.');
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        return ApiResponse::success($order->load('items.product'), 'Order details retrieved.');
    }

    public function index(): JsonResponse
    {
        $orders = Order::with(['user:id,name,phone', 'items.product'])->latest()->paginate(15);
        return ApiResponse::success($orders, 'All orders retrieved successfully (Admin).');
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order->update(['status' => $request->validated('status')]);
        return ApiResponse::success($order, 'Order status updated successfully.');
    }
}
