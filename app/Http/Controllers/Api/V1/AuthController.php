<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new customer account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => 'customer',
        ]);

        event(new Registered($user));

        $token = $user->createToken('maysha-storefront')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Registration successful. A verification email has been sent.', [], 201);
    }

    /**
     * Authenticate customer with email and password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower($validated['email']);

        $user = User::where('email', $email)->first();

        // Timing-attack prevention: perform dummy hash check if user not found
        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            if (! $user || ! $user->password) {
                Hash::check($validated['password'], '$2y$12$e8rP/k4E7t9aK8s.Jz8A0eB1h3C4D5E6F7G8H9I0J1K2L3M4N5O6P');
            }

            return $this->error('Invalid credentials provided.', 401);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('maysha-storefront')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful.');
    }

    /**
     * Invalidate the current session / token.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()?->delete();
        }

        return $this->success(null, 'Successfully logged out.');
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update customer profile details.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->success([
            'user' => new UserResource($user),
        ], 'Profile updated successfully.');
    }

    /**
     * Change user password and terminate other active sessions.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! $user->password || ! Hash::check($validated['current_password'], $user->password)) {
            return $this->error('Current password does not match.', 422, [
                'current_password' => ['The provided current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        // Revoke all other tokens except the current one
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return $this->success(null, 'Password updated successfully. Other sessions logged out.');
    }

    /**
     * Request a password reset link (Uniform response prevents user enumeration).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower($request->input('email'));

        Password::sendResetLink(['email' => $email]);

        return $this->success(null, 'If an account exists with this email address, a password reset link has been dispatched.');
    }

    /**
     * Reset password using a valid reset token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Invalidate all existing tokens on password reset
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Your password has been successfully reset. Please log in with your new credentials.');
        }

        return $this->error('Invalid or expired password reset token.', 422, [
            'token' => ['This password reset token is invalid or has expired.'],
        ]);
    }

    /**
     * Verify user email via signed URL.
     */
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse|RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid verification link.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(null, 'Email is already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));

        return redirect()->away("{$frontendUrl}/auth/login?verified=1");
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->error('Email address is already verified.', 400);
        }

        $user->sendEmailVerificationNotification();

        return $this->success(null, 'Verification link has been sent to your email.');
    }

    /**
     * Redirect to Google OAuth provider with state parameter.
     */
    public function redirectToGoogle(): Response
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback with anti-account takeover linking rules.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));

        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->stateless(false)->user();
        } catch (\Exception $e) {
            return redirect()->away("{$frontendUrl}/auth/login?error=".urlencode('Google authentication failed. Please try again.'));
        }

        $googleId = $googleUser->getId();
        $email = strtolower($googleUser->getEmail());
        $isGoogleEmailVerified = (bool) ($googleUser->user['email_verified'] ?? true);

        // 1. Check if user already linked with this google_id
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            // 2. Check if user with this email already exists
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {
                // Critical Account Takeover Check: Only link if Google verified the email AND existing user is already verified
                if (! $isGoogleEmailVerified || is_null($existingUser->email_verified_at)) {
                    return redirect()->away("{$frontendUrl}/auth/login?error=".urlencode('An account with this email exists but is unverified. Please verify your email before using Google sign in.'));
                }

                // Safe to link
                $existingUser->update([
                    'google_id' => $googleId,
                    'avatar' => $existingUser->avatar ?: $googleUser->getAvatar(),
                ]);
                $user = $existingUser;
            } else {
                // Create new Google-authenticated user
                $user = User::create([
                    'name' => $googleUser->getName() ?: 'Maysha Customer',
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $googleUser->getAvatar(),
                    'role' => 'customer',
                    'email_verified_at' => now(),
                    'password' => null,
                ]);
            }
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('maysha-storefront')->plainTextToken;

        return redirect()->away("{$frontendUrl}/auth/callback?token={$token}");
    }
}
