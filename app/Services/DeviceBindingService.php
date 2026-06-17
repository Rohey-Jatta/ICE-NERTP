<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeviceBindingService
{
    /**
     * Derive a stable server-side fingerprint from request signals.
     * Uses User-Agent + Accept-Language + IP subnet (first 3 octets).
     * This is intentionally NOT a cookie — it cannot be spoofed by the client.
     */
    public function deriveServerFingerprint(Request $request): string
    {
        $ua       = $request->userAgent() ?? 'unknown';
        $lang     = $request->header('Accept-Language', 'unknown');

        // Use the first 3 octets of the IP so NAT/DHCP minor changes don't break it
        $ipParts  = explode('.', $request->ip());
        $ipSubnet = implode('.', array_slice($ipParts, 0, 3));

        return hash('sha256', $ua . '|' . $lang . '|' . $ipSubnet);
    }

    /**
     * Check whether the current device is allowed for this user.
     *
     * Returns:
     *   'not_required'       — role doesn't need device binding
     *   'no_device_bound'    — first login, no device registered yet
     *   'trusted'            — fingerprint matches
     *   'mismatch'           — fingerprint doesn't match registered device
     *   'revoked'            — device was revoked
     */
    public function checkDevice(User $user, Request $request): string
    {
        $userRole = $user->getRoleNames()->first();

        if (!Device::roleRequiresBinding($userRole)) {
            return 'not_required';
        }

        $fingerprint = $this->deriveServerFingerprint($request);

        // Find any active (non-revoked) device for this user
        $device = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->first();

        if (!$device) {
            // Store pending fingerprint so registration can use it
            $this->storePendingFingerprint($user, $request, $fingerprint);
            return 'no_device_bound';
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

        // Compare server-derived fingerprint with stored one
        if (hash_equals($device->device_fingerprint, $fingerprint)) {
            $device->recordUsage($request->ip());

            AuditLog::record(
                action: 'auth.device.validated',
                event: 'updated',
                module: 'Authentication',
                auditable: $device,
                extra: ['outcome' => 'success', 'ip' => $request->ip()]
            );

            return 'trusted';
        }

        // Fingerprint mismatch — different device
        AuditLog::record(
            action: 'auth.device.mismatch',
            event: 'blocked',
            module: 'Authentication',
            extra: [
                'outcome'        => 'blocked',
                'failure_reason' => 'Device fingerprint mismatch',
                'user_id'        => $user->id,
                'stored_device'  => $device->id,
                'ip'             => $request->ip(),
            ]
        );

        return 'mismatch';
    }

    /**
     * Register a new device for a user silently (called after OTP verification).
     * This is the ONLY place where device registration happens.
     */
    public function registerDeviceSilently(User $user, Request $request): Device
    {
        $fingerprint = $this->deriveServerFingerprint($request);
        $pending     = $this->getPendingFingerprint($user);

        // If there's already an active device, revoke it first (admin reset scenario)
        Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->update([
                'is_revoked' => true,
                'revoked_at' => now(),
                'is_trusted' => false,
            ]);

        $device = Device::create([
            'user_id'          => $user->id,
            'device_fingerprint' => $fingerprint,
            'device_name'      => $this->detectDeviceName($request),
            'device_type'      => $this->detectDeviceType($request),
            'os'               => $this->detectOs($request),
            'browser'          => $this->detectBrowser($request),
            'verified_at'      => now(),
            'verified_by_ip'   => $request->ip(),
            'last_used_at'     => now(),
            'last_used_ip'     => $request->ip(),
            'is_trusted'       => true,
            'is_revoked'       => false,
        ]);

        $this->clearPendingFingerprint($user);

        AuditLog::record(
            action: 'auth.device.registered',
            event: 'created',
            module: 'Authentication',
            auditable: $device,
            extra: [
                'outcome'            => 'success',
                'device_type'        => $device->device_type,
                'os'                 => $device->os,
                'browser'            => $device->browser,
                'ip'                 => $request->ip(),
            ]
        );

        Log::info('[DeviceBinding] Device registered silently', [
            'user_id'   => $user->id,
            'device_id' => $device->id,
            'os'        => $device->os,
        ]);

        return $device;
    }

    /**
     * Admin: revoke (reset) a user's device binding so they can re-register.
     */
    public function revokeAllDevices(User $user, int $adminId): void
    {
        $count = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->count();

        Device::where('user_id', $user->id)->update([
            'is_trusted' => false,
            'is_revoked' => true,
            'revoked_at' => now(),
        ]);

        AuditLog::record(
            action: 'admin.device.reset',
            event: 'updated',
            module: 'DeviceManagement',
            extra: [
                'outcome'          => 'success',
                'target_user_id'   => $user->id,
                'admin_id'         => $adminId,
                'devices_revoked'  => $count,
            ]
        );

        Log::info('[DeviceBinding] Admin reset device binding', [
            'target_user' => $user->id,
            'admin'       => $adminId,
            'count'       => $count,
        ]);
    }

    /**
     * Admin: revoke a specific device.
     */
    public function revokeDevice(User $adminUser, int $deviceId): bool
    {
        $device = Device::findOrFail($deviceId);
        $device->revoke();

        AuditLog::record(
            action: 'admin.device.revoked',
            event: 'updated',
            module: 'DeviceManagement',
            auditable: $device,
            extra: [
                'outcome'  => 'success',
                'admin_id' => $adminUser->id,
            ]
        );

        return true;
    }

    /**
     * Get registered devices for a user (for admin display).
     */
    public function getUserDevices(User $user)
    {
        return Device::where('user_id', $user->id)
            ->orderByDesc('last_used_at')
            ->get();
    }

    // ── Pending fingerprint (stored in cache during 2FA flow) ─────────────────

    private function storePendingFingerprint(User $user, Request $request, string $fingerprint): void
    {
        Cache::put(
            'pending_device_' . $user->id,
            [
                'fingerprint' => $fingerprint,
                'ip'          => $request->ip(),
                'os'          => $this->detectOs($request),
                'browser'     => $this->detectBrowser($request),
                'type'        => $this->detectDeviceType($request),
            ],
            now()->addMinutes(30)
        );
    }

    public function getPendingFingerprint(User $user): ?array
    {
        return Cache::get('pending_device_' . $user->id);
    }

    private function clearPendingFingerprint(User $user): void
    {
        Cache::forget('pending_device_' . $user->id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Legacy method kept for backward compatibility with existing code.
     * @deprecated Use deriveServerFingerprint() instead.
     */
    public function extractFingerprint(Request $request): ?string
    {
        return $this->deriveServerFingerprint($request);
    }

    public function generateFingerprint(Request $request): string
    {
        return $this->deriveServerFingerprint($request);
    }

    private function detectDeviceName(Request $request): string
    {
        $os   = $this->detectOs($request);
        $type = $this->detectDeviceType($request);
        return ucfirst($type) . ' – ' . $os;
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
        if (str_contains($ua, 'Edg')) return 'Edge';
        if (str_contains($ua, 'Chrome')) return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari')) return 'Safari';
        return 'Unknown';
    }
}