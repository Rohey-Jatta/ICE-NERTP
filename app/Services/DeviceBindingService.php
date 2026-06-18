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
     * Minimum similarity threshold for device fingerprints (as percentage).
     * 90% match required to consider a device as trusted.
     */
    const FINGERPRINT_MATCH_THRESHOLD = 90;

    /**
     * Derive a stable server-side fingerprint from request signals.
     * Uses User-Agent + Accept-Language only.
     *
     * IMPORTANT: We intentionally do NOT include the client IP address in
     * this fingerprint. IP is not a stable "same device" signal:
     *   - On localhost/dev, "localhost" can resolve to either ::1 or
     *     127.0.0.1 on different connections (browser dual-stack/Happy
     *     Eyeballs racing), which previously caused the fingerprint to
     *     change between requests from the SAME browser tab — permanently
     *     locking users out with a false "device mismatch" right after a
     *     successful 2FA code.
     *   - In production, polling officers on mobile data can have their
     *     IP change between cell towers / NAT pools mid-session, which
     *     would incorrectly flag a legitimate device as mismatched.
     * Device binding is meant to bind to the physical device, not
     * to whatever network it happens to be using at that moment.
     */
    public function deriveServerFingerprint(Request $request): string
    {
        $ua   = $request->userAgent() ?? 'unknown';
        $lang = $request->header('Accept-Language', 'unknown');

        return hash('sha256', $ua . '|' . $lang);
    }

    /**
     * Extract and parse client-side device fingerprint from request.
     * The client sends a JSON-encoded fingerprint object containing:
     * - os, platform, deviceType, cpuCores, deviceMemory
     * - screen (resolution, color depth, pixel ratio)
     * - timezone (offset, name)
     * - language (lang, languages array)
     * - touch capabilities
     * - canvas and WebGL fingerprint hashes
     */
    private function parseClientFingerprint(Request $request): ?array
    {
        $jsonFp = $request->input('deviceFingerprint');
        if (!$jsonFp) {
            return null;
        }

        try {
            $fp = json_decode($jsonFp, true);
            if (is_array($fp)) {
                return $fp;
            }
        } catch (\Throwable $e) {
            Log::warning('[DeviceBinding] Failed to parse client fingerprint', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Combine server-side and client-side fingerprints into one.
     * Returns the combined fingerprint data that will be stored.
     */
    private function combineFingerprints(Request $request, ?array $clientFp): array
    {
        $serverFp = $this->deriveServerFingerprint($request);

        $combined = [
            'serverFingerprint' => $serverFp,
            'clientFingerprint' => $clientFp ?? [],
            'collectedAt'       => now()->toIso8601String(),
        ];

        return $combined;
    }

    /**
     * Calculate similarity score between two fingerprints.
     * Returns a score 0-100 representing percentage match.
     *
     * Comparison logic:
     *   - Server fingerprint (UA + language) must match exactly (high priority)
     *   - Client fingerprint components are scored individually:
     *     * OS, platform, device type (high priority, must match)
     *     * Screen resolution (medium priority, small changes acceptable)
     *     * Timezone, language (low priority, can change)
     *     * Canvas/WebGL hashes (medium priority)
     */
    private function calculateSimilarity(array $storedFp, array $currentFp): int
    {
        // Default if stored fingerprint is incomplete
        if (empty($storedFp['serverFingerprint'])) {
            return 0;
        }

        $score = 0;
        $maxScore = 0;

        // ── Server fingerprint (40 points max) ──────────────────────────────────
        $maxScore += 40;

        $serverMatch = isset($currentFp['serverFingerprint']) &&
            hash_equals($storedFp['serverFingerprint'], $currentFp['serverFingerprint']);

        if ($serverMatch) {
            $score += 40;
        }

        // If the stored record has no client fingerprint data, use the
        // server fingerprint as the primary trust signal.
        $storedClient = $storedFp['clientFingerprint'] ?? [];
        $currentClient = $currentFp['clientFingerprint'] ?? [];
        if ($serverMatch && (empty($storedClient) || empty($currentClient))) {
            return 100;
        }

        // ── Client fingerprint components (60 points max) ──────────────────────

        // OS, Platform, Device Type (30 points max - must match strongly)
        $maxScore += 30;
        if (!empty($storedClient['os']) && !empty($currentClient['os'])) {
            if ($storedClient['os'] === $currentClient['os']) {
                $score += 15;
            } elseif ($this->osCompatible($storedClient['os'], $currentClient['os'])) {
                $score += 8;
            }
        }

        if (!empty($storedClient['platform']) && !empty($currentClient['platform'])) {
            if ($storedClient['platform'] === $currentClient['platform']) {
                $score += 8;
            }
        }

        if (!empty($storedClient['deviceType']) && !empty($currentClient['deviceType'])) {
            if ($storedClient['deviceType'] === $currentClient['deviceType']) {
                $score += 7;
            }
        }

        // Screen resolution (10 points max - minor changes acceptable)
        $maxScore += 10;
        $screenMatch = $this->compareScreenResolution(
            $storedClient['screen'] ?? [],
            $currentClient['screen'] ?? []
        );
        $score += (int) ($screenMatch * 10);

        // Canvas and WebGL hashes (10 points max - hardware rendering)
        $maxScore += 10;
        if (!empty($storedClient['canvasFingerprint']) &&
            !empty($currentClient['canvasFingerprint'])) {
            if ($storedClient['canvasFingerprint'] === $currentClient['canvasFingerprint']) {
                $score += 5;
            }
        }

        if (!empty($storedClient['webglFingerprint']) &&
            !empty($currentClient['webglFingerprint'])) {
            if ($storedClient['webglFingerprint'] === $currentClient['webglFingerprint']) {
                $score += 5;
            }
        }

        // Timezone and language (10 points max - low priority, can change)
        $maxScore += 10;
        if (!empty($storedClient['timezone']['timezone']) &&
            !empty($currentClient['timezone']['timezone'])) {
            if ($storedClient['timezone']['timezone'] === $currentClient['timezone']['timezone']) {
                $score += 5;
            }
        }

        if (!empty($storedClient['language']['lang']) &&
            !empty($currentClient['language']['lang'])) {
            if ($storedClient['language']['lang'] === $currentClient['language']['lang']) {
                $score += 5;
            }
        }

        // Calculate percentage
        if ($maxScore === 0) {
            return 0;
        }

        $percentage = (int) (($score / $maxScore) * 100);
        return min(100, max(0, $percentage));
    }

    /**
     * Check if two OS values are compatible (e.g., Windows on different versions)
     */
    private function osCompatible(string $stored, string $current): bool
    {
        // Exact match is best
        if ($stored === $current) {
            return true;
        }

        // Allow minor OS version differences (e.g., "Windows 10" vs "Windows")
        $storedBase = explode(' ', $stored)[0];
        $currentBase = explode(' ', $current)[0];

        return $storedBase === $currentBase;
    }

    /**
     * Compare screen resolutions with tolerance for minor changes.
     * Returns a score 0-1.0.
     */
    private function compareScreenResolution(array $stored, array $current): float
    {
        if (empty($stored) || empty($current)) {
            return 0.5; // Neutral score if missing
        }

        $storedW = $stored['width'] ?? 0;
        $storedH = $stored['height'] ?? 0;
        $currentW = $current['width'] ?? 0;
        $currentH = $current['height'] ?? 0;

        // Exact match
        if ($storedW === $currentW && $storedH === $currentH) {
            return 1.0;
        }

        // Minor changes (within 1% variance) still accepted
        $widthVariance = abs($storedW - $currentW) / max(1, $storedW);
        $heightVariance = abs($storedH - $currentH) / max(1, $storedH);

        if ($widthVariance < 0.01 && $heightVariance < 0.01) {
            return 0.95;
        }

        // Color depth match is also important
        $colorDepthMatch = ($stored['colorDepth'] ?? 0) === ($current['colorDepth'] ?? 0);
        if ($colorDepthMatch && $widthVariance < 0.05) {
            return 0.7;
        }

        return 0.3;
    }

    /**
     * Check whether the current device is allowed for this user.
     *
     * Returns:
     *   'not_required'       — role doesn't need device binding
     *   'no_device_bound'    — first login, no device registered yet
     *   'trusted'            — fingerprint matches above threshold
     *   'mismatch'           — fingerprint doesn't match registered device
     *   'revoked'            — device was revoked
     */
    public function checkDevice(User $user, Request $request): string
    {
        $userRole = $user->getRoleNames()->first();

        if (!Device::roleRequiresBinding($userRole)) {
            return 'not_required';
        }

        $clientFp = $this->parseClientFingerprint($request);
        $currentFpData = $this->combineFingerprints($request, $clientFp);

        // Find any active (non-revoked) device for this user
        $device = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->first();

        if (!$device) {
            // Store pending fingerprint so registration can use it
            $this->storePendingFingerprint($user, $request, $currentFpData);
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

        // If device has no comprehensive fingerprint data (legacy device),
        // fall back to the old exact-hash comparison so previously-registered
        // devices continue to work. If the client supplied a fingerprint,
        // store it to upgrade the device record silently for future checks.
        $storedFpJson = $device->device_fingerprint_data;
        $storedFpData = null;
        if (!empty($storedFpJson)) {
            $storedFpData = json_decode($storedFpJson, true);
        }

        if (empty($storedFpData)) {
            $currentServerFingerprint = $currentFpData['serverFingerprint'] ?? $this->deriveServerFingerprint($request);

            if (!empty($device->device_fingerprint) && hash_equals($device->device_fingerprint, $currentServerFingerprint)) {
                if (!empty($clientFp)) {
                    $upgradeData = $this->combineFingerprints($request, $clientFp);
                    try {
                        $device->update(['device_fingerprint_data' => json_encode($upgradeData)]);
                    } catch (\Throwable $e) {
                        Log::warning('[DeviceBinding] Failed to upgrade legacy device fingerprint_data', ['device_id' => $device->id, 'error' => $e->getMessage()]);
                    }
                }

                $device->recordUsage($request->ip());

                AuditLog::record(
                    action: 'auth.device.validated',
                    event: 'updated',
                    module: 'Authentication',
                    auditable: $device,
                    extra: ['outcome' => 'success', 'ip' => $request->ip(), 'legacy_fallback' => true]
                );

                return 'trusted';
            }

            Log::warning('[DeviceBinding] Legacy device mismatch', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'stored_device_fingerprint' => $device->device_fingerprint,
                'current_server_fingerprint' => $currentServerFingerprint,
            ]);

            $similarity = 0;
        } else {
            // Compare fingerprints using similarity-based matching
            $similarity = $this->calculateSimilarity($storedFpData, $currentFpData);
        }

        Log::info('[DeviceBinding] Fingerprint similarity check', [
            'user_id'    => $user->id,
            'device_id'  => $device->id,
            'similarity' => $similarity . '%',
            'threshold'  => self::FINGERPRINT_MATCH_THRESHOLD . '%',
        ]);

        if ($similarity >= self::FINGERPRINT_MATCH_THRESHOLD) {
            $device->recordUsage($request->ip());

            AuditLog::record(
                action: 'auth.device.validated',
                event: 'updated',
                module: 'Authentication',
                auditable: $device,
                extra: [
                    'outcome' => 'success',
                    'ip' => $request->ip(),
                    'similarity' => $similarity,
                ]
            );

            return 'trusted';
        }

        // Fingerprint similarity below threshold — different device
        AuditLog::record(
            action: 'auth.device.mismatch',
            event: 'blocked',
            module: 'Authentication',
            extra: [
                'outcome'        => 'blocked',
                'failure_reason' => "Device fingerprint mismatch (similarity: {$similarity}%)",
                'user_id'        => $user->id,
                'stored_device'  => $device->id,
                'ip'             => $request->ip(),
                'similarity'     => $similarity,
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
        $clientFp = $this->parseClientFingerprint($request);
        $fpData = $this->combineFingerprints($request, $clientFp);

        // If there's already an active device, revoke it first (admin reset scenario)
        Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->update([
                'is_revoked' => true,
                'revoked_at' => now(),
                'is_trusted' => false,
            ]);

        // For backward compatibility, keep the legacy device_fingerprint as the
        // server-side fingerprint hash used by the old binding flow.
        $fingerprintHash = $fpData['serverFingerprint'];

        $device = Device::create([
            'user_id'                   => $user->id,
            'device_fingerprint'        => $fingerprintHash,
            'device_fingerprint_data'   => json_encode($fpData),
            'device_name'               => $this->detectDeviceName($clientFp, $request),
            'device_type'               => $clientFp['deviceType'] ?? $this->detectDeviceType($request),
            'os'                        => $clientFp['os'] ?? $this->detectOs($request),
            'browser'                   => $this->detectBrowser($request),
            'verified_at'               => now(),
            'verified_by_ip'            => $request->ip(),
            'last_used_at'              => now(),
            'last_used_ip'              => $request->ip(),
            'is_trusted'                => true,
            'is_revoked'                => false,
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
                'client_fingerprint' => !empty($clientFp),
            ]
        );

        Log::info('[DeviceBinding] Device registered silently', [
            'user_id'   => $user->id,
            'device_id' => $device->id,
            'os'        => $device->os,
            'with_client_fp' => !empty($clientFp),
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

    private function storePendingFingerprint(User $user, Request $request, array $fpData): void
    {
        Cache::put(
            'pending_device_' . $user->id,
            [
                'fingerprint_data' => $fpData,
                'ip'               => $request->ip(),
                'os'               => $fpData['clientFingerprint']['os'] ?? $this->detectOs($request),
                'browser'          => $this->detectBrowser($request),
                'type'             => $fpData['clientFingerprint']['deviceType'] ?? $this->detectDeviceType($request),
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
     * @deprecated Use registerDeviceSilently() with client fingerprint instead.
     */
    public function extractFingerprint(Request $request): ?string
    {
        return $this->deriveServerFingerprint($request);
    }

    public function generateFingerprint(Request $request): string
    {
        return $this->deriveServerFingerprint($request);
    }

    private function detectDeviceName(?array $clientFp, Request $request): string
    {
        if ($clientFp) {
            $os = $clientFp['os'] ?? 'Unknown';
            $type = ucfirst($clientFp['deviceType'] ?? 'device');
            return "{$type} – {$os}";
        }

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
