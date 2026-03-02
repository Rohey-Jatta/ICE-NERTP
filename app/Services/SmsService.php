<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $sms;

    public function __construct()
    {
        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');

        if ($username && $apiKey) {
            $AT = new AfricasTalking($username, $apiKey);
            $this->sms = $AT->sms();
        }
    }

    /**
     * Send SMS to a phone number
     */
    public function send(string $phoneNumber, string $message): bool
    {
        try {
            // For development/testing - just log the message
            if (config('app.env') === 'local' || !$this->sms) {
                Log::info("SMS TO {$phoneNumber}: {$message}");
                // Return true to simulate success in development
                return true;
            }

            // Production: Actually send SMS
            $result = $this->sms->send([
                'to' => $phoneNumber,
                'message' => $message,
                'from' => config('services.africastalking.from', 'IEC_NERTP'),
            ]);

            Log::info("SMS sent successfully", ['result' => $result]);
            return true;
        } catch (\Exception $e) {
            Log::error("SMS sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send 2FA verification code
     */
    public function send2FACode(string $phoneNumber, string $code): bool
    {
        $message = "Your IEC NERTP verification code is: {$code}. This code expires in 10 minutes. Do not share this code with anyone.";
        return $this->send($phoneNumber, $message);
    }
}
