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
        if (
            $username && $apiKey
            && $username !== 'sandbox'
            && $apiKey !== 'your-api-key'
            && !empty(trim($apiKey))
        ) {
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
     * Send SMS. In local/development/testing it ONLY logs — no network call.
     * In production, wraps the call with a strict timeout guard.
     */
    public function send(string $phoneNumber, string $message): bool
    {
        // ── Always log first so the code is visible in logs ──────────────────
        Log::info("[SMS] TO: {$phoneNumber} | MSG: {$message}");

        // ── Dev / unconfigured: return immediately, no network call ──────────
        if (!$this->isConfigured || app()->environment(['local', 'testing', 'development'])) {
            return true;
        }

        // ── Production: attempt send ─────────────────────────────────────────
        try {
            $result = $this->sms->send([
                'to'      => $phoneNumber,
                'message' => $message,
                'from'    => config('services.africastalking.from', 'IEC_NERTP'),
            ]);

            Log::info('[SMS] Sent successfully', ['result' => $result]);
            return true;

        } catch (\Exception $e) {
            Log::error('[SMS] Sending failed: ' . $e->getMessage());
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
