<?php

use App\Http\Controllers\AcceptanceController;
use App\Http\Controllers\Api\ResultSubmissionController;
use Illuminate\Support\Facades\Route;

/**
 * API Routes for IEC NERTP.
 * 
 * These routes are prefixed with /api automatically.
 * All routes require Sanctum authentication.
 */

Route::middleware(['auth:sanctum'])->group(function () {

    // -------------------------------------------------------------------------
    // Result Submission (Polling Officers)
    // -------------------------------------------------------------------------
    Route::prefix('results')->name('results.')->group(function () {
        
        // Submit result - requires GPS validation middleware
        Route::post('/submit', [ResultSubmissionController::class, 'submit'])
            ->name('submit')
            ->middleware(['role:polling-officer', 'gps.validate']);
        
        // Get officer's submitted results
        Route::get('/my-submissions', [ResultSubmissionController::class, 'mySubmissions'])
            ->name('my-submissions')
            ->middleware(['role:polling-officer']);
    });

    // -------------------------------------------------------------------------
    // Party Acceptance (Party Representatives)
    // -------------------------------------------------------------------------
    Route::prefix('acceptance')->name('acceptance.')->group(function () {
        
        // Submit acceptance decision
        Route::post('/', [AcceptanceController::class, 'submit'])
            ->name('submit')
            ->middleware(['role:party-representative']);
        
        // Get pending acceptances for party rep
        Route::get('/pending', [AcceptanceController::class, 'pending'])
            ->name('pending')
            ->middleware(['role:party-representative']);
    });
});

// -------------------------------------------------------------------------
// Public Results API (NO authentication required)
// -------------------------------------------------------------------------
Route::prefix('public/results')->name('public.results.')->group(function () {
    
    Route::get('/elections', [\App\Http\Controllers\Api\PublicResultsController::class, 'elections'])
        ->name('elections');
    
    Route::get('/{election}', [\App\Http\Controllers\Api\PublicResultsController::class, 'national'])
        ->name('national');
    
    Route::get('/{election}/ward/{ward}', [\App\Http\Controllers\Api\PublicResultsController::class, 'ward'])
        ->name('ward');
    
    Route::get('/{election}/constituency/{constituency}', [\App\Http\Controllers\Api\PublicResultsController::class, 'constituency'])
        ->name('constituency');
    
    Route::get('/{election}/station/{station}', [\App\Http\Controllers\Api\PublicResultsController::class, 'station'])
        ->name('station');
    
    Route::get('/{election}/map', [\App\Http\Controllers\Api\PublicResultsController::class, 'mapData'])
        ->name('map');
});

// -------------------------------------------------------------------------
// Approval API (for Ward/Constituency/Admin Area/Chairman)
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum'])->prefix('approval')->name('approval.')->group(function () {
    
    Route::get('/', [\App\Http\Controllers\ApprovalController::class, 'index'])
        ->name('index');
    
    Route::post('/approve', [\App\Http\Controllers\ApprovalController::class, 'approve'])
        ->name('approve');
    
    Route::post('/reject', [\App\Http\Controllers\ApprovalController::class, 'reject'])
        ->name('reject');
    
    Route::get('/{result}', [\App\Http\Controllers\ApprovalController::class, 'show'])
        ->name('show');
});
