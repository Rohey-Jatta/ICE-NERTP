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
     *
     * Uses User-Agent only (NOT IP address) because:
     * - IP addresses change frequently (mobile networks, VPNs, DHCP)
     * - Including IP caused "mismatch" on every network change
     * - User-Agent is stable across a device's browser sessions
     *
     * The fingerprint is a SHA-256 hash so it's consistent and non-reversible.
     */
    public function deriveServerFingerprint(Request $request): string
    {
        $ua   = $request->userAgent() ?? 'unknown-ua';
        $lang = $request->header('Accept-Language', 'unknown-lang');

        // Normalise UA slightly to avoid micro-version noise
        // but keep it specific enough to differentiate devices
        $normalised = trim($ua) . '|' . trim($lang);

        return hash('sha256', $normalised);
    }

    /**
     * Check whether the current device is allowed for this user.
     *
     * Returns one of:
     *   'not_required'       — role doesn't need device binding
     *   'no_device_bound'    — first login, no device registered yet
     *   'trusted'            — fingerprint matches
     *   'mismatch'           — modern fingerprint that doesn't match
     *   'legacy_device'      — stored fingerprint is old UUID style
     *   'revoked'            — device was revoked
     */
    public function checkDevice(User $user, Request $request): string
    {
        $userRole = $user->getRoleNames()->first();

        if (!Device::roleRequiresBinding($userRole)) {
            return 'not_required';
        }

        $fingerprint = $this->deriveServerFingerprint($request);

        $device = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->latest('last_used_at')
            ->first();

        if (!$device) {
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

        // Stored fingerprint looks like a legacy UUID — treat as re-registration
        if ($this->isLegacyFingerprint($device->device_fingerprint)) {
            return 'legacy_device';
        }

        // Modern fingerprint mismatch — different device
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
     * Revokes all previous devices first, enforcing one-device-per-user.
     */
    public function registerDeviceSilently(User $user, Request $request): Device
    {
        $fingerprint = $this->deriveServerFingerprint($request);

        // Revoke all existing devices — one device per user rule
        $revokedCount = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->count();

        Device::where('user_id', $user->id)->update([
            'is_revoked' => true,
            'revoked_at' => now(),
            'is_trusted' => false,
        ]);

        if ($revokedCount > 0) {
            Log::info('[DeviceBinding] Revoked previous devices on re-registration', [
                'user_id' => $user->id,
                'count'   => $revokedCount,
            ]);
        }

        $device = Device::create([
            'user_id'            => $user->id,
            'device_fingerprint' => $fingerprint,
            'device_name'        => $this->detectDeviceName($request),
            'device_type'        => $this->detectDeviceType($request),
            'os'                 => $this->detectOs($request),
            'browser'            => $this->detectBrowser($request),
            'verified_at'        => now(),
            'verified_by_ip'     => $request->ip(),
            'last_used_at'       => now(),
            'last_used_ip'       => $request->ip(),
            'is_trusted'         => true,
            'is_revoked'         => false,
        ]);

        $this->clearPendingFingerprint($user);

        AuditLog::record(
            action: 'auth.device.registered',
            event: 'created',
            module: 'Authentication',
            auditable: $device,
            extra: [
                'outcome'     => 'success',
                'device_type' => $device->device_type,
                'os'          => $device->os,
                'browser'     => $device->browser,
                'ip'          => $request->ip(),
            ]
        );

        Log::info('[DeviceBinding] Device registered silently', [
            'user_id'     => $user->id,
            'device_id'   => $device->id,
            'os'          => $device->os,
            'fingerprint' => substr($fingerprint, 0, 16) . '...',
        ]);

        return $device;
    }

    /**
     * Admin: revoke (reset) all device bindings for a user.
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
                'outcome'         => 'success',
                'target_user_id'  => $user->id,
                'admin_id'        => $adminId,
                'devices_revoked' => $count,
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

    // ── Pending fingerprint (stored briefly during 2FA flow) ──────────────────

    /**
     * Public alias so TwoFactorController can call this without exposing internals.
     */
    public function storePendingFingerprintPublic(User $user, Request $request, string $fingerprint): void
    {
        $this->storePendingFingerprint($user, $request, $fingerprint);
    }

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

    // ── Fingerprint helpers ───────────────────────────────────────────────────

    /**
     * Detect whether a stored fingerprint is from the old cookie-based system.
     * Old: UUID (36 chars with dashes) or "iec-" prefixed random string.
     * New: SHA-256 hash (exactly 64 lowercase hex characters).
     */
    public function isLegacyFingerprint(string $fingerprint): bool
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fingerprint)) {
            return true;
        }
        if (str_starts_with($fingerprint, 'iec-')) {
            return true;
        }
        // Not a 64-char hex string = legacy
        return !preg_match('/^[0-9a-f]{64}$/', $fingerprint);
    }

    /**
     * Legacy alias.
     * @deprecated Use deriveServerFingerprint()
     */
    public function extractFingerprint(Request $request): ?string
    {
        return $this->deriveServerFingerprint($request);
    }

    public function generateFingerprint(Request $request): string
    {
        return $this->deriveServerFingerprint($request);
    }

    // ── UA detection helpers ──────────────────────────────────────────────────

    private function detectDeviceName(Request $request): string
    {
        $os   = $this->detectOs($request);
        $type = $this->detectDeviceType($request);
        return ucfirst($type) . ' — ' . $os;
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
        if (str_contains($ua, 'Edg'))     return 'Edge';
        if (str_contains($ua, 'Chrome'))  return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari'))  return 'Safari';
        return 'Unknown';
    }
}