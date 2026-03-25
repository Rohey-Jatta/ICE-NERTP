<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Public\ResultsSummaryController;
use App\Http\Controllers\Public\ResultsMapController;
use App\Http\Controllers\Public\ResultsStationsController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ReportController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Models\PoliticalParty;
use App\Models\AdministrativeHierarchy;


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
    Route::post('/auth/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
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
            $users = User::with('roles')->paginate(20);
            return Inertia::render('Admin/Users', [
                'auth' => ['user' => Auth::user()],
                'users' => $users,
            ]);
        })->name('users');

        Route::get('/users/{user}/edit', function (User $user) {
            $roles = ['polling-officer', 'ward-approver', 'constituency-approver', 'admin-area-approver', 'iec-chairman', 'party-representative', 'election-monitor'];
            $pollingStations = PollingStation::select('id', 'name', 'code')->get();
            $wards = AdministrativeHierarchy::where('level', 'ward')->select('id', 'name')->get();
            $constituencies = AdministrativeHierarchy::where('level', 'constituency')->select('id', 'name')->get();
            $adminAreas = AdministrativeHierarchy::where('level', 'admin_area')->select('id', 'name')->get();
            $parties = PoliticalParty::select('id', 'name')->get();

            return Inertia::render('Admin/UserEdit', [
                'auth' => ['user' => Auth::user()],
                'user' => $user->load('roles'),
                'roles' => $roles,
                'pollingStations' => $pollingStations,
                'wards' => $wards,
                'constituencies' => $constituencies,
                'adminAreas' => $adminAreas,
                'parties' => $parties,
            ]);
        })->name('users.edit');

        Route::put('/users/{user}', function (Request $request, User $user) {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'status' => 'required|in:active,inactive,suspended',
                'role' => 'required|string',
            ]);

            try {
                $user->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'status' => $request->status,
                ]);

                // Update role
                $user->syncRoles([$request->role]);

                // Handle specific assignments based on role
                switch ($request->role) {
                    case 'polling-officer':
                        if ($request->polling_station_id) {
                            PollingStation::where('assigned_officer_id', $user->id)->update(['assigned_officer_id' => null]);
                            PollingStation::where('id', $request->polling_station_id)->update(['assigned_officer_id' => $user->id]);
                        }
                        break;
                    case 'ward-approver':
                        if ($request->ward_id) {
                            AdministrativeHierarchy::where('assigned_approver_id', $user->id)->update(['assigned_approver_id' => null]);
                            AdministrativeHierarchy::where('id', $request->ward_id)->update(['assigned_approver_id' => $user->id]);
                        }
                        break;
                    case 'constituency-approver':
                        if ($request->constituency_id) {
                            AdministrativeHierarchy::where('assigned_approver_id', $user->id)->update(['assigned_approver_id' => null]);
                            AdministrativeHierarchy::where('id', $request->constituency_id)->update(['assigned_approver_id' => $user->id]);
                        }
                        break;
                    case 'admin-area-approver':
                        if ($request->admin_area_id) {
                            AdministrativeHierarchy::where('assigned_approver_id', $user->id)->update(['assigned_approver_id' => null]);
                            AdministrativeHierarchy::where('id', $request->admin_area_id)->update(['assigned_approver_id' => $user->id]);
                        }
                        break;
                }

                // Log audit trail
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'User Updated',
                    'description' => "Updated user: {$user->email}",
                    'model_type' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('admin.users')->with('success', 'User updated successfully!');
            } catch (\Exception $e) {
                Log::error('User update failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
            }
        })->name('users.update');

        // Party Representatives Management
        Route::get('/party-representatives', function () {
            $representatives = \App\Models\PartyRepresentative::with(['user', 'politicalParty', 'pollingStations'])->paginate(20);
            return Inertia::render('Admin/PartyRepresentatives', [
                'auth' => ['user' => Auth::user()],
                'representatives' => $representatives,
            ]);
        })->name('party-representatives');

        Route::get('/party-representatives/create', function () {
            $users = User::whereDoesntHave('partyRepresentative')->select('id', 'name', 'email')->get();
            $parties = PoliticalParty::select('id', 'name')->get();
            $pollingStations = PollingStation::select('id', 'name', 'code')->get();

            return Inertia::render('Admin/PartyRepresentativeCreate', [
                'auth' => ['user' => Auth::user()],
                'users' => $users,
                'parties' => $parties,
                'pollingStations' => $pollingStations,
            ]);
        })->name('party-representatives.create');

        Route::post('/party-representatives', function (Request $request) {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'political_party_id' => 'required|exists:political_parties,id',
                'polling_station_ids' => 'required|array|min:1',
                'polling_station_ids.*' => 'exists:polling_stations,id',
                'designation' => 'nullable|string|max:255',
            ]);

            try {
                $representative = \App\Models\PartyRepresentative::create([
                    'user_id' => $request->user_id,
                    'political_party_id' => $request->political_party_id,
                    'election_id' => Election::where('status', 'active')->first()->id ?? 1,
                    'designation' => $request->designation,
                    'accreditation_number' => 'PR-' . strtoupper(uniqid()),
                ]);

                // Assign to polling stations
                foreach ($request->polling_station_ids as $stationId) {
                    DB::table('party_representative_polling_station')->insert([
                        'party_representative_id' => $representative->id,
                        'polling_station_id' => $stationId,
                        'assigned_at' => now(),
                        'assigned_by' => Auth::id(),
                    ]);
                }

                // Assign role
                $user = User::find($request->user_id);
                $user->assignRole('party-representative');

                // Log audit trail
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Party Representative Created',
                    'description' => "Created party representative: {$user->name}",
                    'model_type' => 'PartyRepresentative',
                    'model_id' => $representative->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('admin.party-representatives')->with('success', 'Party representative created successfully!');
            } catch (\Exception $e) {
                Log::error('Party representative creation failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to create party representative: ' . $e->getMessage()]);
            }
        })->name('party-representatives.store');

        // Election Monitors Management
        Route::get('/election-monitors', function () {
            $monitors = \App\Models\ElectionMonitor::with(['user', 'pollingStations'])->paginate(20);
            return Inertia::render('Admin/ElectionMonitors', [
                'auth' => ['user' => Auth::user()],
                'monitors' => $monitors,
            ]);
        })->name('election-monitors');

        Route::get('/election-monitors/create', function () {
            $users = User::whereDoesntHave('electionMonitor')->select('id', 'name', 'email')->get();
            $pollingStations = PollingStation::select('id', 'name', 'code')->get();

            return Inertia::render('Admin/ElectionMonitorCreate', [
                'auth' => ['user' => Auth::user()],
                'users' => $users,
                'pollingStations' => $pollingStations,
            ]);
        })->name('election-monitors.create');

        Route::post('/election-monitors', function (Request $request) {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'organization' => 'nullable|string|max:255',
                'type' => 'required|in:domestic,international,civil_society',
                'polling_station_ids' => 'required|array|min:1',
                'polling_station_ids.*' => 'exists:polling_stations,id',
            ]);

            try {
                $monitor = \App\Models\ElectionMonitor::create([
                    'user_id' => $request->user_id,
                    'election_id' => Election::where('status', 'active')->first()->id ?? 1,
                    'organization' => $request->organization,
                    'type' => $request->type,
                    'accreditation_number' => 'EM-' . strtoupper(uniqid()),
                ]);

                // Assign to polling stations
                foreach ($request->polling_station_ids as $stationId) {
                    DB::table('election_monitor_polling_station')->insert([
                        'election_monitor_id' => $monitor->id,
                        'polling_station_id' => $stationId,
                        'assigned_at' => now(),
                    ]);
                }

                // Assign role
                $user = User::find($request->user_id);
                $user->assignRole('election-monitor');

                // Log audit trail
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Election Monitor Created',
                    'description' => "Created election monitor: {$user->name}",
                    'model_type' => 'ElectionMonitor',
                    'model_id' => $monitor->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('admin.election-monitors')->with('success', 'Election monitor created successfully!');
            } catch (\Exception $e) {
                Log::error('Election monitor creation failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to create election monitor: ' . $e->getMessage()]);
            }
        })->name('election-monitors.store');

        Route::get('/users/create', function () {
            return Inertia::render('Admin/UserCreate', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('users.create');

        Route::post('/users', function (Request $request) {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string',
            ]);

            try {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                    'phone_number' => $request->phone_number ?? null,
                    'status' => 'active',
                ]);

                // Assign role to user
                if ($request->role) {
                    $user->assignRole($request->role);
                }

                // Log audit trail
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'User Created',
                    'description' => "Created user: {$user->email}",
                    'model_type' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('admin.users')->with('success', 'User created successfully!');
            } catch (\Exception $e) {
                Log::error('User creation failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
            }
        })->name('users.store');

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

        Route::get('/elections/create', function () {
            return Inertia::render('Admin/ElectionCreate', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('elections.create');

        Route::post('/elections', function (Request $request) {
            $request->validate([
                'name' => 'required|string|max:255|unique:elections,name',
                'type' => 'required|string|in:presidential,parliamentary,local,referendum',
                'date' => 'required|date|after:today',
            ]);

            try {
                $election = Election::create([
                    'name' => $request->name,
                    'type' => $request->type,
                    'election_date' => $request->date,
                    'status' => 'scheduled',
                    'created_by' => Auth::id(),
                ]);

                // Log audit trail
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Election Created',
                    'description' => "Created election: {$election->name} ({$election->type})",
                    'model_type' => 'Election',
                    'model_id' => $election->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('admin.elections')->with('success', 'Election created successfully!');
            } catch (\Exception $e) {
                Log::error('Election creation failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to create election: ' . $e->getMessage()]);
            }
        })->name('elections.store');

        Route::get('/polling-stations', function () {
            return Inertia::render('Admin/PollingStations', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('polling-stations');

        Route::get('/polling-stations/create', function () {
            $wards = AdministrativeHierarchy::where('level', 'ward')->get(['id', 'name']);
            return Inertia::render('Admin/PollingStationCreate', [
                'auth' => ['user' => Auth::user()],
                'wards' => $wards,
            ]);
        })->name('polling-stations.create');

        Route::post('/polling-stations', function (Request $request) {
            $request->validate([
                'code' => 'required|string|unique:polling_stations,code',
                'name' => 'required|string|max:255',
                'ward_id' => 'required|integer|exists:administrative_hierarchies,id',
            ]);

            try {
                $station = PollingStation::create([
                    'code' => strtoupper($request->code),
                    'name' => $request->name,
                    'ward_id' => $request->ward_id,
                    'status' => 'active',
                ]);

                // Log audit trail
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Polling Station Created',
                    'description' => "Created polling station: {$station->code} - {$station->name}",
                    'model_type' => 'PollingStation',
                    'model_id' => $station->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('admin.polling-stations')->with('success', 'Polling station registered successfully!');
            } catch (\Exception $e) {
                Log::error('Polling station creation failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to create polling station: ' . $e->getMessage()]);
            }
        })->name('polling-stations.store');

        Route::get('/parties', function () {
            return Inertia::render('Admin/Parties', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('parties');

        Route::get('/parties/create', function () {
            return Inertia::render('Admin/PartyCreate', [
                'auth' => ['user' => Auth::user()],
            ]);
        })->name('parties.create');

        Route::post('/parties', function (Request $request) {
            $request->validate([
                'name' => 'required|string|max:255|unique:political_parties,name',
                'abbreviation' => 'required|string|max:10|unique:political_parties,abbreviation',
            ]);

            try {
                $party = PoliticalParty::create([
                    'name' => $request->name,
                    'abbreviation' => strtoupper($request->abbreviation),
                    'status' => 'active',
                    'registered_by' => Auth::id(),
                ]);

                // Log audit trail
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Political Party Registered',
                    'description' => "Registered party: {$party->name} ({$party->abbreviation})",
                    'model_type' => 'PoliticalParty',
                    'model_id' => $party->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('admin.parties')->with('success', 'Party registered successfully!');
            } catch (\Exception $e) {
                Log::error('Party registration failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to register party: ' . $e->getMessage()]);
            }
        })->name('parties.store');

        Route::get('/audit-logs', function (Request $request) {
            $query = AuditLog::with('user');

            // Apply filters
            if ($request->filled('user')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->user . '%')
                      ->orWhere('email', 'like', '%' . $request->user . '%');
                });
            }

            if ($request->filled('action')) {
                $query->where('action', 'like', '%' . $request->action . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $logs = $query->latest()->paginate(50);

            return Inertia::render('Admin/AuditLogs', [
                'auth' => ['user' => Auth::user()],
                'logs' => $logs,
                'filters' => $request->only(['user', 'action', 'date_from', 'date_to']),
            ]);
        })->name('audit-logs');

        Route::get('/settings', function () {
            $settings = [
                'system_name' => config('app.name', 'IEC NERTP'),
                'system_email' => config('mail.from.address', 'admin@iec.gm'),
                'timezone' => config('app.timezone', 'UTC'),
                'require_2fa' => config('auth.require_2fa', false),
                'gps_validation_enabled' => config('election.gps_validation_enabled', true),
                'max_file_size' => config('filesystems.max_file_size', 10240),
                'sms_enabled' => config('services.africastalking.enabled', false),
            ];
            return Inertia::render('Admin/Settings', [
                'auth' => ['user' => Auth::user()],
                'settings' => $settings,
            ]);
        })->name('settings');

        Route::post('/settings', function (Request $request) {
            $request->validate([
                'system_name' => 'required|string|max:255',
                'system_email' => 'required|email',
                'timezone' => 'required|string',
                'require_2fa' => 'boolean',
                'gps_validation_enabled' => 'boolean',
                'max_file_size' => 'required|integer|min:1024|max:51200',
                'sms_enabled' => 'boolean',
            ]);

            // Here you would typically save to database or config files
            // For now, we'll just log the changes
            Log::info('Settings updated', $request->all());

            // Log audit trail
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'Settings Updated',
                'description' => "System settings updated by " . Auth::user()->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('success', 'Settings updated successfully!');
        })->name('settings.update');

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
});

Route::get('/constituency/reports/download/{id}', [ReportController::class, 'download']);

// Route::post('/ward/approve/{id}', [WardApprovalController::class, 'approve']);
