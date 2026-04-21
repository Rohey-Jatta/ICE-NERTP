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
     * Always stores in cache FIRST before any attempt to send.
     */
    public function generateCode(User $user): string
    {
        $code     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "2fa_code_{$user->id}";
        Cache::put($cacheKey, $code, now()->addMinutes(10));

        // Always log the code so it's accessible during development/testing
        Log::info("[2FA] Code generated for {$user->email}: {$code}");

        return $code;
    }

    /**
     * Send the code via SMS. Never throws — always returns bool.
     */
    public function sendCode(User $user, string $code): bool
    {
        try {
            return $this->smsService->send2FACode($user->phone, $code);
        } catch (\Throwable $e) {
            Log::error("[2FA] sendCode failed for {$user->email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alias used by some controllers — generates and sends in one call.
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
