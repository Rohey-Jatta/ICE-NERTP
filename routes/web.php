<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
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

// ─── Two-Factor ───────────────────────────────────────────────────────────────
// GET /show — guest only (no point showing 2FA if already logged in)
Route::middleware('guest')->group(function () {
    Route::get('/auth/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
});

// POST verify & resend must NOT have guest middleware — the session contains
// 2fa_user_id which can trick Laravel's guest check into thinking the user
// is authenticated, causing a 403 on the POST and breaking the flow.
Route::post('/auth/two-factor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
Route::post('/auth/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');

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

// ─── Reports ──────────────────────────────────────────────────────────────────
Route::get('/constituency/reports/download/{id}', [ReportController::class, 'download']);