<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DeviceBindingService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthService $twoFactorService,
        private readonly DeviceBindingService $deviceService,
    ) {}

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => false,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $rateLimitKey = 'login_attempts_' . $request->ip();
        if (Cache::get($rateLimitKey, 0) >= 5) {
            AuditLog::record(
                action: 'auth.login.rate_limited',
                event: 'blocked',
                module: 'Authentication',
                extra: ['outcome' => 'blocked', 'failure_reason' => 'Rate limit exceeded']
            );
            throw ValidationException::withMessages([
                'email' => ['Too many login attempts. Please wait 2 minutes.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Cache::increment($rateLimitKey);
            Cache::put($rateLimitKey, Cache::get($rateLimitKey, 0), now()->addMinutes(2));

            AuditLog::record(
                action: 'auth.login.failed',
                event: 'failure',
                module: 'Authentication',
                extra: ['outcome' => 'failure', 'failure_reason' => 'Invalid credentials']
            );

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status !== 'active') {
            AuditLog::record(
                action: 'auth.login.inactive_account',
                event: 'blocked',
                module: 'Authentication',
                extra: ['outcome' => 'blocked', 'failure_reason' => "Account status: {$user->status}"]
            );
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Contact IEC Administrator.'],
            ]);
        }

        $request->session()->put('auth.pending_user_id', $user->id);
        $request->session()->put('auth.pending_email', $user->email);
        Cache::forget($rateLimitKey);

        $this->twoFactorService->sendSmsOtp($user);

        return response()->json([
            'status' => 'two_factor_required',
            'message' => 'Verification code sent to your registered phone.',
            'phone_hint' => $this->maskPhone($user->phone),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $request->user()->currentAccessToken()->delete();

            AuditLog::record(
                action: 'auth.logout',
                event: 'deleted',
                module: 'Authentication',
                extra: ['outcome' => 'success']
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'logged_out']);
    }

    private function maskPhone(?string $phone): string
    {
        if (!$phone || strlen($phone) < 6) return '***';
        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
    }
}
