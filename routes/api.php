<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {


    Route::prefix('auth')->group(function () {
        Route::post('/register',   [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
        Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:3,1');
        Route::post('/login',      [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
    });

    Route::middleware(['auth:api', 'jwt.refresh'])->group(function () {
        // المصادقة الأساسية
        Route::get('/auth/me',      [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);


        Route::get('/products',      [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
    });

    Route::middleware(['auth:api', 'role:customer'])->group(function () {
        // إدارة الطلبات
        Route::post('/orders',       [OrderController::class, 'store']);
        Route::get('/my-orders',     [OrderController::class, 'myOrders']);
        Route::get('/orders/{id}',   [OrderController::class, 'show']);
    });


    Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {


        Route::post('/products',         [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);


        Route::get('/orders',                 [OrderController::class, 'index']);
        Route::patch('/orders/{id}/status',   [OrderController::class, 'updateStatus']);
    });
});
