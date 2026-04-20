<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    protected TwoFactorAuthService $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle login. Code is stored in cache BEFORE SMS is attempted.
     * SMS failure is caught and logged — it NEVER blocks the redirect.
     */
    public function store(Request $request)
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(60);
        }

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Contact IEC Administrator.'],
            ]);
        }

        // Store pending user ID for the 2FA step
        $request->session()->put('2fa_user_id', $user->id);

        // ── Generate & STORE the code in cache BEFORE attempting SMS ──────────
        // This ensures the user can always verify, even if SMS fails.
        $code      = $this->twoFactorService->generateCode($user);
        $expiresAt = now()->addMinutes(10);

        $request->session()->put('2fa_sms_sent',   true);
        $request->session()->put('2fa_expires_at', $expiresAt->timestamp);

        // ── Send SMS — wrapped in try/catch so it NEVER blocks login ──────────
        try {
            $this->twoFactorService->sendCode($user, $code);
        } catch (\Throwable $e) {
            // Log the failure but continue — code is in cache, user can resend
            Log::error('[Auth] 2FA SMS dispatch failed: ' . $e->getMessage());
            Log::info("[Auth] Fallback 2FA code for {$user->email}: {$code}");
        }

        return to_route('two-factor.show');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
