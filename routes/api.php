<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\AdminArticleController;
use App\Http\Controllers\Api\V1\Admin\AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminProductController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\ShippingController;
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

    // Homepage Aggregate & Sitemap Slugs
    Route::get('/home', [HomeController::class, 'index'])->middleware('throttle:catalog');
    Route::get('/sitemap/slugs', [HomeController::class, 'slugs'])->middleware('throttle:api');

    // Shipping Pincode Verification
    Route::get('/shipping/pincode/{pin}', [ShippingController::class, 'checkPincode'])->middleware('throttle:api');

    // ----------------------------------------------------------------------
    // Authentication & Account
    // ----------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-forgot');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-forgot');

        // Email Verification
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        // Google Socialite OAuth & Secure Code Exchange
        Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle'])->middleware('throttle:auth-google');
        Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback'])->middleware('throttle:auth-google');
        Route::post('/exchange-code', [AuthController::class, 'exchangeCode'])->middleware('throttle:auth-login');

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
    // Authenticated Customer Account (Addresses, Wishlist, Orders)
    // ----------------------------------------------------------------------
    Route::middleware('auth:sanctum')->prefix('account')->group(function () {
        Route::get('/addresses', [AccountController::class, 'getAddresses']);
        Route::post('/addresses', [AccountController::class, 'storeAddress']);
        Route::put('/addresses/{id}', [AccountController::class, 'updateAddress']);
        Route::delete('/addresses/{id}', [AccountController::class, 'destroyAddress']);

        Route::get('/wishlist', [AccountController::class, 'getWishlist']);
        Route::post('/wishlist/sync', [AccountController::class, 'syncWishlist']);
        Route::post('/wishlist/{productId}', [AccountController::class, 'addToWishlist']);
        Route::delete('/wishlist/{productId}', [AccountController::class, 'removeFromWishlist']);

        Route::get('/orders', [AccountController::class, 'getOrders']);
        Route::get('/orders/{reference}', [AccountController::class, 'getOrderDetail']);
        Route::post('/orders/{reference}/returns', [AccountController::class, 'requestReturn']);
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
        Route::post('/reviews/{id}/helpful', [ReviewController::class, 'voteHelpful']);

        // Articles & Journal (Public Read)
        Route::get('/articles', [ArticleController::class, 'index']);
        Route::get('/articles/{slug}', [ArticleController::class, 'show']);

        // Coupons & Active Offers
        Route::get('/coupons', [CouponController::class, 'index']);
    });

    // ----------------------------------------------------------------------
    // Cart & Checkout (Optional Sanctum Auth for Logged-In / Guest)
    // ----------------------------------------------------------------------
    Route::middleware(['optional.sanctum', 'throttle:cart'])->group(function () {
        Route::get('/cart', [CartController::class, 'getCart']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
        Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:coupon');
        Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->middleware('throttle:coupon');
        Route::delete('/cart', [CartController::class, 'clearCart']);
        Route::post('/cart/clear', [CartController::class, 'clearCart']);
    });

    // Orders & Checkout Quote (Optional Sanctum Auth for Logged-In / Guest)
    Route::middleware('optional.sanctum')->group(function () {
        Route::post('/checkout/quote', [OrderController::class, 'quote'])->middleware('throttle:checkout');
        Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:checkout');
        Route::get('/orders/{reference}', [OrderController::class, 'show']);
        Route::get('/orders/{reference}/invoice', [OrderController::class, 'invoice']);
        Route::get('/orders/{reference}/tracking', [OrderController::class, 'tracking']);
    });

    // Payments (Razorpay)
    Route::prefix('payments/razorpay')->group(function () {
        Route::post('/create-order', [PaymentController::class, 'createRazorpayOrder'])->middleware('throttle:checkout');
        Route::post('/verify', [PaymentController::class, 'verifyPayment'])->middleware('throttle:payment-verify');
        Route::post('/webhook', [PaymentController::class, 'handleWebhook']); // Signature verified, no IP throttle
    });

    // Neutral Logistics Webhook (Shiprocket rejects URLs with shiprocket, kartrocket, sr, kr)
    Route::post('/logistics/hook', [\App\Http\Controllers\Api\V1\ShippingWebhookController::class, 'handle'])
        ->middleware('throttle:60,1');

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

        // Articles Management
        Route::get('/articles', [AdminArticleController::class, 'index']);
        Route::post('/articles', [AdminArticleController::class, 'store']);
        Route::put('/articles/{id}', [AdminArticleController::class, 'update']);
        Route::delete('/articles/{id}', [AdminArticleController::class, 'destroy']);

        // Coupons Management
        Route::apiResource('coupons', AdminCouponController::class);

        // Categories Management
        Route::apiResource('categories', \App\Http\Controllers\Api\V1\Admin\AdminCategoryController::class);

        // Settings & Newsletter Broadcast
        Route::get('/settings', [\App\Http\Controllers\Api\V1\Admin\AdminSettingController::class, 'index']);
        Route::post('/settings', [\App\Http\Controllers\Api\V1\Admin\AdminSettingController::class, 'update']);
        Route::get('/subscribers', [\App\Http\Controllers\Api\V1\Admin\AdminSettingController::class, 'subscribers']);
        Route::post('/newsletter/broadcast', [\App\Http\Controllers\Api\V1\Admin\AdminSettingController::class, 'broadcast']);

        // Two-Factor Authentication (TOTP)
        Route::get('/2fa/status', [\App\Http\Controllers\Api\V1\Admin\Admin2FAController::class, 'status']);
        Route::post('/2fa/setup', [\App\Http\Controllers\Api\V1\Admin\Admin2FAController::class, 'setup']);
        Route::post('/2fa/confirm', [\App\Http\Controllers\Api\V1\Admin\Admin2FAController::class, 'confirm']);
        Route::post('/2fa/disable', [\App\Http\Controllers\Api\V1\Admin\Admin2FAController::class, 'disable']);

        // Shiprocket Logistics & Shipments Management
        Route::get('/shipments', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'index']);
        Route::get('/orders/{id}/shipment', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'showByOrder']);
        Route::post('/orders/{id}/shipment', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'createForOrder']);
        Route::get('/shipments/{id}/couriers', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'getCouriers']);
        Route::post('/shipments/{id}/assign', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'assign']);
        Route::post('/shipments/{id}/pickup', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'pickup']);
        Route::post('/shipments/{id}/label', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'label']);
        Route::post('/shipments/{id}/manifest', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'manifest']);
        Route::post('/shipments/{id}/invoice', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'invoice']);
        Route::post('/shipments/{id}/cancel', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'cancel']);
        Route::post('/shipments/{id}/sync', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'sync']);
        Route::get('/shiprocket/wallet', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'wallet']);
        Route::get('/shiprocket/doctor', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'doctor']);
        Route::post('/shipments/{id}/ndr/reattempt', [\App\Http\Controllers\Api\V1\Admin\AdminShipmentController::class, 'reattemptNdr']);
    });
});
