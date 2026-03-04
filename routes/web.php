<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Public\ResultsSummaryController;
use App\Http\Controllers\Public\ResultsMapController;
use App\Http\Controllers\Public\ResultsStationsController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Models\AuditLog;

// Public routes
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/results', [ResultsSummaryController::class, 'index'])->name('results');
Route::get('/results/map', [ResultsMapController::class, 'index'])->name('results.map');
Route::get('/results/stations', [ResultsStationsController::class, 'index'])->name('results.stations');

// Auth routes
require __DIR__.'/auth.php';

// 2FA routes
Route::middleware('guest')->group(function () {
    Route::get('/auth/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/auth/two-factor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
});

// Protected routes
Route::middleware('auth')->group(function () {
    
    // POLLING OFFICER ROUTES
    Route::prefix('officer')->name('officer.')->middleware('role:polling-officer')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Officer/Dashboard', [
                'station' => auth()->user()->assignedStation ?? null,
                'submissions' => [],
            ]);
        })->name('dashboard');
        
        Route::get('/results/submit', function () {
            return Inertia::render('Officer/ResultSubmit');
        })->name('results.submit');
        
        Route::get('/submissions', function () {
            return Inertia::render('Officer/Submissions');
        })->name('submissions');
    });
    
    // WARD APPROVER ROUTES
    Route::prefix('ward')->name('ward.')->middleware('role:ward-approver')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Ward/Dashboard', [
                'ward' => null,
                'pendingResults' => 0,
                'statistics' => ['approved' => 0, 'rejected' => 0],
            ]);
        })->name('dashboard');
        
        Route::get('/approval-queue', function () {
            return Inertia::render('Ward/ApprovalQueue', [
                'pendingResults' => [],
            ]);
        })->name('approval-queue');
        
        Route::get('/analytics', function () {
            return Inertia::render('Ward/Analytics');
        })->name('analytics');
    });
    
    // CONSTITUENCY APPROVER ROUTES
    Route::prefix('constituency')->name('constituency.')->middleware('role:constituency-approver')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Constituency/Dashboard', [
                'constituency' => null,
                'pendingResults' => 0,
                'statistics' => ['approved' => 0, 'totalWards' => 0],
            ]);
        })->name('dashboard');
        
        Route::get('/approval-queue', function () {
            return Inertia::render('Constituency/ApprovalQueue');
        })->name('approval-queue');
        
        Route::get('/ward-breakdowns', function () {
            return Inertia::render('Constituency/WardBreakdowns');
        })->name('ward-breakdowns');
        
        Route::get('/reports', function () {
            return Inertia::render('Constituency/Reports');
        })->name('reports');
    });
    
    // ADMIN AREA APPROVER ROUTES
    Route::prefix('admin-area')->name('admin-area.')->middleware('role:admin-area-approver')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('AdminArea/Dashboard', [
                'adminArea' => null,
                'pendingResults' => 0,
                'statistics' => ['approved' => 0, 'constituencies' => 0, 'progress' => 0],
            ]);
        })->name('dashboard');
        
        Route::get('/approval-queue', function () {
            return Inertia::render('AdminArea/ApprovalQueue');
        })->name('approval-queue');
        
        Route::get('/constituency-breakdowns', function () {
            return Inertia::render('AdminArea/ConstituencyBreakdowns');
        })->name('constituency-breakdowns');
        
        Route::get('/analytics', function () {
            return Inertia::render('AdminArea/Analytics');
        })->name('analytics');
    });
    
    // IEC CHAIRMAN ROUTES
    Route::prefix('chairman')->name('chairman.')->middleware('role:iec-chairman')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Chairman/Dashboard', [
                'pendingNational' => 0,
                'statistics' => [
                    'nationallyCertified' => 0,
                    'totalStations' => 0,
                    'totalVoters' => 0,
                    'nationalProgress' => 0,
                ],
                'recentActivity' => [],
            ]);
        })->name('dashboard');
        
        Route::get('/national-queue', function () {
            return Inertia::render('Chairman/NationalQueue');
        })->name('national-queue');
        
        Route::get('/all-results', function () {
            return Inertia::render('Chairman/AllResults');
        })->name('all-results');
        
        Route::get('/analytics', function () {
            return Inertia::render('Chairman/Analytics');
        })->name('analytics');
        
        Route::get('/publish', function () {
            return Inertia::render('Chairman/Publish');
        })->name('publish');
    });
    
    // IEC ADMINISTRATOR ROUTES
    Route::prefix('admin')->name('admin.')->middleware('role:iec-administrator')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard', [
                'statistics' => [
                    'totalUsers' => \App\Models\User::count(),
                    'totalStations' => \App\Models\PollingStation::count(),
                    'activeElections' => \App\Models\Election::where('status', 'active')->count(),
                ],
                'systemStatus' => ['status' => 'Running'],
            ]);
        })->name('dashboard');
        
        Route::get('/users', function () {
            return Inertia::render('Admin/Users');
        })->name('users');
        
        Route::get('/roles', function () {
            return Inertia::render('Admin/Roles');
        })->name('roles');
        
        Route::get('/elections', function () {
            return Inertia::render('Admin/Elections');
        })->name('elections');
        
        Route::get('/polling-stations', function () {
            return Inertia::render('Admin/PollingStations');
        })->name('polling-stations');
        
        Route::get('/parties', function () {
            return Inertia::render('Admin/Parties');
        })->name('parties');
        
        Route::get('/audit-logs', function () {
            $logs = AuditLog::with('user')
                ->latest()
                ->paginate(50);
            
            return Inertia::render('Admin/AuditLogs', [
                'logs' => $logs,
                'filters' => [],
            ]);
        })->name('audit-logs');
        
        Route::get('/settings', function () {
            return Inertia::render('Admin/Settings');
        })->name('settings');
        
        Route::get('/system-health', function () {
            return Inertia::render('Admin/SystemHealth');
        })->name('system-health');
        
        Route::get('/backups', function () {
            return Inertia::render('Admin/Backups');
        })->name('backups');
    });
    
    // PARTY REPRESENTATIVE ROUTES
    Route::prefix('party')->name('party.')->middleware('role:party-representative')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Party/Dashboard', [
                'party' => null,
                'assignedStations' => [],
                'statistics' => [
                    'pendingAcceptance' => 0,
                    'accepted' => 0,
                    'disputed' => 0,
                ],
            ]);
        })->name('dashboard');
        
        Route::get('/stations', function () {
            return Inertia::render('Party/Stations');
        })->name('stations');
        
        Route::get('/pending-acceptance', function () {
            return Inertia::render('Party/PendingAcceptance', [
                'pendingResults' => [],
            ]);
        })->name('pending-acceptance');
        
        Route::get('/dashboard-overview', function () {
            return Inertia::render('Party/DashboardOverview');
        })->name('dashboard-overview');
    });
    
    // ELECTION MONITOR ROUTES
    Route::prefix('monitor')->name('monitor.')->middleware('role:election-monitor')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Monitor/Dashboard', [
                'assignedStations' => [],
                'observations' => [],
                'statistics' => [
                    'flagged' => 0,
                    'visited' => 0,
                ],
            ]);
        })->name('dashboard');
        
        Route::get('/stations', function () {
            return Inertia::render('Monitor/Stations');
        })->name('stations');
        
        Route::get('/submit-observation', function () {
            return Inertia::render('Monitor/SubmitObservation');
        })->name('submit-observation');
        
        Route::get('/observations', function () {
            return Inertia::render('Monitor/Observations');
        })->name('observations');
    });
    
    // Generic dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'user' => auth()->user(),
        ]);
    })->name('dashboard');
});
