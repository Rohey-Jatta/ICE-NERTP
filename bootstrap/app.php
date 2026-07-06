<?php

use App\Http\Middleware\EnsureDeviceBound;
use App\Http\Middleware\EnsureGpsValid;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\AuditRequestMiddleware;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // "iec_device_id" is written directly by JavaScript (see
        // resources/js/bootstrap.js) as a plain, unencrypted cookie used for
        // device-binding fingerprinting. Without this exception, Laravel's
        // EncryptCookies middleware tries to decrypt it on every request,
        // fails, and silently sets it to null — which made
        // DeviceBindingService::deriveServerFingerprint() unable to ever
        // see it server-side.
        $middleware->encryptCookies(except: [
            'iec_device_id',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AuditRequestMiddleware::class,
            ForcePasswordChange::class,
        ]);

        $middleware->api(append: [
            AuditRequestMiddleware::class,
        ]);

        $middleware->alias([
            'role'         => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'   => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'device.bound' => EnsureDeviceBound::class,
            'gps.validate' => EnsureGpsValid::class,
            'guest'        => RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * EXISTING FILE A LOGIC PRESERVED
         *
         * Spatie PermissionMiddleware / AuthorizationException handling
         * converted into visible Inertia errors.
         */

        $exceptions->render(function (UnauthorizedException $e, $request) {

            if ($request->header('X-Inertia')) {

                return back()->withErrors([
                    'error' => 'You do not have permission to perform this action. Please contact the IEC Administrator if this seems wrong.',
                ]);

            }

            if ($request->expectsJson()) {

                return ApiResponse::error(
                    'You do not have permission to perform this action.',
                    'FORBIDDEN',
                    [],
                    403
                );

            }

        });


        $exceptions->render(function (AuthorizationException $e, $request) {

            if ($request->header('X-Inertia')) {

                return back()->withErrors([
                    'error' => 'You do not have permission to perform this action. Please contact the IEC Administrator if this seems wrong.',
                ]);

            }

            if ($request->expectsJson()) {

                return ApiResponse::error(
                    'You do not have permission to perform this action.',
                    'FORBIDDEN',
                    [],
                    403
                );

            }

        });


        /**
         * ADDED FROM FILE B
         * Authentication error display handling
         */

        $exceptions->render(function (AuthenticationException $e, $request) {

            if ($request->header('X-Inertia')) {

                return redirect()
                    ->guest(route('login'))
                    ->with('info', 'You need to login to continue.');

            }

            if ($request->expectsJson()) {

                return ApiResponse::error(
                    'You need to login to continue.',
                    'UNAUTHENTICATED',
                    [],
                    401
                );

            }

        });


        /**
         * ADDED FROM FILE B
         * Session expiry display handling
         */

        $exceptions->render(function (TokenMismatchException $e, $request) {

            if ($request->header('X-Inertia')) {

                return back()->withErrors([
                    'error' => 'Your session has expired. Please refresh the page and try again.',
                ]);

            }

            if ($request->expectsJson()) {

                return ApiResponse::error(
                    'Your session has expired. Please refresh the page and try again.',
                    'SESSION_EXPIRED',
                    [],
                    419
                );

            }

        });


        /**
         * ADDED FROM FILE B
         * Missing model/API response handling
         */

        $exceptions->render(function (ModelNotFoundException $e, $request) {

            if ($request->expectsJson() && !$request->header('X-Inertia')) {

                return ApiResponse::error(
                    'The requested item could not be found. It may have been deleted or moved.',
                    'NOT_FOUND',
                    [],
                    404
                );

            }

        });


        /**
         * ADDED FROM FILE B
         * Validation display handling
         */

        $exceptions->render(function (ValidationException $e, $request) {

            if ($request->expectsJson() && !$request->header('X-Inertia')) {

                return ApiResponse::error(
                    'Some of the information provided is invalid. Please check the form and try again.',
                    'VALIDATION_ERROR',
                    $e->errors(),
                    422
                );

            }

        });


        /**
         * ADDED FROM FILE B
         * Database error display handling
         */

        $exceptions->render(function (QueryException $e, $request) {

            if ($request->expectsJson() && !$request->header('X-Inertia')) {

                return ApiResponse::error(
                    'Something went wrong while saving your data. Please try again, and contact support if the problem continues.',
                    'DATABASE_ERROR',
                    [],
                    500
                );

            }

        });


        /**
         * ADDED FROM FILE B
         * Final fallback error display
         */

        $exceptions->render(function (\Throwable $e, $request) {

            if ($e instanceof HttpExceptionInterface) {
                return;
            }


            if ($request->expectsJson() && !$request->header('X-Inertia')) {

                return ApiResponse::error(
                    config('app.debug')
                        ? $e->getMessage()
                        : 'Something went wrong on our end. Please try again, and contact support if the problem continues.',
                    'SERVER_ERROR',
                    [],
                    500
                );

            }


            if ($request->header('X-Inertia')) {

                return back()->withErrors([
                    'error' => 'Something went wrong completing this action. Please try again.',
                ]);

            }

        });

    })
    ->booting(function () {

        if (env('VERCEL')) {

            $compiledPath = '/tmp/storage/framework/views';

            if (!is_dir($compiledPath)) {

                mkdir($compiledPath, 0755, true);

            }

            config([
                'view.compiled' => $compiledPath
            ]);


            if (!is_dir('/tmp/storage/framework/sessions')) {

                mkdir('/tmp/storage/framework/sessions', 0755, true);

            }

        }

    })
    ->create();