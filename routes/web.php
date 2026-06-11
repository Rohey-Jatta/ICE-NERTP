<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Public\ResultsSummaryController;
use App\Http\Controllers\Public\ResultsMapController;
use App\Http\Controllers\Public\ResultsStationsController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\TwoFactorController;

// ─── Home — Public Results Page ───────────────────────────────────────────────
Route::get('/', [ResultsSummaryController::class, 'index'])->name('home');

// ─── Public Results ───────────────────────────────────────────────────────────
Route::get('/results', [ResultsSummaryController::class, 'index'])->name('results');
Route::get('/results/map', [ResultsMapController::class, 'index'])->name('results.map');
Route::get('/results/stations', [ResultsStationsController::class, 'index'])->name('results.stations');

// ─── JSON endpoint: homepage embedded map data ────────────────────────────────
// Returns station lat/lng/status/votes array for the LeafletMap on the homepage.
// Shares the same cache as the full /results/map page.
Route::get('/api/public/map-stations', [ResultsMapController::class, 'stationsJson']);

// ─── Auth routes ──────────────────────────────────────────────────────────────
require __DIR__.'/auth.php';

// ─── Two-Factor (guest only) ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/auth/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/auth/two-factor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('/auth/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
});

// ─── Forced Password Change (auth required) ──────────────────────────────────
// Users created with a default password (or reset by an admin) are redirected
// here by the EnsurePasswordChanged middleware until they set their own.
Route::middleware('auth')->group(function () {
    Route::get('/auth/change-password', [ForcePasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/auth/change-password', [ForcePasswordChangeController::class, 'update'])->name('password.change.store');
});

// ─── Device Registration (auth required) ─────────────────────────────────────
require __DIR__.'/device.php';

// ─── Role-specific dashboards & actions ──────────────────────────────────────
require __DIR__.'/officer.php';
require __DIR__.'/ward.php';
require __DIR__.'/constituency.php';
require __DIR__.'/admin-area.php';
require __DIR__.'/chairman.php';
require __DIR__.'/admin.php';
require __DIR__.'/party.php';
require __DIR__.'/monitor.php';
