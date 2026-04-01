<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

Route::middleware(['auth', 'role:election-monitor'])
    ->prefix('monitor')
    ->name('monitor.')
    ->group(function () {

    Route::get('/dashboard', fn() => Inertia::render('Monitor/Dashboard', [
        'auth'             => ['user' => Auth::user()],
        'assignedStations' => [],
        'observations'     => [],
        'statistics'       => ['flagged' => 0, 'visited' => 0],
    ]))->name('dashboard');

    Route::get('/stations', fn() => Inertia::render('Monitor/Stations', [
        'auth'     => ['user' => Auth::user()],
        'stations' => [],
    ]))->name('stations');

    Route::get('/submit-observation', fn() => Inertia::render('Monitor/SubmitObservation', [
        'auth' => ['user' => Auth::user()],
    ]))->name('submit-observation');

    Route::post('/observations', fn() => back()->with('success', 'Observation submitted!'))->name('observations.store');

    Route::get('/observations', fn() => Inertia::render('Monitor/Observations', [
        'auth'         => ['user' => Auth::user()],
        'observations' => [],
    ]))->name('observations');
});