<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DeviceBindingService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthService $twoFactorService,
        private readonly DeviceBindingService $deviceService,
    ) {}

    public function show(Request $request): Response|JsonResponse|RedirectResponse
    {
        if (!$request->session()->has('auth.pending_user_id')) {
            return redirect()->route('auth.login');
        }

        $user = User::find($request->session()->get('auth.pending_user_id'));

        return Inertia::render('Auth/TwoFactor', [
            'phone_hint' => $this->maskPhone($user?->phone),
            'is_locked_out' => $this->twoFactorService->isLockedOut($user),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('auth.pending_user_id');
        if (!$userId) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 401);
        }

        $user = User::findOrFail($userId);

        if ($this->twoFactorService->isLockedOut($user)) {
            $seconds = $this->twoFactorService->getLockoutSecondsRemaining($user);
            return response()->json([
                'message' => "Too many failed attempts. Try again in {$seconds} seconds.",
                'locked_out' => true,
            ], 429);
        }

        $verified = $user->two_factor_secret
            ? $this->twoFactorService->verifyTotpCode($user, $request->code)
            : $this->twoFactorService->verifySmsOtp($user, $request->code);

        if (!$verified) {
            return response()->json([
                'message' => 'Invalid verification code.',
                'errors' => ['code' => ['The verification code is incorrect.']],
            ], 422);
        }

        $deviceStatus = $this->deviceService->checkDevice($user, $request);

        if ($deviceStatus === 'pending_registration') {
            $request->session()->put('auth.device_registration_pending', true);
            return response()->json([
                'status' => 'device_registration_required',
                'message' => 'This device is not registered. Please register it to continue.',
            ]);
        }

        if ($deviceStatus === 'revoked') {
            return response()->json([
                'message' => 'This device has been revoked. Contact IEC Administrator.',
            ], 403);
        }

        return $this->issueTokenAndRedirect($user, $request);
    }

    // public function showDeviceRegistration(Request $request): Response|JsonResponse
    //  it was like above but was having error on redirect() method{
    public function showDeviceRegistration(Request $request): Response|JsonResponse|RedirectResponse
    {
        if (!$request->session()->has('auth.pending_user_id')
            || !$request->session()->has('auth.device_registration_pending')) {
            return redirect()->route('auth.login');
        }

        $pending = $this->deviceService->getPendingDevice(
            User::find($request->session()->get('auth.pending_user_id'))
        );

        return Inertia::render('Auth/DeviceVerification', [
            'device_info' => [
                'os' => $pending['os'] ?? 'Unknown',
                'browser' => $pending['browser'] ?? 'Unknown',
                'type' => $pending['type'] ?? 'Unknown',
                'ip' => $pending['ip'] ?? 'Unknown',
            ],
        ]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $userId = $request->session()->get('auth.pending_user_id');
        if (!$userId || !$request->session()->has('auth.device_registration_pending')) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 401);
        }

        $user = User::findOrFail($userId);

        $this->deviceService->registerDevice($user, $request, $request->device_name);

        $request->session()->forget('auth.device_registration_pending');

        return $this->issueTokenAndRedirect($user, $request);
    }

    public function resend(Request $request): JsonResponse
    {
        $userId = $request->session()->get('auth.pending_user_id');
        if (!$userId) {
            return response()->json(['message' => 'Session expired.'], 401);
        }

        $user = User::findOrFail($userId);

        if ($this->twoFactorService->isLockedOut($user)) {
            return response()->json(['message' => 'Account is locked. Please wait.'], 429);
        }

        $sent = $this->twoFactorService->sendSmsOtp($user);

        return response()->json([
            'status' => $sent ? 'sent' : 'failed',
            'message' => $sent ? 'New code sent.' : 'Failed to send code. Try again.',
        ]);
    }

    private function issueTokenAndRedirect(User $user, Request $request): JsonResponse
    {
        $request->session()->forget([
            'auth.pending_user_id',
            'auth.pending_email',
            'auth.device_registration_pending',
        ]);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $role = $user->getRoleNames()->first();
        $tokenName = "iec_session_{$role}_" . now()->timestamp;
        $token = $user->createToken($tokenName);

        AuditLog::record(
            action: 'auth.login.success',
            event: 'created',
            module: 'Authentication',
            extra: [
                'outcome' => 'success',
                'user_role' => $role,
            ]
        );

        return response()->json([
            'status' => 'authenticated',
            'redirect_url' => $this->getRoleDashboard($role),
            'role' => $role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
            ],
        ])->cookie(
            DeviceBindingService::DEVICE_COOKIE_NAME,
            $request->cookie(DeviceBindingService::DEVICE_COOKIE_NAME),
            DeviceBindingService::DEVICE_COOKIE_TTL * 24 * 60,
            '/',
            null,
            true,
            true
        );
    }

    private function getRoleDashboard(string $role): string
    {
        return match ($role) {
            'polling-officer' => '/officer/dashboard',
            'ward-approver' => '/ward/dashboard',
            'constituency-approver' => '/constituency/dashboard',
            'admin-area-approver' => '/admin-area/dashboard',
            'iec-chairman' => '/chairman/dashboard',
            'iec-administrator' => '/admin/dashboard',
            'party-representative' => '/party/dashboard',
            'election-monitor' => '/monitor/dashboard',
            default => '/dashboard',
        };
    }

    private function maskPhone(?string $phone): string
    {
        if (!$phone || strlen($phone) < 6) return '***';
        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
    }
}
