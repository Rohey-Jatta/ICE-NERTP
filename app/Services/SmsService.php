<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $sms;
    protected bool $isConfigured = false;

    public function __construct()
    {
        $username = config('services.africastalking.username');
        $apiKey   = config('services.africastalking.api_key');

        // Only initialise if real credentials are provided
        if ($username && $apiKey && $username !== 'sandbox' && $apiKey !== 'your-api-key') {
            try {
                $AT        = new AfricasTalking($username, $apiKey);
                $this->sms = $AT->sms();
                $this->isConfigured = true;
            } catch (\Exception $e) {
                Log::warning('[SmsService] Failed to initialise SDK: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send SMS. Never blocks more than ~10 s.
     * In local/development/testing it ONLY logs — no network call.
     */
    public function send(string $phoneNumber, string $message): bool
    {
        // ── Dev / unconfigured: log and return immediately ────────────────────
        if (!$this->isConfigured || app()->environment(['local', 'testing', 'development'])) {
            Log::info("[SMS] TO {$phoneNumber}: {$message}");
            return true;
        }

        // ── Production: attempt send with hard time limit ─────────────────────
        // Always log the message first so admins can see the code even on failure
        Log::info("[SMS] Sending to {$phoneNumber}");

        try {
            // Cap SMS sending to 10 seconds — prevents blocking the PHP process
            if (function_exists('set_time_limit')) {
                set_time_limit(10);
            }

            $result = $this->sms->send([
                'to'      => $phoneNumber,
                'message' => $message,
                'from'    => config('services.africastalking.from', 'IEC_NERTP'),
            ]);

            Log::info('[SMS] Sent successfully', ['result' => $result]);
            return true;

        } catch (\Exception $e) {
            Log::error('[SMS] Sending failed: ' . $e->getMessage());
            // Fallback: log the message so admins can manually share the code
            Log::info("[SMS] FALLBACK - TO {$phoneNumber}: {$message}");
            return false;
        }
    }

    /**
     * Send 2FA verification code.
     */
    public function send2FACode(string $phoneNumber, string $code): bool
    {
        $message = "Your IEC NERTP verification code is: {$code}. "
                 . "This code expires in 10 minutes. Do not share this code.";

        return $this->send($phoneNumber, $message);
    }

    /**
     * Check if SMS service is properly configured.
     */
    public function isAvailable(): bool
    {
        return $this->isConfigured;
    }
}
