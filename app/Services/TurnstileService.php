<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    protected ?string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.cloudflare.turnstile_secret', env('TURNSTILE_SECRET'));
    }

    /**
     * Verify Turnstile token with Cloudflare siteverify API.
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        // If Turnstile is not configured, or during local unit testing, bypass gracefully
        if (empty($this->secretKey) || app()->environment('testing')) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification network error: '.$e->getMessage());
            // Fail open on Cloudflare network timeout to avoid rejecting legitimate buyers
            return true;
        }
    }
}
