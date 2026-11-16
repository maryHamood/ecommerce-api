<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\RealtimeStreamController;
use App\Http\Controllers\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1');

        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
            ->middleware('throttle:5,1');

        Route::post('/resend-otp', [AuthController::class, 'resendOtp'])
            ->middleware('throttle:3,1');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login');
    });

    // Authenticated routes
    Route::middleware(['auth:api'])->group(function () {

        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/categories', [CategoryController::class, 'index']);

        Route::put('/auth/email', [EmailVerificationController::class, 'updateEmail']);

        Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'sendVerificationLink']);
    });

    // Public products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Customer routes
    Route::middleware(['auth:api', 'role:customer'])->group(function () {

        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/my-orders', [OrderController::class, 'myOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);

        // Customer real-time stream
        Route::get('/customer/events/stream', [
            RealtimeStreamController::class,
            'customer'
        ]);
    });

    // Admin routes
    Route::middleware(['auth:api', 'role:admin'])
        ->prefix('admin')
        ->group(function () {

            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);

            Route::get('/orders', [OrderController::class, 'index']);
            Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
            Route::post('/categories', [CategoryController::class, 'store']);

            // Admin real-time stream
            Route::get('/events/stream', [
                RealtimeStreamController::class,
                'admin'
            ]);
        });

    // Email verification
    Route::get('/auth/email/verify/{user}/{hash}', [
        EmailVerificationController::class,
        'verify'
    ])
        ->middleware('signed')
        ->name('verification.verify');

});
