<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * If the user is already authenticated, redirect them to their role-specific dashboard
     * instead of allowing them to access guest-only routes like login.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
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

        return $next($request);
    }
}
