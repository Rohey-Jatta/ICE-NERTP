<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    protected TwoFactorAuthService $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
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
            return back()->withErrors(['code' => 'Invalid verification code. Please try again.']);
        }

        // Code is valid — now actually log the user in
        Auth::login($user);

        // Regenerate session AFTER login (safe to do here)
        $request->session()->regenerate();

        // Clean up 2FA session data
        $request->session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

        // Redirect based on role
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