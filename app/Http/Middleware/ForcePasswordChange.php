<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Routes that are allowed even when a password change is required.
     */
    private const ALLOWED_ROUTES = [
        'password.change',
        'password.change.update',
        'logout',
        'two-factor.show',
        'two-factor.verify',
        'two-factor.resend',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Pass through if not authenticated
        if (!$user) {
            return $next($request);
        }

        // Pass through if password change is not required
        if (!($user->must_change_password ?? false)) {
            return $next($request);
        }

        // Always allow the change-password route and logout
        if ($request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        // JSON / API callers get a 403 with redirect hint
        if ($request->expectsJson()) {
            return response()->json([
                'message'  => 'You must change your password before continuing.',
                'code'     => 'PASSWORD_CHANGE_REQUIRED',
                'redirect' => route('password.change'),
            ], 403);
        }

        return redirect()
            ->route('password.change')
            ->with('info', 'Your password has been reset by an administrator. Please set a new password to continue.');
    }
}