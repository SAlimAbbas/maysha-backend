<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustBffClientIp
{
    /**
     * Trust client IP forwarded by Next.js BFF only when valid shared secret matches.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = (string) (config('services.bff.secret') ?: env('BFF_SECRET'));
        $providedSecret = (string) $request->header('X-Maysha-BFF-Secret');
        $clientIp = $request->header('X-Maysha-Client-IP');

        if (! empty($expectedSecret) && ! empty($providedSecret) && hash_equals($expectedSecret, $providedSecret)) {
            if ($clientIp && filter_var($clientIp, FILTER_VALIDATE_IP)) {
                $request->server->set('REMOTE_ADDR', $clientIp);
            }
        }

        return $next($request);
    }
}
