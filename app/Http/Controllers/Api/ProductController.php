<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Product;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

//    public function index(Request $request): JsonResponse
//    {
//        if ($request->has('debug')) {
//            \DB::enableQueryLog();
//        }
//
//        if ($request->has('without_with')) {
//            $query = Product::select('id', 'name', 'price', 'photo', 'category_id', 'created_at');
//        } else {
//            $query = Product::with('category')->select('id', 'name', 'price', 'photo', 'category_id', 'created_at');
//        }
//
//        if ($request->filled('category_id')) {
//            $query->where('category_id', $request->input('category_id'));
//        }
//
//        $products = $query->paginate(10);
//
//        if ($request->has('without_with')) {
//            foreach ($products as $product) {
//                $product->category_name = \App\Models\Category::find($product->category_id)->name;
//            }
//        }
//
//        if ($request->has('debug')) {
//            $queryCount = count(\DB::getQueryLog());
//            return ApiResponse::success([
//                'queries_count' => $queryCount,
//                'products' => $products->items(),
//            ], 'Debug mode');
//        }
//
//        return ApiResponse::success($products, 'Products retrieved successfully.');
//    }

    public function index(Request $request): JsonResponse
    {
        $categoryId = $request->input('category_id', 'all');
        $page = $request->input('page', 1);
        $cacheKey = 'products_category_' . $categoryId . '_page_' . $page;

        $products = Cache::remember($cacheKey, 60, function () use ($request) {
            $query = Product::with('category')
                ->select('id', 'name', 'price', 'photo', 'category_id', 'created_at');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }

            return $query->paginate(10)->toArray();
        });

        return ApiResponse::success($products, 'Products retrieved successfully.');
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $currentStock = $this->inventoryService->checkStock($product->id);
        $productData = $product->toArray();
        $productData['amount'] = $currentStock;

        // ETag
        $etag = md5(json_encode($productData));

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        return ApiResponse::success($productData, 'Product details retrieved successfully.')
            ->header('ETag', $etag);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $total = 0;
        $orderItems = [];
        foreach ($request->items as $item) {
            $product = \App\Models\Product::findOrFail($item['product_id']);
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

        try {
            $isReserved = $this->inventoryService->reserveStock($order->id, $request->items);

            if (!$isReserved) {
                $order->delete();
                return ApiResponse::error('Insufficient stock for one or more items.', null, 400);
            }
        } catch (\Throwable $e) {

            $this->inventoryService->releaseStock($order->id);
            $order->delete();
            return ApiResponse::error('Inventory service is currently unavailable. Please try again later.', null, 503);
        }

        try {
            $order->items()->createMany($orderItems);
            return ApiResponse::success($order->load('items'), 'Order created successfully.', 201);
        } catch (Exception $e) {
            $this->inventoryService->releaseStock($order->id);
            $order->delete();
            return ApiResponse::error('Failed to create order, stock has been released.', null, 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['initial_stock'])) unset($data['initial_stock']);

        if ($request->hasFile('photo')) {
            if ($product->getRawOriginal('photo')) {
                Storage::disk('public')->delete($product->getRawOriginal('photo'));
            }
            $path = $request->file('photo')->store('products', 'public');
            $data['photo'] = $path;
        }

        $product->update($data);

        Cache::flush();

        return ApiResponse::success($product, 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->getRawOriginal('photo')) {
            Storage::disk('public')->delete($product->getRawOriginal('photo'));
        }

        $this->inventoryService->deleteInventory($product->id);
        $product->delete();

        Cache::flush();

        return ApiResponse::success(null, 'Product and its inventory deleted successfully.');
    }
}
