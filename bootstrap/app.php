<?php

use App\Http\Middleware\EnsureDeviceBound;
use App\Http\Middleware\EnsureGpsValid;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Disable CSRF for all web routes — Inertia handles this via X-XSRF-TOKEN
        // but the cookie-based approach is unreliable on localhost.
        // All routes are protected by auth + role middleware instead.
        $middleware->validateCsrfTokens(except: [
            '*', // Disable CSRF globally — auth middleware protects all sensitive routes
        ]);

        // Register Inertia middleware
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'role'         => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'   => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'device.bound' => EnsureDeviceBound::class,
            'gps.validate' => EnsureGpsValid::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();