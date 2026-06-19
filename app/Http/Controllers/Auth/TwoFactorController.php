<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\DeviceBindingService;
use App\Services\TwoFactorAuthService;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService,
        protected DeviceBindingService $deviceService,
    ) {}

    public function show(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactor', [
            'expiresAt' => $request->session()->get('2fa_expires_at'),
            'status'    => $request->session()->get('status'),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return back()->withErrors(['code' => 'Session expired. Please log in again.']);
        }

        $user = User::find($userId);
        if (!$user) {
            return back()->withErrors(['code' => 'User not found. Please log in again.']);
        }

        // Check code expiry
        $expiresAt = $request->session()->get('2fa_expires_at');
        if (!$expiresAt || now()->timestamp > $expiresAt) {
            $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);
            return back()->withErrors(['code' => 'Verification code has expired. Please request a new code.']);
        }

        // Verify the OTP code
        if (!$this->twoFactorService->verifyCode($user, $request->code)) {
            AuditLog::record(
                action: 'auth.two_factor.failed',
                event: 'failure',
                module: 'Authentication',
                auditable: $user,
                extra: ['outcome' => 'failure', 'failure_reason' => 'Invalid verification code']
            );
            return back()->withErrors(['code' => 'Invalid verification code. Please try again.']);
        }

        // ── OTP is valid — now handle device binding ──────────────────────────

        $role = $user->getRoleNames()->first();

        if (Device::roleRequiresBinding($role)) {
            $deviceStatus = $this->resolveDeviceStatus($user, $request);

            switch ($deviceStatus) {
                case 'mismatch':
                    // Hard mismatch against a known modern fingerprint — block login
                    AuditLog::record(
                        action: 'auth.login.device_blocked',
                        event: 'blocked',
                        module: 'Authentication',
                        auditable: $user,
                        extra: [
                            'outcome'        => 'blocked',
                            'failure_reason' => 'Device mismatch — account bound to a different device',
                            'ip'             => $request->ip(),
                        ]
                    );

                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

                    return redirect()->route('login')->withErrors([
                        'email' => 'This account is already registered to another device. Please contact the IEC Administrator for assistance.',
                    ]);

                case 'revoked':
                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);
                    return redirect()->route('login')->withErrors([
                        'email' => 'Your device access has been revoked. Please contact the IEC Administrator.',
                    ]);

                case 'no_device_bound':
                case 'legacy_device':
                    // First login or legacy UUID fingerprint — silently register current device
                    try {
                        $this->deviceService->registerDeviceSilently($user, $request);
                        Log::info('[Auth] Device registered silently', [
                            'user_id' => $user->id,
                            'reason'  => $deviceStatus,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('[Auth] Silent device registration failed', [
                            'user_id' => $user->id,
                            'error'   => $e->getMessage(),
                        ]);
                        // Do NOT block login if registration fails
                    }
                    break;

                case 'trusted':
                    // Device already registered and matches — nothing to do
                    break;

                case 'not_required':
                    // Role doesn't need device binding
                    break;
            }
        }

        // ── All checks passed — log the user in ──────────────────────────────

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

        AuditLog::record(
            action: 'auth.login.success',
            event: 'action',
            module: 'Authentication',
            auditable: $user,
            extra: ['outcome' => 'success', 'ip' => $request->ip()]
        );

        return $this->redirectByRole($user);
    }

    public function resend(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $code      = $this->twoFactorService->generateCode($user);
        $this->twoFactorService->sendCode($user, $code);

        $expiresAt = now()->addMinutes(10);
        $request->session()->put('2fa_sms_sent', true);
        $request->session()->put('2fa_expires_at', $expiresAt->timestamp);

        return back()->with('status', 'A new verification code has been sent to your phone.');
    }

    /**
     * Resolve device status, treating legacy UUID-based fingerprints
     * (stored from the old cookie system) as needing re-registration
     * rather than a hard mismatch block.
     */
    protected function resolveDeviceStatus(User $user, Request $request): string
    {
        $serverFingerprint = $this->deviceService->deriveServerFingerprint($request);

        $device = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->first();

        if (!$device) {
            $this->deviceService->storePendingFingerprintPublic($user, $request, $serverFingerprint);
            return 'no_device_bound';
        }

        if ($device->is_revoked) {
            return 'revoked';
        }

        // Exact match — trusted
        if (hash_equals($device->device_fingerprint, $serverFingerprint)) {
            $device->recordUsage($request->ip());
            return 'trusted';
        }

        // Check if the stored fingerprint looks like a legacy UUID
        // (old cookie-based: "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" or "iec-xxxx-xxxx")
        if ($this->isLegacyFingerprint($device->device_fingerprint)) {
            Log::info('[Auth] Legacy device fingerprint detected — re-registering', [
                'user_id'   => $user->id,
                'device_id' => $device->id,
                'stored'    => substr($device->device_fingerprint, 0, 16) . '...',
            ]);
            return 'legacy_device';
        }

        // Modern fingerprint but different device
        return 'mismatch';
    }

    /**
     * Detect whether a stored fingerprint is from the old cookie-based system.
     * Old fingerprints were UUIDs (36 chars with dashes) or "iec-" prefixed random strings.
     * New fingerprints are SHA-256 hashes (64 hex chars).
     */
    protected function isLegacyFingerprint(string $fingerprint): bool
    {
        // UUID format: 8-4-4-4-12
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fingerprint)) {
            return true;
        }

        // Old "iec-" prefixed random strings
        if (str_starts_with($fingerprint, 'iec-')) {
            return true;
        }

        // SHA-256 hashes are exactly 64 hex characters — if it's NOT that, treat as legacy
        if (!preg_match('/^[0-9a-f]{64}$/', $fingerprint)) {
            return true;
        }

        return false;
    }

    protected function redirectByRole(User $user): \Illuminate\Http\RedirectResponse
    {
        $role = $user->getRoleNames()->first();

        return match ($role) {
            'polling-officer'       => redirect()->route('officer.dashboard'),
            'ward-approver'         => redirect()->route('ward.dashboard'),
            'constituency-approver' => redirect()->route('constituency.dashboard'),
            'admin-area-approver'   => redirect()->route('admin-area.dashboard'),
            'iec-chairman'          => redirect()->route('chairman.dashboard'),
            'iec-administrator'     => redirect()->route('admin.dashboard'),
            'party-representative'  => redirect()->route('party.dashboard'),
            'election-monitor'      => redirect()->route('monitor.dashboard'),
            default                 => redirect('/'),
        };
    }
}