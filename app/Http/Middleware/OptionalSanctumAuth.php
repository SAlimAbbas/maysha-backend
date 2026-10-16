<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class OptionalSanctumAuth
{
    /**
     * Handle an incoming request and resolve authenticated user from Sanctum if present.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            $user = Auth::guard('sanctum')->user();
            if ($user) {
                Auth::setUser($user);
                $request->setUserResolver(fn () => $user);
            }
        }

        return $next($request);
    }
}
