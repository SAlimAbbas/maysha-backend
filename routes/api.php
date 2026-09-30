<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\BannerController;

/*
|--------------------------------------------------------------------------
| Maysha REST API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Health / Store Info
    Route::get('/store-info', function () {
        return response()->json([
            'brand' => 'Maysha Skincare',
            'tagline' => 'Clean Beauty for Real Skin',
            'currency' => 'INR',
            'symbol' => '₹',
            'free_shipping_threshold' => 999,
            'status' => 'operational'
        ]);
    });

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/bestsellers', [ProductController::class, 'bestsellers']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // Categories & Concerns
    Route::get('/categories', [CategoryController::class, 'categories']);
    Route::get('/concerns', [CategoryController::class, 'concerns']);

    // Cart Management
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);

    // Orders & Checkout
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{reference}', [OrderController::class, 'show']);

    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Promotional Banners & Hero Carousel (Max 7 dynamic banners)
    Route::get('/banners', [BannerController::class, 'index']);
    Route::post('/banners', [BannerController::class, 'store']);
    Route::put('/banners/{id}', [BannerController::class, 'update']);
    Route::delete('/banners/{id}', [BannerController::class, 'destroy']);
    Route::post('/banners/reorder', [BannerController::class, 'reorder']);
});
