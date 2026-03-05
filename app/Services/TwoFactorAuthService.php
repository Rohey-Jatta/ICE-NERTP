<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwoFactorAuthService
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Generate code and cahe a 6-digit 2FA code for the given user
     */

    public function generateCode(User $user): string
    {
        // Generate 6-digit code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store code in cache for 10 minutes
        $cacheKey = "2fa_code_{$user->id}";
        Cache::put($cacheKey, $code, now()->addMinutes(10));

        return $code;
    }

    // send the generated code to the user's phone number via SMS
    public function sendCode(User $user, string $code): bool
    {

        Log::info("2FA Code for {$user->email} (" . ($user->phone ?? 'NO PHONE') . "): {$code}");

        // Send SMS
        return $this->smsService->send2FACode($user->phone, $code);
    }

    /**
     * Verify 2FA code
     */
    public function verifyCode(User $user, string $code): bool
    {
        $cacheKey = "2fa_code_{$user->id}";
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode) {
            return false;
        }

        if ($code === $storedCode) {
            // Clear the code after successful verification
            Cache::forget($cacheKey);
            return true;
        }

        return false;
    }
}
