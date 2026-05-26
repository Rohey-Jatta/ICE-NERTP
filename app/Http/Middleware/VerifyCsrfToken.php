<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected $except = [
        // Add any routes that should skip CSRF (added routes to test the productivity)
        'officer/results/submit',
        'logout',
        'auth/login',
        'auth/two-factor',
        'auth/two-factor/resend',
        'auth/device/register',
        'admin/parties/*/update',
        'admin/polling-stations/*',
        'admin/hierarchy/*',
        'admin/users/*',
        'admin/elections/*',
        'admin/parties/*',
        'ward/*',
        'constituency/*',
        'admin-area/*',
        'chairman/*',
        'party/*',
        'monitor/*',
        'officer/*',

    ];
}
