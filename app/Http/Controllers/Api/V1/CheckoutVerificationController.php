<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Mail\CheckoutOtpMail;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class CheckoutVerificationController extends Controller
{
    use ApiResponse;

    /**
     * Send a 6-digit confirmation OTP to the client's email before order placement.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($request->input('email')));
        $ip = $request->ip() ?: '127.0.0.1';
        $rateLimitKey = 'checkout-otp:' . sha1($email . '|' . $ip);

        // Throttle: max 5 requests per 10 minutes
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return $this->error("Too many OTP requests. Please wait {$seconds} seconds before trying again.", 429);
        }

        RateLimiter::hit($rateLimitKey, 600);

        // Generate 6-digit OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        $emailHash = sha1($email);
        $otpHash = hash_hmac('sha256', $otp, (string) config('app.key'));

        // Store hashed OTP for 10 minutes
        Cache::put("checkout_otp:{$emailHash}", $otpHash, 600);
        Cache::put("checkout_otp_attempts:{$emailHash}", 0, 600);

        try {
            Mail::to($email)->queue(new CheckoutOtpMail($otp));
        } catch (\Throwable $e) {
            return $this->error('Unable to send verification email at this moment. Please verify your address and retry.', 500);
        }

        return $this->success([
            'email' => $email,
            'expiresInSeconds' => 600,
        ], "A 6-digit verification code has been sent to {$email}.");
    }

    /**
     * Verify the 6-digit OTP entered by the client.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'otp' => 'required|string|size:6',
        ]);

        $email = strtolower(trim($request->input('email')));
        $enteredOtp = trim($request->input('otp'));
        $emailHash = sha1($email);

        $storedHash = Cache::get("checkout_otp:{$emailHash}");
        if (! $storedHash) {
            return $this->error('The verification code has expired or was not requested. Please request a new code.', 422);
        }

        $attempts = (int) Cache::get("checkout_otp_attempts:{$emailHash}", 0);
        if ($attempts >= 5) {
            Cache::forget("checkout_otp:{$emailHash}");
            return $this->error('Too many incorrect attempts. Please request a new verification code.', 429);
        }

        $expectedHash = hash_hmac('sha256', $enteredOtp, (string) config('app.key'));

        if (! hash_equals($storedHash, $expectedHash)) {
            Cache::put("checkout_otp_attempts:{$emailHash}", $attempts + 1, 600);
            return $this->error('Invalid verification code. Please check your inbox and enter the 6 digits correctly.', 422);
        }

        // OTP matched - remove pending OTP and mark email verified for 1 hour
        Cache::forget("checkout_otp:{$emailHash}");
        Cache::forget("checkout_otp_attempts:{$emailHash}");
        Cache::put("checkout_verified:{$emailHash}", true, 3600);

        // Generate tamper-evident token
        $payload = json_encode([
            'email' => $email,
            'verified_at' => time(),
        ]);
        $sig = hash_hmac('sha256', $payload, (string) config('app.key'));
        $verificationToken = base64_encode($payload) . '.' . $sig;

        return $this->success([
            'verified' => true,
            'email' => $email,
            'verificationToken' => $verificationToken,
        ], 'Email address verified successfully.');
    }

    /**
     * Check if an email is verified for checkout.
     */
    public static function isEmailVerified(string $email, ?string $token = null): bool
    {
        $normalized = strtolower(trim($email));
        $emailHash = sha1($normalized);

        // Check active cache session
        if (Cache::has("checkout_verified:{$emailHash}")) {
            return true;
        }

        // Validate token signature if provided
        if (! empty($token) && str_contains($token, '.')) {
            [$b64, $sig] = explode('.', $token, 2);
            $payload = base64_decode($b64, true);
            if ($payload) {
                $expectedSig = hash_hmac('sha256', $payload, (string) config('app.key'));
                if (hash_equals($expectedSig, $sig)) {
                    $data = json_decode($payload, true);
                    if (is_array($data) && isset($data['email'], $data['verified_at'])) {
                        if (strtolower(trim($data['email'])) === $normalized) {
                            // Valid within 1 hour
                            if ((time() - (int) $data['verified_at']) < 3600) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }
}
