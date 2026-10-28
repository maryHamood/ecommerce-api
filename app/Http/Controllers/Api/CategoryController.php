<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function index(): JsonResponse
    {
        $categories = Category::all();
        return ApiResponse::success($categories, 'Categories retrieved successfully.');
    }


    public function store(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ], [
            'name.required' => 'Category name is required.',
            'name.unique' => 'This category already exists.'
        ]);

        $category = Category::create($validated);

        return ApiResponse::success($category, 'Category created successfully.', 201);
    }
}