<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ChangePasswordController;
use Illuminate\Support\Facades\Route;

// Login routes — accessible to guests only
Route::middleware('guest')->group(function () {
    Route::get('/auth/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/auth/login', [AuthenticatedSessionController::class, 'store']);
});

// Logout route
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Force password change routes
Route::middleware('auth')->group(function () {
    Route::get('/auth/change-password', function () {
        return \Inertia\Inertia::render('Auth/ChangePassword');
    })->name('password.change');

    Route::post('/auth/change-password', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $user = $request->user();
        $user->update([
            'password'             => bcrypt($request->password),
            'must_change_password' => false,
        ]);

        \App\Models\AuditLog::record(
            action: 'auth.password.changed',
            event:  'updated',
            module: 'Authentication',
            auditable: $user,
            extra:  ['outcome' => 'success']
        );

        return redirect($user->dashboard_url ?? '/')->with('success', 'Password changed successfully.');
    })->name('password.change.update');
});