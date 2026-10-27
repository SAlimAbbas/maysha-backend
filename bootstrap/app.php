<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configure trusted proxies safely (restrict to TRUSTED_PROXIES env or Cloudflare IP ranges)
        $trustedProxies = env('TRUSTED_PROXIES');
        $cloudflareRanges = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];

        if ($trustedProxies === 'cloudflare') {
            $middleware->trustProxies(at: $cloudflareRanges);
        } elseif ($trustedProxies === '*' && app()->isLocal()) {
            $middleware->trustProxies(at: '*');
        } elseif (! empty($trustedProxies)) {
            $middleware->trustProxies(at: array_map('trim', explode(',', $trustedProxies)));
        } else {
            $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        }

        // Trust edge client IP from BFF when secret matches
        $middleware->prepend(\App\Http\Middleware\TrustBffClientIp::class);

        // Security headers across all responses
        $middleware->append(SecurityHeaders::class);

        // Sanctum stateful authentication for frontend SPA
        $middleware->statefulApi();

        // Middleware aliases
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'optional.sanctum' => \App\Http\Middleware\OptionalSanctumAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Standardized JSON error response envelope for API
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in to proceed.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This action is unauthorized.',
                ], 403);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                ], 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down and try again later.',
                ], 429, $e->getHeaders());
            }
        });

        $exceptions->render(function (HttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred.',
                ], $e->getStatusCode(), $e->getHeaders());
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $isDebug = config('app.debug', false);

                return response()->json([
                    'success' => false,
                    'message' => $isDebug ? $e->getMessage() : 'An internal server error occurred.',
                ], 500);
            }
        });
    })->create();
