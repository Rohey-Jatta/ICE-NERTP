<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\DeviceBindingService;
use App\Services\TwoFactorAuthService;
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
        protected AuditTrailService    $auditTrail,
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
        ], [
            'code.required' => 'Please enter the 6-digit verification code.',
            'code.size'     => 'The verification code must be exactly 6 digits.',
        ]);

        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your session has expired. Please log in again.']);
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

            $this->auditTrail->authEvent(
                action: 'auth.two_factor.code_expired',
                description: sprintf(
                    '2FA verification code EXPIRED for user %s (ID:%d) at %s from IP %s.',
                    $user->name, $user->id, now()->toDateTimeString(), $request->ip()
                ),
                extra: ['event' => 'failure', 'outcome' => 'failure', 'failure_reason' => 'OTP expired']
            );

            return back()->withErrors(['code' => 'Your verification code has expired. Please request a new code below.']);
        }

        // Verify the OTP code
        if (!$this->twoFactorService->verifyCode($user, $request->code)) {
            $this->auditTrail->authEvent(
                action: 'auth.two_factor.failed',
                description: sprintf(
                    '2FA verification FAILED for user %s (ID:%d, role:%s) at %s from IP %s — incorrect OTP entered.',
                    $user->name,
                    $user->id,
                    $user->getRoleNames()->first() ?? 'unassigned',
                    now()->toDateTimeString(),
                    $request->ip()
                ),
                extra: ['event' => 'failure', 'outcome' => 'failure', 'failure_reason' => 'Invalid OTP code']
            );
            return back()->withErrors(['code' => 'That code is incorrect. Please check the code sent to your phone and try again.']);
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
                    $this->auditTrail->authEvent(
                        action: 'auth.device.mismatch_blocked',
                        description: sprintf(
                            'Login BLOCKED for user %s (ID:%d, role:%s) at %s from IP %s — '
                            . 'device fingerprint does not match registered device. '
                            . 'Account is bound to a different device.',
                            $user->name, $user->id, $role,
                            now()->toDateTimeString(), $request->ip()
                        ),
                        extra: [
                            'event'          => 'blocked',
                            'outcome'        => 'blocked',
                            'failure_reason' => 'Device fingerprint mismatch',
                        ]
                    );
                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);
                    return redirect()->route('login')->with('device_error',
                        'This account is already active on another registered device. Please contact the IEC Administrator for help.'
                    );

                case 'revoked':
                    $this->auditTrail->authEvent(
                        action: 'auth.device.revoked_blocked',
                        description: sprintf(
                            'Login BLOCKED for user %s (ID:%d) — device has been revoked. IP: %s.',
                            $user->name, $user->id, $request->ip()
                        ),
                        extra: [
                            'event'          => 'blocked',
                            'outcome'        => 'blocked',
                            'failure_reason' => 'Device revoked',
                        ]
                    );
                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);
                    return redirect()->route('login')->with('device_error',
                        'Your device access has been revoked. Please contact the IEC Administrator for help.'
                    );

                case 'no_device_bound':
                case 'legacy_device':
                    try {
                        $device = $this->deviceService->registerDeviceSilently($user, $request);
                        $this->auditTrail->authEvent(
                            action: 'auth.device.registered',
                            description: sprintf(
                                'Device automatically registered and bound for user %s (ID:%d, role:%s) '
                                . 'at %s from IP %s. Device: %s (%s / %s). '
                                . 'Reason: %s.',
                                $user->name, $user->id, $role,
                                now()->toDateTimeString(), $request->ip(),
                                $device->device_name, $device->os, $device->browser,
                                $deviceStatus === 'legacy_device' ? 'legacy fingerprint replaced' : 'first device registration'
                            ),
                            extra: [
                                'event'   => 'created',
                                'outcome' => 'success',
                            ]
                        );
                    } catch (\Throwable $e) {
                        Log::error('[Auth] Silent device registration failed', [
                            'user_id' => $user->id,
                            'error'   => $e->getMessage(),
                        ]);
                    }
                    break;

                case 'trusted':
                    $this->auditTrail->authEvent(
                        action: 'auth.device.validated',
                        description: sprintf(
                            'Registered device validated for user %s (ID:%d) at %s from IP %s.',
                            $user->name, $user->id,
                            now()->toDateTimeString(), $request->ip()
                        ),
                        extra: ['event' => 'action', 'outcome' => 'success']
                    );
                    break;

                case 'not_required':
                    break;
            }
        }

        // ── All checks passed — log the user in ──────────────────────────────
        try {
            Auth::login($user, false);
            $request->session()->regenerate();
            $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            $this->auditTrail->authEvent(
                action: 'auth.login.success',
                description: sprintf(
                    'User %s (ID:%d, role:%s) successfully authenticated via 2FA '
                    . 'and logged in at %s from IP %s.',
                    $user->name,
                    $user->id,
                    $user->getRoleNames()->first() ?? 'unassigned',
                    now()->toDateTimeString(),
                    $request->ip()
                ),
                extra: ['event' => 'action', 'outcome' => 'success']
            );

            $request->session()->save();

            Log::info('[Auth] Login successful, redirecting', [
                'user_id'    => $user->id,
                'role'       => $role ?? $user->getRoleNames()->first(),
                'auth_check' => Auth::check(),
            ]);

        } catch (\Throwable $e) {
            Log::error('[Auth] Login failed after 2FA verify', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return back()->withErrors(['code' => 'An unexpected error occurred completing your login. Please try again, or contact the IEC Administrator if this keeps happening.']);
        }

        return $this->redirectByRole($user);
    }

    public function resend(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your session has expired. Please log in again.']);
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'User not found. Please log in again.']);
        }

        $code      = $this->twoFactorService->generateCode($user);
        $this->twoFactorService->sendCode($user, $code);

        $expiresAt = now()->addMinutes(10);
        $request->session()->put('2fa_sms_sent', true);
        $request->session()->put('2fa_expires_at', $expiresAt->timestamp);
        $request->session()->save();

        $this->auditTrail->authEvent(
            action: 'auth.two_factor.code_resent',
            description: sprintf(
                'User %s (ID:%d) requested a new 2FA code from IP %s at %s.',
                $user->name, $user->id, $request->ip(), now()->toDateTimeString()
            ),
            extra: ['event' => 'action', 'outcome' => 'success']
        );

        return back()->with('status', 'A new verification code has been sent to your phone.');
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers (unchanged from original)
    // ─────────────────────────────────────────────────────────────────

    protected function resolveDeviceStatus(User $user, Request $request): string
    {
        $role = $user->getRoleNames()->first();

        if (!Device::roleRequiresBinding($role)) {
            return 'not_required';
        }

        $serverFingerprint = $this->deviceService->deriveServerFingerprint($request);

        $device = Device::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->whereNotNull('verified_at')
            ->latest('last_used_at')
            ->first();

        if (!$device) {
            $this->deviceService->storePendingFingerprintPublic($user, $request, $serverFingerprint);
            return 'no_device_bound';
        }

        if ($device->is_revoked) {
            return 'revoked';
        }

        if (hash_equals($device->device_fingerprint, $serverFingerprint)) {
            $device->recordUsage($request->ip());
            return 'trusted';
        }

        if ($this->isLegacyFingerprint($device->device_fingerprint)) {
            Log::info('[Auth] Legacy device fingerprint — will re-register', [
                'user_id'   => $user->id,
                'device_id' => $device->id,
                'stored_len'=> strlen($device->device_fingerprint),
            ]);
            return 'legacy_device';
        }

        return 'mismatch';
    }

    protected function isLegacyFingerprint(string $fingerprint): bool
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fingerprint)) {
            return true;
        }
        if (str_starts_with($fingerprint, 'iec-')) {
            return true;
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $fingerprint)) {
            return true;
        }
        return false;
    }

    protected function redirectByRole(User $user): \Illuminate\Http\RedirectResponse
    {
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