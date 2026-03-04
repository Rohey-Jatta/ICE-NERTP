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

        return Inertia::render('Auth/TwoFactor');
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

        // Verify code
        if ($this->twoFactorService->verifyCode($user, $request->code)) {
            // Login user
            Auth::login($user);
            $request->session()->regenerate();
            session()->forget('2fa_user_id');
            
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
