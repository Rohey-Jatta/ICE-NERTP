<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use App\Services\DeviceBindingService;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    protected TwoFactorAuthService $twoFactorService;
    protected DeviceBindingService $deviceService;

    public function __construct(TwoFactorAuthService $twoFactorService, DeviceBindingService $deviceService)
    {
        $this->twoFactorService = $twoFactorService;
        $this->deviceService    = $deviceService;
    }

    public function show(Request $request)
    {
        // Guard: if no pending 2FA session, send back to login
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

        // Verify the code
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

        // Before finalizing login, check device binding requirements.
        $deviceStatus = $this->deviceService->checkDevice($user, $request);

        if ($deviceStatus === 'revoked') {
            AuditLog::record(
                action: 'auth.device.revoked_access_attempt',
                event: 'blocked',
                module: 'Authentication',
                auditable: $user,
                extra: ['outcome' => 'blocked', 'failure_reason' => 'Revoked device attempted login']
            );
            return back()->withErrors(['email' => ['This device has been revoked. Contact IEC Administrator.']]);
        }

        if ($deviceStatus === 'pending_registration') {
            $fingerprint = $this->deviceService->extractFingerprint($request);

            if (!$fingerprint) {
                return back()->withErrors(['email' => ['Device identifier unavailable. Please enable JavaScript and try again.']]);
            }

            if ($user->bound_device_id !== null) {
                AuditLog::record(
                    action: 'auth.device.binding_mismatch',
                    event: 'blocked',
                    module: 'Authentication',
                    auditable: $user,
                    extra: ['bound_device_id' => $user->bound_device_id, 'attempted_device_fingerprint' => $fingerprint]
                );

                return back()->withErrors(['email' => ['This account is already registered to another device and cannot be used on this device.']])->setStatusCode(403);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

            return redirect()->route('device.verify');
        }

        // Complete login and then ensure the user's `bound_device_id` is set for already-registered devices.
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

        $fingerprint = $this->deviceService->extractFingerprint($request);

        if ($fingerprint) {
            $device = Device::where('device_fingerprint', $fingerprint)
                ->where('user_id', $user->id)
                ->first();

            if ($device) {
                if ($user->bound_device_id === null) {
                    $user->bound_device_id = $device->id;
                    $user->save();

                    AuditLog::record(
                        action: 'auth.device.bound',
                        event: 'created',
                        module: 'Authentication',
                        auditable: $user,
                        extra: ['device_id' => $device->id, 'device_fingerprint' => $device->device_fingerprint]
                    );
                } elseif ($user->bound_device_id !== $device->id) {
                    AuditLog::record(
                        action: 'auth.device.binding_mismatch',
                        event: 'blocked',
                        module: 'Authentication',
                        auditable: $user,
                        extra: ['bound_device_id' => $user->bound_device_id, 'attempted_device_fingerprint' => $device->device_fingerprint]
                    );

                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'This account is already registered to another device and cannot be used on this device.'
                        ], 403);
                    }

                    return redirect()->route('login')
                        ->withErrors(['email' => ['This account is already registered to another device and cannot be used on this device.']])
                        ->setStatusCode(403);
                }
            }
        }

        AuditLog::record(
            action: 'auth.login.success',
            event: 'action',
            module: 'Authentication',
            auditable: $user,
            extra: ['outcome' => 'success']
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

        // Generate a fresh code and send it
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
