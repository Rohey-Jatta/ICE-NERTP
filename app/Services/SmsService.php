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

        // Only initialise the SDK if both credentials are present AND
        // we're not using the sandbox placeholder values
        if ($username && $apiKey && $username !== 'sandbox' && $apiKey !== 'your-api-key') {
            try {
                $AT        = new AfricasTalking($username, $apiKey);
                $this->sms = $AT->sms();
                $this->isConfigured = true;
            } catch (\Exception $e) {
                Log::warning('[SmsService] Failed to initialise Africa\'s Talking SDK: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send SMS to a phone number.
     * Always returns true in local / unconfigured environments so the
     * rest of the login flow can continue without blocking on a network call.
     */
    public function send(string $phoneNumber, string $message): bool
    {
        // Development / unconfigured — log only, never make a network call
        if (!$this->isConfigured || app()->environment('local', 'testing')) {
            Log::info("[SMS] TO {$phoneNumber}: {$message}");
            return true;
        }

        try {
            $result = $this->sms->send([
                'to'      => $phoneNumber,
                'message' => $message,
                'from'    => config('services.africastalking.from', 'IEC_NERTP'),
            ]);

            Log::info('[SMS] Sent successfully', ['result' => $result]);
            return true;
        } catch (\Exception $e) {
            // Log but don't crash — the 2FA code is still in cache and visible in logs
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
                 . "This code expires in 10 minutes. Do not share this code with anyone.";

        return $this->send($phoneNumber, $message);
    }
}