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
     * Handle login. Session is written BEFORE any SMS attempt.
     * SMS failure is caught — it NEVER blocks the redirect to 2FA page.
     */
    public function store(Request $request)
    {
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

        // ── Step 1: Generate the code and store EVERYTHING in session FIRST ──
        // This ensures the user can verify even if SMS never arrives.
        $code      = $this->twoFactorService->generateCode($user);
        $expiresAt = now()->addMinutes(10);

        $request->session()->put('2fa_user_id',   $user->id);
        $request->session()->put('2fa_sms_sent',   true);
        $request->session()->put('2fa_expires_at', $expiresAt->timestamp);

        // ── Step 2: Force session to be written to storage NOW ───────────────
        // Without this, if the SMS call crashes the process, the session data
        // is lost and the user ends up back on the home page.
        $request->session()->save();

        // ── Step 3: Attempt SMS in a fire-and-forget style ───────────────────
        // The code is already in cache from generateCode(). SMS is best-effort.
        try {
            // Temporarily increase time limit just for this operation
            if (function_exists('set_time_limit')) {
                set_time_limit(15);
            }
            $this->twoFactorService->sendCode($user, $code);
        } catch (\Throwable $e) {
            // Log failure but NEVER block — user can resend on the 2FA page
            Log::error('[Auth] 2FA SMS failed during login: ' . $e->getMessage());
            Log::info("[Auth] Fallback 2FA code for {$user->email}: {$code}");
        }

        // ── Step 4: Always redirect to 2FA page ─────────────────────────────
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
