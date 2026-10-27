<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::paginate(10);
        return ApiResponse::success($products, 'Products retrieved successfully.');
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success($product, 'Product details retrieved.');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {

        $data = $request->validated();


        if ($request->hasFile('photo')) {

            $path = $request->file('photo')->store('products', 'public');

            $data['photo'] = $path;
        }


        $product = Product::create($data);

        return ApiResponse::success($product, 'Product created successfully.', 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();


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


        $product->delete();
        return ApiResponse::success(null, 'Product deleted successfully.');
    }
}
