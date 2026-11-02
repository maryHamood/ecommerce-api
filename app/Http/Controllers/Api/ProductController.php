<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Product::query();
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        $products = $query->paginate(10);
        return ApiResponse::success($products, 'Products retrieved successfully.');
    }

    public function show(Product $product): JsonResponse
    {

        $currentStock = $this->inventoryService->checkStock($product->id);


        $productData = $product->toArray();


        $productData['amount'] = $currentStock;

        return ApiResponse::success($productData, 'Product details retrieved successfully.');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $initialStock = $data['initial_stock'];
        unset($data['initial_stock']);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('products', 'public');
            $data['photo'] = $path;
        }


        $product = Product::create($data);


        $this->inventoryService->initInventory($product->id, $initialStock);

        return ApiResponse::success($product, 'Product created successfully.', 201);
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
        return ApiResponse::success($product, 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {

        if ($product->getRawOriginal('photo')) {
            Storage::disk('public')->delete($product->getRawOriginal('photo'));
        }


        $this->inventoryService->deleteInventory($product->id);


        $product->delete();

        return ApiResponse::success(null, 'Product and its inventory deleted successfully.');
    }



}
