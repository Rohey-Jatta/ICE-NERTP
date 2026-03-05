<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    protected $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function create()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }
        return Inertia::render('Auth/Login');
    }

    protected function redirectByRole($user)
    {
        $role = $user->roles->first();
        if (!$role) return redirect('/dashboard');

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

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::validate($credentials)) {
            $user = \App\Models\User::where('email', $credentials['email'])->first();

            // Skip 2FA in local development
            if (app()->environment('local')) {
                Auth::login($user);
                $request->session()->regenerate();
                return $this->redirectByRole($user);
            }

            // Generate and send 2FA code
            $code = $this->twoFactorService->generateCode($user);
            $this->twoFactorService->sendCode($user, $code);

            Log::info('2FA Code for ' . $user->email . ': ' . $code);

            // Store user ID in session
            $request->session()->put('2fa_user_id', $user->id);

            // FORCE redirect to 2FA
            return to_route('two-factor.show');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ])->withInput($request->only('email'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
