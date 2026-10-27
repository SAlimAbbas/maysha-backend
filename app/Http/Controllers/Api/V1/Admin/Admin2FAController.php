<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class Admin2FAController extends Controller
{
    use ApiResponse;
    public function __construct(protected TotpService $totpService)
    {
    }

    /**
     * Get current 2FA status for the authenticated admin.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmedAt' => $user->two_factor_confirmed_at?->toIso8601String(),
            'recoveryCodesRemaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    /**
     * Generate a new TOTP secret, QR URI, and recovery codes for enrollment.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->totpService->generateSecret();
        $qrUri = $this->totpService->getOtpAuthUri($user->email, $secret, 'Maysha Admin');
        $recoveryCodes = $this->totpService->generateRecoveryCodes(10);

        // Store temporarily in cache or session until confirmed
        cache()->put("admin_totp_setup_{$user->id}", [
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes['hashed'],
        ], now()->addMinutes(15));

        return $this->success([
            'secret' => $secret,
            'qrUri' => $qrUri,
            'recoveryCodes' => $recoveryCodes['plain'],
        ], 'TOTP enrollment initiated. Verify with your authenticator code to activate.');
    }

    /**
     * Confirm and activate TOTP for the authenticated admin.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $setupData = cache()->get("admin_totp_setup_{$user->id}");

        if (! $setupData) {
            return $this->error('TOTP setup session expired. Please restart enrollment.', 422);
        }

        if (! $this->totpService->verify($setupData['secret'], $validated['code'])) {
            return $this->error('Invalid TOTP verification code.', 422);
        }

        $user->update([
            'two_factor_secret' => $setupData['secret'],
            'two_factor_recovery_codes' => $setupData['recovery_codes'],
            'two_factor_confirmed_at' => now(),
        ]);

        cache()->forget("admin_totp_setup_{$user->id}");

        AuditLog::record('admin.2fa.totp_enabled', $user, $user);

        return $this->success(null, 'Two-Factor Authentication (TOTP) successfully enabled.');
    }

    /**
     * Disable TOTP for the authenticated admin.
     */
    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return $this->error('Incorrect password. Cannot disable 2FA.', 403);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        AuditLog::record('admin.2fa.totp_disabled', $user, $user);

        return $this->success(null, 'Two-Factor Authentication disabled.');
    }
}
