<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Public\ResultsSummaryController;
use App\Http\Controllers\Public\ResultsMapController;
use App\Http\Controllers\Public\ResultsStationsController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Results routes
Route::get('/results', [ResultsSummaryController::class, 'index'])->name('results');
Route::get('/results/map', [ResultsMapController::class, 'index'])->name('results.map');
Route::get('/results/stations', [ResultsStationsController::class, 'index'])->name('results.stations');

// Dashboard route - FIXED
Route::get('/dashboard', function () {
    $user = Auth::user(); // Use Auth facade instead of auth() helper
    
    if (!$user) {
        return redirect('/auth/login');
    }
    
    return Inertia::render('Dashboard', [
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_id' => $user->employee_id,
        ]
    ]);
})->middleware('auth')->name('dashboard');

require __DIR__.'/auth.php';
