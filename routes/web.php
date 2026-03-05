<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Public\ResultsSummaryController;
use App\Http\Controllers\Public\ResultsMapController;
use App\Http\Controllers\Public\ResultsStationsController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;


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
        $user = Auth::user();
        return Inertia::render('Officer/Dashboard', [
            'auth' => ['user' => $user],
            'station' => $user->assignedStation ?? null,
            'submissions' => [],
        ]);
    })->name('dashboard');

    // SHOW FORM
    Route::get('/results/submit', function () {
        return Inertia::render('Officer/ResultSubmit', [
        'auth' => [
            'user' => Auth::user()
        ],
        'candidates' => Candidate::all(), 
        'election' => Election::where('status', 'active')->first()
    ]);
    })->name('results.submit');

//     // HANDLE SUBMISSION
//     Route::post('/results/submit', function (\Illuminate\Http\Request $request) {
//         Log::info('Result submitted', $request->all());

//          $request->validate([
//         'election_id' => 'required',
//         'registered_voters' => 'required|integer',
//         'total_votes_cast' => 'required|integer',
//         'valid_votes' => 'required|integer',
//         'rejected_votes' => 'required|integer',
//         'photo' => 'required|image|max:10240',
//         'candidate_votes' => 'required|array'
//     ]);

//     $photoPath = null;

//     if ($request->hasFile('photo')) {
//         $photoPath = $request->file('photo')->store('result-photos', 'public');
//     }

//     $result = Result::create([
//         'user_id' => Auth::id(),
//         'election_id' => $request->election_id,
//         'polling_station_id' => Auth::user()->polling_station_id,
//         'registered_voters' => $request->registered_voters,
//         'total_votes_cast' => $request->total_votes_cast,
//         'valid_votes' => $request->valid_votes,
//         'rejected_votes' => $request->rejected_votes,
//         'turnout' => $request->turnout,
//         'photo_path' => $photoPath,
//         'certification_status' => 'Pending Ward Approval'
//     ]);

//     foreach ($request->candidate_votes as $candidateId => $votes) {
//         ResultCandidateVote::create([
//             'result_id' => $result->id,
//             'candidate_id' => $candidateId,
//             'votes' => $votes
//         ]);
//     }

//         return redirect()->route('officer.submissions')
//             ->with('success', 'Results submitted successfully');
//     })->name('results.store');

//     Route::get('/submissions', function () {
//          $results = Result::where('user_id', Auth::id())
//         ->with('pollingStation')
//         ->latest()
//         ->get()
//         ->map(function ($result) {
//             return [
//                 'id' => $result->id,
//                 'polling_station' => $result->pollingStation->name ?? 'Unknown Station',
//                 'submitted_at' => $result->created_at->format('Y-m-d H:i'),
//                 'status' => $result->certification_status,
//                 'total_votes' => $result->total_votes,
//                 'turnout' => $result->turnout,
//                 'rejected_votes' => $result->rejected_votes,
//                 'photo_url' => $result->photo_path ? Storage::url($result->photo_path) : null,
//             ];
//         });

