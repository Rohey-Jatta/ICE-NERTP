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
     * log the user in immediately. Instead we validate credentials manually,
     * store the pending user ID in the session, and redirect to 2FA.
     *
     * We also do NOT call Auth::logout() / session()->invalidate() because
     * that regenerates the CSRF token and causes 419 errors on the 2FA form.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Find the user without logging them in
        $user = User::where('email', $request->email)->first();

        // Validate credentials manually
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // Check account status
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Contact IEC Administrator.'],
            ]);
        }

        // Store pending user ID in session for 2FA step
        // Do NOT regenerate the session here — that would invalidate the CSRF token
        $request->session()->put('2fa_user_id', $user->id);

        // Generate and send the 2FA code
        $code = $this->twoFactorService->generateCode($user);
        $this->twoFactorService->sendCode($user, $code);

        $expiresAt = now()->addMinutes(10);
        $request->session()->put('2fa_sms_sent', true);
        $request->session()->put('2fa_expires_at', $expiresAt->timestamp);

        // Redirect to 2FA page — session (and CSRF token) is still valid
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