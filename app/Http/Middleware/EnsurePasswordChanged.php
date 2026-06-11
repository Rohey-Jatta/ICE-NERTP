<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces users flagged with must_change_password (new accounts created with a
 * default password, or accounts reset by a Super Admin) to set their own
 * password before accessing any authenticated page.
 */
class EnsurePasswordChanged
{
    /**
     * Routes the user may still reach while a password change is pending.
     */
    private const ALLOWED_ROUTES = [
        'password.change',
        'password.change.store',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && !$request->routeIs(self::ALLOWED_ROUTES)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
