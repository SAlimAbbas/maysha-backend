<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Concern;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Observers\BannerObserver;
use App\Observers\CategoryObserver;
use App\Observers\ConcernObserver;
use App\Observers\ProductObserver;
use App\Observers\ReviewObserver;
use App\Observers\SiteSettingObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerModelObservers();

        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            return "{$frontendUrl}/auth/reset-password?token={$token}&email=" . urlencode($notifiable->getEmailForPasswordReset());
        });
    }

    /**
     * Register model observers for catalog cache invalidation and ISR revalidation.
     */
    protected function registerModelObservers(): void
    {
        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);
        Concern::observe(ConcernObserver::class);
        Banner::observe(BannerObserver::class);
        SiteSetting::observe(SiteSettingObserver::class);
        Review::observe(ReviewObserver::class);
    }

    /**
     * Configure named rate limiters across all storefront and admin endpoints.
     */
    protected function configureRateLimiting(): void
    {
        // Global API default: 120/min
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Catalog reads: 120/min
        RateLimiter::for('catalog', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Search & Suggest: 30/min
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Auth Login: 5/min per email+IP and 20/hour per IP
        RateLimiter::for('auth-login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perHour(20)->by($request->ip()),
            ];
        });

        // Auth Register: 5/hour per IP
        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // Auth Forgot Password: 3/hour per email & 10/hour per IP
        RateLimiter::for('auth-forgot', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perHour(3)->by($email),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Auth Verify Resend: 3 per 10 mins
        RateLimiter::for('auth-verify-resend', function (Request $request) {
            return Limit::perMinutes(10, 3)->by($request->user()?->id ?: $request->ip());
        });

        // Auth Google: 20/min
        RateLimiter::for('auth-google', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Cart mutations: 60/min
        RateLimiter::for('cart', function (Request $request) {
            $cartToken = $request->header('X-Cart-Token') ?: $request->cookie('maysha_cart_token', '');

            return Limit::perMinute(60)->by($cartToken.'|'.$request->ip());
        });

        // Coupon application: 10/min
        RateLimiter::for('coupon', function (Request $request) {
            $userKey = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(10)->by($userKey);
        });

        // Checkout & Order creation: 10/hour
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
        });

        // Payment verification: 20/min
        RateLimiter::for('payment-verify', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Review submissions: 3/hour
        RateLimiter::for('reviews', function (Request $request) {
            return Limit::perHour(3)->by($request->user()?->id ?: $request->ip());
        });

        // Newsletter and Contact forms: 3/hour
        RateLimiter::for('newsletter', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        RateLimiter::for('contact', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        // Media signing (admin): 30/min
        RateLimiter::for('media-sign', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Admin general: 60/min
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Admin login: 5/min
        RateLimiter::for('admin-login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
