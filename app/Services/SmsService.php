<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $driver;

    public function __construct()
    {
        $this->driver = config('services.sms.driver', 'log');
    }

    /**
     * Send an SMS message.
     * Returns true on success, false on failure.
     */
    public function send(string $phoneNumber, string $message): bool
    {
        Log::info("[SMS] Driver={$this->driver} | TO: {$phoneNumber} | MSG: {$message}");

        return match ($this->driver) {
            'twilio'         => $this->sendViaTwilio($phoneNumber, $message),
            'africastalking' => $this->sendViaAfricasTalking($phoneNumber, $message),
            'log'            => $this->sendViaLog($phoneNumber, $message),
            default          => $this->sendViaLog($phoneNumber, $message),
        };
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
     * Check if SMS will actually be delivered (not just logged).
     */
    public function isAvailable(): bool
    {
        return $this->driver !== 'log';
    }

    // ── Drivers ───────────────────────────────────────────────────────────────

    private function sendViaTwilio(string $phoneNumber, string $message): bool
    {
        $sid   = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from  = config('services.twilio.from');

        if (!$sid || !$token || !$from) {
            Log::error('[SMS][Twilio] Missing credentials. Check TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM in .env');
            return false;
        }

        try {
            $client = new \Twilio\Rest\Client($sid, $token);

            $client->messages->create(
                $phoneNumber,
                [
                    'from' => $from,
                    'body' => $message,
                ]
            );

            Log::info('[SMS][Twilio] Message sent successfully to ' . $phoneNumber);
            return true;

        } catch (\Twilio\Exceptions\RestException $e) {
            Log::error('[SMS][Twilio] RestException: ' . $e->getMessage() . ' (Code: ' . $e->getStatusCode() . ')');
            return false;
        } catch (\Exception $e) {
            Log::error('[SMS][Twilio] Exception: ' . $e->getMessage());
            return false;
        }
    }

    private function sendViaAfricasTalking(string $phoneNumber, string $message): bool
    {
        $username = config('services.africastalking.username');
        $apiKey   = config('services.africastalking.api_key');
        $from     = config('services.africastalking.from', 'IEC_NERTP');

        if (!$username || !$apiKey || $username === 'sandbox') {
            Log::error('[SMS][AfricasTalking] Missing or sandbox credentials. Cannot send real SMS.');
            return false;
        }

        try {
            $AT  = new \AfricasTalking\SDK\AfricasTalking($username, $apiKey);
            $sms = $AT->sms();

            $result = $sms->send([
                'to'      => $phoneNumber,
                'message' => $message,
                'from'    => $from,
            ]);

            Log::info('[SMS][AfricasTalking] Sent successfully', ['result' => $result]);
            return true;

        } catch (\Exception $e) {
            Log::error('[SMS][AfricasTalking] Failed: ' . $e->getMessage());
            return false;
        }
    }

    private function sendViaLog(string $phoneNumber, string $message): bool
    {
        // In log mode, the code is already logged above in send().
        // This is intentional for local/dev — check storage/logs/laravel.log
        Log::info('[SMS][Log] SMS not sent — driver is "log". Set SMS_DRIVER=twilio in .env to send real SMS.');
        return true; // Return true so login flow isn't blocked during development
    }
}