//         return Inertia::render('Officer/Submissions', [
//             'auth' => ['user' => Auth::user()],
//             'submissions' => $results
//         ]);
//     })->name('submissions');

    // HANDLE SUBMISSION
        Route::post('/results/submit', function (Request $request) {
            Log::info('Result submission started', $request->all());

            // Calculate turnout
            $turnout = 0;
            if ($request->registered_voters > 0) {
                $turnout = ($request->total_votes_cast / $request->registered_voters) * 100;
            }

            $request->validate([
                'election_id' => 'required|exists:elections,id',
                'registered_voters' => 'required|integer|min:0',
                'total_votes_cast' => 'required|integer|min:0',
                'valid_votes' => 'required|integer|min:0',
                'rejected_votes' => 'required|integer|min:0',
                'photo' => 'required|image|max:10240',
                'candidate_votes' => 'required|array'
            ]);

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('result-photos', 'public');
                Log::info('Photo uploaded', ['path' => $photoPath]);
            }

            try {
                $result = Result::create([
                    'user_id' => Auth::id(),
                    'election_id' => $request->election_id,
                    'polling_station_id' => Auth::user()->polling_station_id,
                    'registered_voters' => $request->registered_voters,
                    'total_votes_cast' => $request->total_votes_cast,
                    'valid_votes' => $request->valid_votes,
                    'rejected_votes' => $request->rejected_votes,
                    'turnout' => round($turnout, 2),
                    'photo_path' => $photoPath,
                    'certification_status' => 'Pending Ward Approval'
                ]);

                Log::info('Result created', ['result_id' => $result->id]);

                // Save candidate votes
                foreach ($request->candidate_votes as $candidateId => $votes) {
                    ResultCandidateVote::create([
                        'result_id' => $result->id,
                        'candidate_id' => $candidateId,
                        'votes' => $votes
                    ]);
                }

                Log::info('Candidate votes saved');

                return redirect()->route('officer.submissions')
                    ->with('success', 'Results submitted successfully!');

            } catch (\Exception $e) {
                Log::error('Result submission failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return back()->withErrors(['error' => 'Failed to submit results: ' . $e->getMessage()]);
            }
        })->name('results.store');

        Route::get('/submissions', function () {
            $results = Result::where('user_id', Auth::id())
                ->with('pollingStation')
                ->latest()
                ->get()
                ->map(function ($result) {
                    return [
                        'id' => $result->id,
                        'polling_station' => $result->pollingStation->name ?? 'Unknown Station',
                        'submitted_at' => $result->created_at->format('Y-m-d H:i'),
                        'status' => $result->certification_status,
                        'total_votes' => $result->total_votes_cast,
                        'turnout' => $result->turnout,
                        'rejected_votes' => $result->rejected_votes,
                        'photo_url' => $result->photo_path ? Storage::url($result->photo_path) : null,
                    ];
                });

            return Inertia::render('Officer/Submissions', [
                'auth' => ['user' => Auth::user()],
                'submissions' => $results
            ]);
        })->name('submissions');
});

    // WARD APPROVER ROUTES
    Route::prefix('ward')->name('ward.')->middleware('role:ward-approver')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Ward/Dashboard', [
                'auth' => ['user' => Auth::user()],
                'ward' => null,
                'pendingResults' => 0,
                'statistics' => ['approved' => 0, 'rejected' => 0],
            ]);
        })->name('dashboard');

        Route::get('/approval-queue', function () {
            return Inertia::render('Ward/ApprovalQueue', [
                'auth' => ['user' => Auth::user()],
                'pendingResults' => [],
            ]);
        })->name('approval-queue');

        Route::get('/analytics', function () {
            return Inertia::render('Ward/Analytics', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('analytics');
    });

    // CONSTITUENCY APPROVER ROUTES
    Route::prefix('constituency')->name('constituency.')->middleware('role:constituency-approver')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Constituency/Dashboard', [
                'auth' => ['user' => Auth::user()],
                'constituency' => null,
                'pendingResults' => 0,
                'statistics' => ['approved' => 0, 'totalWards' => 0],
            ]);
        })->name('dashboard');

        Route::get('/approval-queue', function () {
            return Inertia::render('Constituency/ApprovalQueue', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('approval-queue');

        Route::get('/ward-breakdowns', function () {
            return Inertia::render('Constituency/WardBreakdowns', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('ward-breakdowns');

        Route::get('/reports', function () {
            return Inertia::render('Constituency/Reports', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('reports');
    });

    // ADMIN AREA APPROVER ROUTES
    Route::prefix('admin-area')->name('admin-area.')->middleware('role:admin-area-approver')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('AdminArea/Dashboard', [
                'auth' => ['user' => Auth::user()],
                'adminArea' => null,
                'pendingResults' => 0,
                'statistics' => ['approved' => 0, 'constituencies' => 0, 'progress' => 0],
            ]);
        })->name('dashboard');

        Route::get('/approval-queue', function () {
            return Inertia::render('AdminArea/ApprovalQueue', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('approval-queue');

        Route::get('/constituency-breakdowns', function () {
            return Inertia::render('AdminArea/ConstituencyBreakdowns', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('constituency-breakdowns');

        Route::get('/analytics', function () {
            return Inertia::render('AdminArea/Analytics', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('analytics');
    });

    // IEC CHAIRMAN ROUTES
    Route::prefix('chairman')->name('chairman.')->middleware('role:iec-chairman')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Chairman/Dashboard', [
                'auth' => ['user' => Auth::user()],
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
            return Inertia::render('Chairman/NationalQueue', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('national-queue');

        Route::get('/all-results', function () {
            return Inertia::render('Chairman/AllResults', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('all-results');

        Route::get('/analytics', function () {
            return Inertia::render('Chairman/Analytics', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('analytics');

        Route::get('/publish', function () {
            return Inertia::render('Chairman/Publish', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('publish');
    });

    // IEC ADMINISTRATOR ROUTES
    Route::prefix('admin')->name('admin.')->middleware('role:iec-administrator')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard', [
                'auth' => ['user' => Auth::user()],
                'statistics' => [
                    'totalUsers' => User::count(),
                    'totalStations' => PollingStation::count(),
                    'activeElections' => Election::where('status', 'active')->count(),
                ],
                'systemStatus' => ['status' => 'Running'],
            ]);
        })->name('dashboard');

        Route::get('/users', function () {
            return Inertia::render('Admin/Users', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('users');

        Route::get('/roles', function () {
            return Inertia::render('Admin/Roles', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('roles');

        Route::get('/elections', function () {
            return Inertia::render('Admin/Elections', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('elections');

        Route::get('/polling-stations', function () {
            return Inertia::render('Admin/PollingStations', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('polling-stations');

        Route::get('/parties', function () {
            return Inertia::render('Admin/Parties', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('parties');

        Route::get('/audit-logs', function () {
            $logs = AuditLog::with('user')->latest()->paginate(50);
            return Inertia::render('Admin/AuditLogs', [
                'auth' => ['user' => Auth::user()],
                'logs' => $logs,
                'filters' => [],
            ]);
        })->name('audit-logs');

        Route::get('/settings', function () {
            return Inertia::render('Admin/Settings', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('settings');

        Route::get('/system-health', function () {
            return Inertia::render('Admin/SystemHealth', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('system-health');

        Route::get('/backups', function () {
            return Inertia::render('Admin/Backups', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('backups');
    });

    // PARTY REPRESENTATIVE ROUTES
    Route::prefix('party')->name('party.')->middleware('role:party-representative')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Party/Dashboard', [
                'auth' => ['user' => Auth::user()],
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
            return Inertia::render('Party/Stations', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('stations');

        Route::get('/pending-acceptance', function () {
            return Inertia::render('Party/PendingAcceptance', [
                'auth' => ['user' => Auth::user()],
                'pendingResults' => [],
            ]);
        })->name('pending-acceptance');

        Route::get('/dashboard-overview', function () {
            return Inertia::render('Party/DashboardOverview', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('dashboard-overview');
    });

    // ELECTION MONITOR ROUTES
    Route::prefix('monitor')->name('monitor.')->middleware('role:election-monitor')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Monitor/Dashboard', [
                'auth' => ['user' => Auth::user()],
                'assignedStations' => [],
                'observations' => [],
                'statistics' => [
                    'flagged' => 0,
                    'visited' => 0,
                ],
            ]);
        })->name('dashboard');

        Route::get('/stations', function () {
            return Inertia::render('Monitor/Stations', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('stations');

        Route::get('/submit-observation', function () {
            return Inertia::render('Monitor/SubmitObservation', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('submit-observation');

        Route::get('/observations', function () {
            return Inertia::render('Monitor/Observations', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('observations');
    });

    // REMOVE GENERIC /dashboard ROUTE - IT WAS CAUSING WHITE PAGE
    // Each role has their own specific dashboard route above
});

// Route::get('/constituency/reports/download/{id}', [ReportController::class, 'download']);
// Route::post('/ward/approve/{id}', [WardApprovalController::class, 'approve']);
