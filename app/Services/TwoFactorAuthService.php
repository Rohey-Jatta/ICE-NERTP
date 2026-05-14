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

        // Always log the code so developers can test without real SMS
        Log::info("[2FA] Code for user_id={$user->id} email={$user->email}: {$code}");

        return $code;
    }

    /**
     * Send the code via the configured SMS driver.
     * Never throws — always returns bool.
     */
    public function sendCode(User $user, string $code): bool
    {
        if (!$user->phone) {
            Log::warning("[2FA] User {$user->email} has no phone number. Code is in the log above.");
            return false;
        }

        try {
            $sent = $this->smsService->send2FACode($user->phone, $code);

            if (!$sent) {
                Log::warning("[2FA] SMS send returned false for user {$user->email}. Check SMS driver logs.");
            }

            return $sent;

        } catch (\Throwable $e) {
            Log::error("[2FA] sendCode exception for {$user->email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alias used by LoginController — generates and sends in one call.
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
            Log::warning("[2FA] No code in cache for user {$user->id}. It may have expired.");
            return false;
        }

        if (hash_equals($storedCode, $code)) {
            Cache::forget($cacheKey);
            Log::info("[2FA] Code verified successfully for user {$user->id}");
            return true;
        }

        Log::warning("[2FA] Code mismatch for user {$user->id}");
        return false;
    }
}