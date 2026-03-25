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
    protected $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function show()
    {
        if (!session('2fa_user_id')) {
            return redirect()->route('login');
        }

        $userId = session('2fa_user_id');
        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        // If this is the first visit or the code previously expired, create a new code and send SMS.
        $expiresAt = session('2fa_expires_at') ? now()->createFromTimestamp(session('2fa_expires_at')) : null;

        if (!session('2fa_sms_sent') || !$expiresAt || now()->greaterThanOrEqualTo($expiresAt)) {
            $code = $this->twoFactorService->generateCode($user);
            $this->twoFactorService->sendCode($user, $code);

            $expiresAt = now()->addMinutes(10);
            session([
                '2fa_sms_sent' => true,
                '2fa_expires_at' => $expiresAt->timestamp,
            ]);
        }

        return Inertia::render('Auth/TwoFactor', [
            'expiresAt' => session('2fa_expires_at'),
            'status' => session('status'),
        ]);
    }

    public function resend(Request $request)
    {
        if (!session('2fa_user_id')) {
            return redirect()->route('login');
        }

        $userId = session('2fa_user_id');
        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        $code = $this->twoFactorService->generateCode($user);
        $this->twoFactorService->sendCode($user, $code);

        $expiresAt = now()->addMinutes(10);
        session([
            '2fa_sms_sent' => true,
            '2fa_expires_at' => $expiresAt->timestamp,
        ]);

        return back()->with('status', 'A new verification code has been sent to your phone.');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = session('2fa_user_id');
        if (!$userId) {
            return back()->withErrors(['code' => 'Session expired. Please login again.']);
        }

        $user = User::find($userId);
        if (!$user) {
            return back()->withErrors(['code' => 'User not found.']);
        }

        // Check for expiry before code match
        $expiresAt = session('2fa_expires_at') ? now()->createFromTimestamp(session('2fa_expires_at')) : null;

        if (!$expiresAt || now()->greaterThan($expiresAt)) {
            session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);
            return back()->withErrors(['code' => 'Verification code has expired. Please request a new code.']);
        }

        // Verify code
        if ($this->twoFactorService->verifyCode($user, $request->code)) {
            // Login user
            Auth::login($user);
            $request->session()->regenerate();
            session()->forget(['2fa_user_id', '2fa_sms_sent', '2fa_expires_at']);

            // Redirect based on role
            return $this->redirectByRole($user);
        }

        return back()->withErrors(['code' => 'Invalid verification code.']);
    }

    protected function redirectByRole($user)
    {
        // Get user's primary role
        $role = $user->roles->first();

        if (!$role) {
            return redirect('/dashboard');
        }

        // Redirect based on role as per architecture
        return match($role->name) {
            'polling-officer' => redirect()->route('officer.dashboard'),
            'ward-approver' => redirect()->route('ward.dashboard'),
            'constituency-approver' => redirect()->route('constituency.dashboard'),
            'admin-area-approver' => redirect()->route('admin-area.dashboard'),
            'iec-chairman' => redirect()->route('chairman.dashboard'),
            'iec-administrator' => redirect()->route('admin.dashboard'),
            'party-representative' => redirect()->route('party.dashboard'),
            'election-monitor' => redirect()->route('monitor.dashboard'),
            default => redirect('/dashboard'),
        };
    }
}
