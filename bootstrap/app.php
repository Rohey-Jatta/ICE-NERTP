<?php

use App\Http\Middleware\EnsureDeviceBound;
use App\Http\Middleware\EnsureGpsValid;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\AuditRequestMiddleware;
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

        $middleware->validateCsrfTokens(except: [
            '*',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AuditRequestMiddleware::class,
        ]);

        $middleware->api(append: [
            AuditRequestMiddleware::class,
        ]);

        $middleware->alias([
            'role'         => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'   => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'device.bound' => EnsureDeviceBound::class,
            'gps.validate' => EnsureGpsValid::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    // CRITICAL: This section tells Laravel how to behave on Vercel
    ->booting(function () {
        if (env('VERCEL')) {
            // Set the compiled view path to /tmp (the only writable folder on Vercel)
            $compiledPath = '/tmp/storage/framework/views';

            if (!is_dir($compiledPath)) {
                mkdir($compiledPath, 0755, true);
            }

            config(['view.compiled' => $compiledPath]);

            // Also ensure the session and cache have a place to go if using file driver
            if (!is_dir('/tmp/storage/framework/sessions')) {
                mkdir('/tmp/storage/framework/sessions', 0755, true);
            }
        }
    })
    ->create();
