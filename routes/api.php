<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminProductController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\StoreInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Maysha REST API Routes (Version 1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Store Info & Health
    Route::get('/store-info', [StoreInfoController::class, 'show'])->middleware('throttle:api');

    // ----------------------------------------------------------------------
    // Authentication & Account
    // ----------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-forgot');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-forgot');

        // Email Verification
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

        // Google Socialite OAuth
        Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle'])->middleware('throttle:auth-google');
        Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback'])->middleware('throttle:auth-google');

        // Authenticated customer routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::patch('/me', [AuthController::class, 'updateProfile']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:auth-verify-resend');
        });
    });

    // ----------------------------------------------------------------------
    // Public Catalog & Storefront (Throttled)
    // ----------------------------------------------------------------------
    Route::middleware('throttle:catalog')->group(function () {
        // Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/bestsellers', [ProductController::class, 'bestsellers']);
        Route::get('/products/new', [ProductController::class, 'newLaunches']);
        Route::get('/products/{slug}', [ProductController::class, 'show']);

        // Search Suggestions
        Route::get('/search/suggest', [ProductController::class, 'suggest'])->middleware('throttle:search');

        // Categories & Concerns
        Route::get('/categories', [CategoryController::class, 'categories']);
        Route::get('/concerns', [CategoryController::class, 'concerns']);

        // Banners (Storefront Read)
        Route::get('/banners', [BannerController::class, 'index']);

        // Reviews (Public Read)
        Route::get('/reviews', [ReviewController::class, 'index']);
    });

    // ----------------------------------------------------------------------
    // Cart & Checkout
    // ----------------------------------------------------------------------
    Route::middleware('throttle:cart')->group(function () {
        Route::get('/cart', [CartController::class, 'getCart']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
        Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:coupon');
        Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->middleware('throttle:coupon');
    });

    // Orders & Checkout Quote
    Route::post('/checkout/quote', [OrderController::class, 'quote'])->middleware('throttle:checkout');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:checkout');
    Route::get('/orders/{reference}', [OrderController::class, 'show']);

    // Payments (Razorpay)
    Route::prefix('payments/razorpay')->group(function () {
        Route::post('/create-order', [PaymentController::class, 'createRazorpayOrder'])->middleware('throttle:checkout');
        Route::post('/verify', [PaymentController::class, 'verifyPayment'])->middleware('throttle:payment-verify');
        Route::post('/webhook', [PaymentController::class, 'handleWebhook']); // Signature verified, no IP throttle
    });

    // Newsletter & Contact
    Route::post('/newsletter', [ContactController::class, 'subscribe'])->middleware('throttle:newsletter');
    Route::post('/contact', [ContactController::class, 'contact'])->middleware('throttle:contact');

    // Authenticated Reviews Write
    Route::middleware(['auth:sanctum', 'throttle:reviews'])->group(function () {
        Route::post('/reviews', [ReviewController::class, 'store']);
    });

    // ----------------------------------------------------------------------
    // Admin Protected Endpoints (/api/v1/admin/*)
    // ----------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'admin', 'throttle:admin'])->prefix('admin')->group(function () {
        // Dashboard & Analytics
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/customers', [AdminDashboardController::class, 'customers']);

        // Products CRUD & Stock Management
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

        // Orders Management & Refunds
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::match(['put', 'patch'], '/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/refund', [AdminOrderController::class, 'refund']);

        // Reviews Moderation
        Route::get('/reviews', [AdminReviewController::class, 'index']);
        Route::match(['put', 'patch'], '/reviews/{id}/approve', [AdminReviewController::class, 'approve']);
        Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);

        // Media Management (Cloudflare direct upload)
        Route::get('/media', [MediaController::class, 'index']);
        Route::post('/media/upload-url', [MediaController::class, 'requestUploadUrl'])->middleware('throttle:media-sign');
        Route::post('/media/{id}/confirm', [MediaController::class, 'confirm']);

        // Banner management (moved behind admin auth)
        Route::post('/banners', [BannerController::class, 'store']);
        Route::put('/banners/{id}', [BannerController::class, 'update']);
        Route::delete('/banners/{id}', [BannerController::class, 'destroy']);
        Route::post('/banners/reorder', [BannerController::class, 'reorder']);
    });
});
