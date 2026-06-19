<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\DeviceBindingService;
use App\Services\TwoFactorAuthService;
use App\Models\AuditLog;
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

        if (\App\Models\Device::roleRequiresBinding($role)) {
            $deviceStatus = $this->deviceService->checkDevice($user, $request);

            switch ($deviceStatus) {
                case 'mismatch':
                    // Different device — block login entirely
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

                    // Clear 2FA session — do not log the user in
                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

                    $mismatchMessage = 'This account is already registered to another device. Please contact the IEC Administrator for assistance.';

                    // Flashed two ways on purpose: `errors.email` for pages that
                    // read Inertia's shared error bag, and `flash.error` (shared
                    // globally by HandleInertiaRequests) for pages/layouts that
                    // render a generic flash banner instead. Without at least
                    // one of these actually being rendered by the Login page,
                    // the user sees nothing — if that's still the case, the
                    // Login page itself needs to display `flash.error`.
                    return redirect()->route('login')
                        ->withErrors(['email' => $mismatchMessage])
                        ->with('error', $mismatchMessage);

                case 'revoked':
                    $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

                    $revokedMessage = 'Your device access has been revoked. Please contact the IEC Administrator.';

                    return redirect()->route('login')
                        ->withErrors(['email' => $revokedMessage])
                        ->with('error', $revokedMessage);

                case 'no_device_bound':
                    // First login — silently register the current device
                    try {
                        $this->deviceService->registerDeviceSilently($user, $request);
                        Log::info('[Auth] Device registered silently on first login', ['user_id' => $user->id]);
                    } catch (\Throwable $e) {
                        Log::error('[Auth] Silent device registration failed', [
                            'user_id' => $user->id,
                            'error'   => $e->getMessage(),
                        ]);
                        // Do NOT block login if registration fails — log it and continue
                    }
                    break;

                case 'trusted':
                    // Device already registered and matches — nothing to do
                    break;

                case 'not_required':
                    // Role doesn't need device binding (party-representative, election-monitor)
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