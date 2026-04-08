<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     * Handle login form submission.
     *
     * We intentionally do NOT call Auth::attempt() here because that would
     * log the user in immediately.  Instead we validate credentials manually,
     * store the pending user ID in the session, and redirect to 2FA.
     *
     * We also do NOT call Auth::logout() / session()->invalidate() here
     * because that regenerates the CSRF token and causes 419 errors on
     * the subsequent 2FA form submission.
     */
    public function store(Request $request)
    {
        // Raise the time limit for this request only so a slow SMS gateway
        // cannot kill the login flow.
        set_time_limit(60);

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

        // Generate + send 2FA code (non-blocking in local env)
        $code      = $this->twoFactorService->generateCode($user);
        $expiresAt = now()->addMinutes(10);

        $request->session()->put('2fa_sms_sent',   true);
        $request->session()->put('2fa_expires_at', $expiresAt->timestamp);

        // Send asynchronously — failure is logged but never fatal
        $this->twoFactorService->sendCode($user, $code);

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