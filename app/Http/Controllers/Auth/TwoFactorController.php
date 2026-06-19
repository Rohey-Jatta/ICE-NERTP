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
            return redirect()->route('login')
                ->withErrors(['email' => 'Session expired. Please log in again.']);
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'User not found. Please log in again.']);
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

            Log::info('[2FA] Device status resolved', [
                'user_id' => $user->id,
                'role'    => $role,
                'status'  => $deviceStatus,
                'ip'      => $request->ip(),
            ]);

            switch ($deviceStatus) {
                case 'mismatch':
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

                    // Clear 2FA session so they can retry login
                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

                    // Store mismatch error in session so login page can show it
                    return redirect()->route('login')->with('device_error',
                        'This account is registered to a different device. Please contact the IEC Administrator for assistance.'
                    );

                case 'revoked':
                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);
                    return redirect()->route('login')->with('device_error',
                        'Your device access has been revoked. Please contact the IEC Administrator.'
                    );

                case 'no_device_bound':
                case 'legacy_device':
                    // First login or legacy fingerprint — silently register current device
                    try {
                        $this->deviceService->registerDeviceSilently($user, $request);
                        Log::info('[Auth] Device registered silently during 2FA verify', [
                            'user_id' => $user->id,
                            'reason'  => $deviceStatus,
                        ]);
                    } catch (\Throwable $e) {
                        // Non-fatal: log but do not block login
                        Log::error('[Auth] Silent device registration failed', [
                            'user_id' => $user->id,
                            'error'   => $e->getMessage(),
                            'trace'   => $e->getTraceAsString(),
                        ]);
                    }
                    break;

                case 'trusted':
                    // Device matches — nothing extra needed
                    break;

                case 'not_required':
                    // Role doesn't require device binding
                    break;
            }
        }

        // ── All checks passed — log the user in ──────────────────────────────
        try {
            Auth::login($user, false); // false = don't "remember me"

            // Regenerate session to prevent session fixation
            $request->session()->regenerate();

            // Clear 2FA session data
            $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

            // Update last login info
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            AuditLog::record(
                action: 'auth.login.success',
                event: 'action',
                module: 'Authentication',
                auditable: $user,
                extra: ['outcome' => 'success', 'ip' => $request->ip()]
            );

            // Force session save before redirect
            $request->session()->save();

            Log::info('[Auth] Login successful, redirecting', [
                'user_id'  => $user->id,
                'role'     => $role ?? $user->getRoleNames()->first(),
                'auth_check' => Auth::check(),
            ]);

        } catch (\Throwable $e) {
            Log::error('[Auth] Login failed after 2FA verify', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return back()->withErrors(['code' => 'An error occurred completing your login. Please try again.']);
        }

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
        $request->session()->save();

        return back()->with('status', 'A new verification code has been sent to your phone.');
    }

    /**
     * Resolve device binding status for the given user and request.
     *
     * Returns one of:
     *   'not_required'    — role doesn't need device binding
     *   'no_device_bound' — no active device registered yet (first login)
     *   'trusted'         — fingerprint matches registered device
     *   'legacy_device'   — stored fingerprint is from old cookie system, needs re-registration
     *   'mismatch'        — modern fingerprint but different device
     *   'revoked'         — device was revoked
     */
    protected function resolveDeviceStatus(User $user, Request $request): string
    {
        $role = $user->getRoleNames()->first();

        if (!Device::roleRequiresBinding($role)) {
            return 'not_required';
        }

        $serverFingerprint = $this->deviceService->deriveServerFingerprint($request);

        // Find the most recently used non-revoked verified device for this user
        $device = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->latest('last_used_at')
            ->first();

        if (!$device) {
            // No device registered — store pending fingerprint and allow registration
            $this->deviceService->storePendingFingerprintPublic($user, $request, $serverFingerprint);
            return 'no_device_bound';
        }

        if ($device->is_revoked) {
            return 'revoked';
        }

        // Exact match against server-derived fingerprint
        if (hash_equals($device->device_fingerprint, $serverFingerprint)) {
            $device->recordUsage($request->ip());
            return 'trusted';
        }

        // Check if the stored fingerprint is a legacy UUID or "iec-" prefix
        if ($this->isLegacyFingerprint($device->device_fingerprint)) {
            Log::info('[Auth] Legacy device fingerprint — will re-register', [
                'user_id'   => $user->id,
                'device_id' => $device->id,
                'stored_len'=> strlen($device->device_fingerprint),
            ]);
            return 'legacy_device';
        }

        // Modern fingerprint but doesn't match — different device
        return 'mismatch';
    }

    /**
     * Detect whether a stored fingerprint is from the old cookie-based system.
     * Old: UUIDs (36 chars with dashes) or "iec-" prefixed random strings.
     * New: SHA-256 hashes (exactly 64 lowercase hex characters).
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

        // SHA-256 hashes are exactly 64 lowercase hex chars — anything else is legacy
        if (!preg_match('/^[0-9a-f]{64}$/', $fingerprint)) {
            return true;
        }

        return false;
    }

    protected function redirectByRole(User $user): \Illuminate\Http\RedirectResponse
    {
        // Check if user must change password first
        if ($user->must_change_password ?? false) {
            return redirect()->route('password.change');
        }

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