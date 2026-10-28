<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $total = 0;
                $orderItems = [];


                foreach ($request->items as $item) {
                    $product = Product::findOrFail($item['product_id']);


                    if ($product->amount < $item['quantity']) {
                        return ApiResponse::error("Product {$product->name} is out of stock.", null, 400);
                    }

                    $total += $product->price * $item['quantity'];
                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                    ];
                }

                $order = auth('api')->user()->orders()->create([
                    'total' => $total,
                    'status' => 'pending',
                ]);


                $order->items()->createMany($orderItems);

                return ApiResponse::success($order->load('items'), 'Order created successfully.', 201);
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create order: ' . $e->getMessage(), null, 500);
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
