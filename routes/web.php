<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Public\ResultsSummaryController;
use App\Http\Controllers\Public\ResultsMapController;
use App\Http\Controllers\Public\ResultsStationsController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ReportController;

// ─── Public routes ────────────────────────────────────────────────────────────
Route::get('/', fn() => Inertia::render('Welcome'))->name('home');
Route::get('/results', [ResultsSummaryController::class, 'index'])->name('results');
Route::get('/results/map', [ResultsMapController::class, 'index'])->name('results.map');
Route::get('/results/stations', [ResultsStationsController::class, 'index'])->name('results.stations');

// ─── Auth routes ──────────────────────────────────────────────────────────────
require __DIR__.'/auth.php';

// ─── Two-Factor (guest only) ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/auth/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/auth/two-factor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('/auth/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
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

// ─── Reports ──────────────────────────────────────────────────────────────────
Route::get('/constituency/reports/download/{id}', [ReportController::class, 'download']);
