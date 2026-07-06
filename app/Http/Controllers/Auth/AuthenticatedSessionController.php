<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\TwoFactorAuthService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService,
        protected AuditTrailService    $auditTrail,
    ) {}

    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle login. Session is written BEFORE any SMS attempt.
     * SMS failure is caught — it NEVER blocks the redirect to 2FA page.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Please enter your email address.',
            'email.email'       => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ]);


        try {
            $user = User::where('email', $request->email)->first();

        } catch (\Throwable $e) {

            Log::error('[Auth] Login DB lookup failed', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'email' => 'We were unable to reach the server. Please check your connection and try again.',
            ])->onlyInput('email');
        }



        if (!$user || !Hash::check($request->password, $user->password)) {

            $this->auditTrail->authEvent(
                action: 'auth.login.failed',
                description: sprintf(
                    'Login attempt FAILED for email "%s" from IP %s — invalid credentials.',
                    $request->email,
                    $request->ip()
                ),
                extra: [
                    'event'          => 'failure',
                    'outcome'        => 'failure',
                    'failure_reason' => 'Invalid credentials',
                    'new_values'     => ['email' => $request->email],
                ]
            );


            throw ValidationException::withMessages([
                'email' => ['Invalid email or password. Please try again.'],
            ]);
        }



        if ($user->status !== 'active') {


            $this->auditTrail->authEvent(
                action: 'auth.login.blocked',
                description: sprintf(
                    'Login BLOCKED for user %s (ID:%d) from IP %s — account status is "%s".',
                    $user->name,
                    $user->id,
                    $request->ip(),
                    $user->status
                ),
                extra: [
                    'event'          => 'blocked',
                    'outcome'        => 'blocked',
                    'failure_reason' => "Account status: {$user->status}",
                ]
            );


            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact the IEC Administrator.'],
            ]);
        }



        try {

            // ── Step 1: Generate the code and store EVERYTHING in session FIRST ──

            $code      = $this->twoFactorService->generateCode($user);
            $expiresAt = now()->addMinutes(10);


            $request->session()->put('2fa_user_id',   $user->id);
            $request->session()->put('2fa_sms_sent',   true);
            $request->session()->put('2fa_expires_at', $expiresAt->timestamp);


            // Force session write immediately
            $request->session()->save();


        } catch (\Throwable $e) {


            Log::error('[Auth] Failed to prepare 2FA session', [
                'user_id' => $user->id,
                'error'   => $e->getMessage()
            ]);


            $this->auditTrail->authEvent(
                action: 'auth.login.two_factor_prep_failed',
                description: sprintf(
                    '2FA preparation failed for user %s (ID:%d). Reason: %s',
                    $user->name,
                    $user->id,
                    $e->getMessage()
                ),
                extra: [
                    'event'          => 'failure',
                    'outcome'        => 'failure',
                    'failure_reason' => $e->getMessage(),
                ]
            );


            return back()->withErrors([
                'email' => 'We were unable to start the verification process. Please try again in a moment.',
            ])->onlyInput('email');

        }



        // ── Step 2: Attempt SMS ───────────────────────────────────────────────

        try {


            if (function_exists('set_time_limit')) {
                set_time_limit(15);
            }


            $this->twoFactorService->sendCode($user, $code);


        } catch (\Throwable $e) {


            Log::error('[Auth] 2FA SMS failed during login: ' . $e->getMessage());

            Log::info("[Auth] Fallback 2FA code for {$user->email}: {$code}");



            $this->auditTrail->authEvent(
                action: 'auth.two_factor.sms_dispatch_failed',
                description: sprintf(
                    '2FA SMS dispatch failed for user %s (ID:%d). Reason: %s',
                    $user->name,
                    $user->id,
                    $e->getMessage()
                ),
                extra: [
                    'event'          => 'failure',
                    'outcome'        => 'failure',
                    'failure_reason' => $e->getMessage(),
                ]
            );

        }



        $this->auditTrail->authEvent(
            action: 'auth.two_factor.code_sent',
            description: sprintf(
                'Credentials accepted for user %s (ID:%d, role:%s). '
                . '2FA verification code sent to registered phone. Awaiting OTP entry.',
                $user->name,
                $user->id,
                $user->getRoleNames()->first() ?? 'unassigned'
            ),
            extra: [
                'event'   => 'action',
                'outcome' => 'success',
            ]
        );



        // ── Step 3: Always redirect to 2FA page ─────────────────────────────

        return to_route('two-factor.show');
    }



    public function destroy(Request $request)
    {

        try {


            $user = Auth::user();

            $name = $user?->name ?? 'Unknown';
            $id   = $user?->id   ?? 0;
            $role = $user?->getRoleNames()->first() ?? 'unknown';



            $this->auditTrail->authEvent(
                action: 'auth.logout',
                description: sprintf(
                    'User %s (ID:%d, role:%s) logged out from IP %s at %s.',
                    $name,
                    $id,
                    $role,
                    $request->ip(),
                    now()->toDateTimeString()
                ),
                extra: [
                    'event' => 'action',
                    'outcome' => 'success'
                ]
            );



            Auth::logout();

            $request->session()->invalidate();


            $request->session()->forget([
                '2fa_user_id',
                '2fa_sms_sent',
                '2fa_expires_at',
                'auth.pending_user_id',
                'auth.pending_email',
            ]);


            $request->session()->regenerateToken();



        } catch (\Throwable $e) {


            Log::error('[Auth] Logout encountered an error', [
                'error' => $e->getMessage()
            ]);

        }


        return redirect('/');
    }
}