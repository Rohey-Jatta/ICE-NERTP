<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Http\Controllers\Public\ResultsSummaryController;
use App\Http\Controllers\Public\ResultsMapController;
use App\Http\Controllers\Public\ResultsStationsController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ReportController;

// ─── Home — Public Results Page ───────────────────────────────────────────────
Route::get('/', [ResultsSummaryController::class, 'index'])->name('home');

// ─── Public Results ───────────────────────────────────────────────────────────
Route::get('/results', [ResultsSummaryController::class, 'index'])->name('results');
Route::get('/results/map', [ResultsMapController::class, 'index'])->name('results.map');
Route::get('/results/stations', [ResultsStationsController::class, 'index'])->name('results.stations');

// ─── JSON endpoint: homepage embedded map data ────────────────────────────────
Route::get('/api/public/map-stations', [ResultsMapController::class, 'stationsJson']);

// ─── Auth routes ──────────────────────────────────────────────────────────────
require __DIR__.'/auth.php';

// ─── Two-Factor (guest only) ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/auth/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/auth/two-factor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('/auth/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
});

// ─── Forced Password Change (authenticated) ───────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/auth/change-password', function () {
        return Inertia::render('Auth/ChangePassword');
    })->name('password.change');

    Route::post('/auth/change-password', function (Request $request) {
        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->update([
            'password'             => bcrypt($request->password),
            'must_change_password' => false,
        ]);

        AuditLog::record(
            action: 'auth.password.changed',
            event:  'updated',
            module: 'Authentication',
            extra:  ['outcome' => 'success']
        );

        return redirect($user->getDashboardUrlAttribute())
            ->with('success', 'Password updated successfully.');
    })->name('password.change.update');
});

// ─── Device Registration (auth required) ─────────────────────────────────────
require __DIR__.'/device.php';

// ─── Admin Device Management (reset/transfer) ───────────────────────────────
require __DIR__.'/admin_device.php';

// ─── Role-specific dashboards & actions ──────────────────────────────────────
require __DIR__.'/officer.php';
require __DIR__.'/ward.php';
require __DIR__.'/constituency.php';
require __DIR__.'/admin-area.php';
require __DIR__.'/chairman.php';
require __DIR__.'/admin.php';
require __DIR__.'/party.php';
require __DIR__.'/monitor.php';

// ─── Reports ──────────────────────────────────────────────────────────────────
Route::get('/constituency/reports/download/{id}', [ReportController::class, 'download']);