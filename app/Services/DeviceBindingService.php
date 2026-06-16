<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceBindingService
{
    const DEVICE_COOKIE_NAME = 'iec_device_id';
    const DEVICE_COOKIE_TTL = 30;
    const PENDING_DEVICE_KEY = 'iec_pending_device_';

    public function checkDevice(User $user, Request $request): string
    {
        $roleNames = $user->getRoleNames();
        $userRole = $roleNames?->first();

        if (!Device::roleRequiresBinding($userRole)) {
            return 'not_required';
        }

        $fingerprint = $this->extractFingerprint($request);

        if (!$fingerprint) {
            return 'pending_registration';
        }

        $device = Device::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->first();

        if (!$device) {
            $this->storePendingDevice($user, $request, $fingerprint);
            return 'pending_registration';
        }

        if ($device->is_revoked) {
            AuditLog::record(
                action: 'auth.device.revoked_access_attempt',
                event: 'blocked',
                module: 'Authentication',
                auditable: $device,
                extra: ['outcome' => 'blocked', 'failure_reason' => 'Revoked device attempted login']
            );
            return 'revoked';
        }

        $device->recordUsage($request->ip());

        AuditLog::record(
            action: 'auth.device.recognized',
            event: 'updated',
            module: 'Authentication',
            auditable: $device,
            extra: ['device_fingerprint' => $fingerprint, 'outcome' => 'success']
        );

        return 'trusted';
    }

    public function registerDevice(User $user, Request $request, string $deviceName): Device
    {
        $fingerprint = $this->extractFingerprint($request);
        $pending = $this->getPendingDevice($user);
        
        $finalFingerprint = $fingerprint ?? $this->generateFingerprint($request);

        // Check if this device already exists for this user
        $existingDevice = Device::where('user_id', $user->id)
            ->where('device_fingerprint', $finalFingerprint)
            ->first();

        if ($existingDevice) {
            // Device already exists for this user — just update it and mark as verified
            $existingDevice->update([
                'device_name' => $deviceName,
                'verified_at' => now(),
                'verified_by_ip' => $request->ip(),
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
                'is_trusted' => true,
                'is_revoked' => false,
            ]);

            if ($user->bound_device_id === null) {
                $user->bound_device_id = $existingDevice->id;
                $user->save();
            }

            $this->clearPendingDevice($user);
            return $existingDevice;
        }

        // Device doesn't exist for this user — create it
        try {
            $device = Device::create([
                'user_id' => $user->id,
                'device_fingerprint' => $finalFingerprint,
                'device_name' => $deviceName,
                'device_type' => $this->detectDeviceType($request),
                'os' => $pending['os'] ?? $this->detectOs($request),
                'browser' => $pending['browser'] ?? $this->detectBrowser($request),
                'verified_at' => now(),
                'verified_by_ip' => $request->ip(),
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
                'is_trusted' => true,
                'is_revoked' => false,
            ]);
        } catch (\Exception $e) {
            \Log::error('Device creation failed: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }

        if ($user->bound_device_id === null) {
            $user->bound_device_id = $device->id;
            $user->save();

            try {
                AuditLog::record(
                    action: 'auth.device.bound',
                    event: 'created',
                    module: 'Authentication',
                    auditable: $user,
                    extra: ['device_id' => $device->id, 'device_fingerprint' => $device->device_fingerprint]
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to record audit log for device.bound: ' . $e->getMessage());
            }
        }

        $this->clearPendingDevice($user);

        try {
            AuditLog::record(
                action: 'auth.device.registered',
                event: 'created',
                module: 'Authentication',
                auditable: $device,
                extra: [
                    'device_fingerprint' => $device->device_fingerprint,
                    'outcome' => 'success',
                ]
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to record audit log for device.registered: ' . $e->getMessage());
        }

        return $device;
    }

    public function getUserDevices(User $user)
    {
        return Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->orderByDesc('last_used_at')
            ->get();
    }

    public function revokeDevice(User $user, int $deviceId): bool
    {
        $device = Device::where('user_id', $user->id)
            ->where('id', $deviceId)
            ->firstOrFail();

        $device->revoke();

        AuditLog::record(
            action: 'auth.device.revoked',
            event: 'updated',
            module: 'Authentication',
            auditable: $device,
            extra: ['outcome' => 'success']
        );

        return true;
    }

    private function storePendingDevice(User $user, Request $request, string $fingerprint): void
    {
        Cache::put(
            self::PENDING_DEVICE_KEY . $user->id,
            [
                'fingerprint' => $fingerprint,
                'ip' => $request->ip(),
                'os' => $this->detectOs($request),
                'browser' => $this->detectBrowser($request),
                'type' => $this->detectDeviceType($request),
            ],
            now()->addMinutes(30)
        );
    }

    public function getPendingDevice(User $user): ?array
    {
        return Cache::get(self::PENDING_DEVICE_KEY . $user->id);
    }

    private function clearPendingDevice(User $user): void
    {
        Cache::forget(self::PENDING_DEVICE_KEY . $user->id);
    }

    public function extractFingerprint(Request $request): ?string
    {
        $cookie = $request->cookie(self::DEVICE_COOKIE_NAME);
        if ($cookie) {
            $fingerprint = trim($cookie);
        } else {
            $header = $request->header('X-DEVICE-ID') ?? $request->header('x-device-id');
            if ($header) {
                $fingerprint = trim($header);
            } else {
                $serverHeader = $request->server('HTTP_X_DEVICE_ID') ?? $request->server('X_DEVICE_ID');
                if ($serverHeader) {
                    $fingerprint = trim($serverHeader);
                } else {
                    $fingerprint = $request->input('device_id') ? trim($request->input('device_id')) : null;
                }
            }
        }

        return $fingerprint;
    }

    public function generateFingerprint(Request $request): string
    {
        return hash('sha256',
            $request->ip() .
            $request->userAgent() .
            $request->header('Accept-Language', '') .
            uniqid('', true)
        );
    }

    private function detectDeviceType(Request $request): string
    {
        $ua = strtolower($request->userAgent() ?? '');
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android')) return 'mobile';
        return 'desktop';
    }

    private function detectOs(Request $request): string
    {
        $ua = $request->userAgent() ?? '';
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
        if (str_contains($ua, 'Mac')) return 'macOS';
        if (str_contains($ua, 'Linux')) return 'Linux';
        return 'Unknown';
    }

    private function detectBrowser(Request $request): string
    {
        $ua = $request->userAgent() ?? '';
        if (str_contains($ua, 'Chrome')) return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari')) return 'Safari';
        if (str_contains($ua, 'Edge')) return 'Edge';
        return 'Unknown';
    }
}
