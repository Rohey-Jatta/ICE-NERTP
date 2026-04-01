<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

Route::middleware(['auth', 'role:party-representative'])
    ->prefix('party')
    ->name('party.')
    ->group(function () {

    Route::get('/dashboard', fn() => Inertia::render('Party/Dashboard', [
        'auth'             => ['user' => Auth::user()],
        'party'            => null,
        'assignedStations' => [],
        'statistics'       => ['pendingAcceptance' => 0, 'accepted' => 0, 'disputed' => 0],
    ]))->name('dashboard');

    Route::get('/stations', fn() => Inertia::render('Party/Stations', [
        'auth'     => ['user' => Auth::user()],
        'stations' => [],
    ]))->name('stations');

    Route::get('/pending-acceptance', fn() => Inertia::render('Party/PendingAcceptance', [
        'auth'           => ['user' => Auth::user()],
        'pendingResults' => [],
    ]))->name('pending-acceptance');

    Route::get('/dashboard-overview', fn() => Inertia::render('Party/DashboardOverview', [
        'auth' => ['user' => Auth::user()],
    ]))->name('dashboard-overview');
});
