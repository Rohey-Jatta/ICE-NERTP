<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwoFactorAuthService
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Generate a 6-digit 2FA code and store it in cache for 10 minutes.
     */
    public function generateCode(User $user): string
    {
        $code     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "2fa_code_{$user->id}";
        Cache::put($cacheKey, $code, now()->addMinutes(10));

        return $code;
    }

    /**
     * Send the generated code to the user's phone number via SMS.
     * Always logs the code so developers can use it even without SMS configured.
     */
    public function sendCode(User $user, string $code): bool
    {
        // Always log so the code is accessible during development
        Log::info("[2FA] Code for {$user->email} ({$user->phone}): {$code}");

        // Send via SMS — SmsService handles dev/prod branching internally
        // and will never block on a network call in local environments
        return $this->smsService->send2FACode($user->phone, $code);
    }

    /**
     * Alias used by some controllers.
     */
    public function sendSmsOtp(User $user): bool
    {
        $code = $this->generateCode($user);
        return $this->sendCode($user, $code);
    }

    /**
     * Verify a submitted 2FA code against the cached value.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $cacheKey   = "2fa_code_{$user->id}";
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode) {
            return false;
        }

        if (hash_equals($storedCode, $code)) {
            Cache::forget($cacheKey);
            return true;
        }

        return false;
    }
}