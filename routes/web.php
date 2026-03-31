<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
    // FIX: Missing resend route — was causing the 404 on "Resend Code" button
    Route::post('/auth/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
});

// ─── Device Registration ──────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/auth/device/verify', function () {
        $user    = Auth::user();
        $pending = app(\App\Services\DeviceBindingService::class)->getPendingDevice($user);
        return Inertia::render('Auth/DeviceVerification', [
            'device_info' => [
                'type'    => $pending['type']    ?? 'desktop',
                'os'      => $pending['os']      ?? 'Unknown',
                'browser' => $pending['browser'] ?? 'Unknown',
                'ip'      => request()->ip(),
            ],
        ]);
    })->name('device.verify');

    Route::post('/auth/device/register', function (Request $request) {
        $request->validate(['device_name' => 'required|string|max:100']);
        $user    = Auth::user();
        $service = app(\App\Services\DeviceBindingService::class);
        try {
            $service->registerDevice($user, $request, $request->device_name);
            $redirectUrl = match ($user->getRoleNames()->first()) {
                'polling-officer'       => '/officer/dashboard',
                'ward-approver'         => '/ward/dashboard',
                'constituency-approver' => '/constituency/dashboard',
                'admin-area-approver'   => '/admin-area/dashboard',
                'iec-chairman'          => '/chairman/dashboard',
                'iec-administrator'     => '/admin/dashboard',
                'party-representative'  => '/party/dashboard',
                'election-monitor'      => '/monitor/dashboard',
                default                 => '/',
            };
            return response()->json(['status' => 'authenticated', 'redirect_url' => $redirectUrl]);
        } catch (\Exception $e) {
            Log::error('Device registration failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Device registration failed.'], 500);
        }
    })->name('device.register');
});

// ─── Authenticated routes ─────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // ── POLLING OFFICER ───────────────────────────────────────────────────────
    Route::prefix('officer')->name('officer.')->middleware('role:polling-officer')->group(function () {

        Route::get('/dashboard', function () {
            $user    = Auth::user();
            $station = PollingStation::where('assigned_officer_id', $user->id)->first();
            return Inertia::render('Officer/Dashboard', [
                'auth'        => ['user' => $user],
                'station'     => $station,
                'submissions' => [],
            ]);
        })->name('dashboard');

        Route::get('/results/submit', function () {
            $election = Election::where('status', 'active')->first();
            return Inertia::render('Officer/ResultSubmit', [
                'auth'       => ['user' => Auth::user()],
                'candidates' => $election ? Candidate::where('election_id', $election->id)->with('politicalParty')->get() : [],
                'election'   => $election,
            ]);
        })->name('results.submit');

        Route::post('/results/submit', function (Request $request) {
            $request->validate([
                'election_id'       => 'required|exists:elections,id',
                'registered_voters' => 'required|integer|min:0',
                'total_votes_cast'  => 'required|integer|min:0',
                'valid_votes'       => 'required|integer|min:0',
                'rejected_votes'    => 'required|integer|min:0',
                'photo'             => 'required|image|max:10240',
                'candidate_votes'   => 'required|array',
            ]);

            $station = PollingStation::where('assigned_officer_id', Auth::id())->first();
            if (!$station) {
                return back()->withErrors(['error' => 'No polling station assigned to your account.']);
            }

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('result-photos', 'public');
            }

            try {
                $result = Result::create([
                    'submission_uuid'         => Str::uuid(),
                    'user_id'                 => Auth::id(),
                    'election_id'             => $request->election_id,
                    'polling_station_id'      => $station->id,
                    'total_registered_voters' => $request->registered_voters,
                    'total_votes_cast'        => $request->total_votes_cast,
                    'valid_votes'             => $request->valid_votes,
                    'rejected_votes'          => $request->rejected_votes,
                    'disputed_votes'          => 0,
                    'result_sheet_photo_path' => $photoPath,
                    'certification_status'    => Result::STATUS_SUBMITTED,
                    'submitted_by'            => Auth::id(),
                    'submitted_at'            => now(),
                ]);

                foreach ($request->candidate_votes as $candidateId => $votes) {
                    ResultCandidateVote::create([
                        'result_id'    => $result->id,
                        'candidate_id' => $candidateId,
                        'election_id'  => $request->election_id,
                        'votes'        => (int) $votes,
                    ]);
                }

                return redirect()->route('officer.submissions')->with('success', 'Results submitted successfully!');
            } catch (\Exception $e) {
                Log::error('Result submission failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to submit: ' . $e->getMessage()]);
            }
        })->name('results.store');

        Route::get('/submissions', function () {
            $results = Result::where('submitted_by', Auth::id())
                ->with('pollingStation')
                ->latest('submitted_at')
                ->get()
                ->map(fn ($r) => [
                    'id'             => $r->id,
                    'polling_station'=> $r->pollingStation->name ?? 'Unknown Station',
                    'submitted_at'   => $r->submitted_at?->format('Y-m-d H:i'),
                    'status'         => $r->certification_status,
                    'total_votes'    => $r->total_votes_cast,
                    'turnout'        => $r->getTurnoutPercentage(),
                    'rejected_votes' => $r->rejected_votes,
                ]);
            return Inertia::render('Officer/Submissions', [
                'auth'        => ['user' => Auth::user()],
                'submissions' => $results,
            ]);
        })->name('submissions');
    });

    // ── WARD APPROVER ─────────────────────────────────────────────────────────
    Route::prefix('ward')->name('ward.')->middleware('role:ward-approver')->group(function () {

        Route::get('/dashboard', function () {
            $user    = Auth::user();
            $ward    = AdministrativeHierarchy::where('assigned_approver_id', $user->id)->where('level', 'ward')->first();
            $pending = $ward
                ? Result::where('certification_status', Result::STATUS_PENDING_WARD)
                    ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))->count()
                : 0;
            return Inertia::render('Ward/Dashboard', [
                'auth'           => ['user' => $user],
                'ward'           => $ward,
                'pendingResults' => $pending,
                'statistics'     => ['approved' => 0, 'rejected' => 0],
            ]);
        })->name('dashboard');

        Route::get('/approval-queue', function () {
            $user    = Auth::user();
            $ward    = AdministrativeHierarchy::where('assigned_approver_id', $user->id)->where('level', 'ward')->first();
            $pending = $ward
                ? Result::where('certification_status', Result::STATUS_PENDING_WARD)
                    ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))
                    ->with(['pollingStation', 'candidateVotes.candidate'])
                    ->get()
                    ->map(fn($r) => [
                        'id'               => $r->id,
                        'polling_station'  => $r->pollingStation->name ?? 'Unknown',
                        'officer'          => 'Officer',
                        'submitted_at'     => $r->submitted_at?->format('Y-m-d H:i'),
                        'total_votes'      => $r->total_votes_cast,
                        'valid_votes'      => $r->valid_votes,
                        'rejected_votes'   => $r->rejected_votes,
                        'turnout'          => $r->getTurnoutPercentage() . '%',
                        'party_acceptance' => 'Pending',
                    ])
                : collect();
            return Inertia::render('Ward/ApprovalQueue', [
                'auth'           => ['user' => $user],
                'pendingResults' => $pending,
            ]);
        })->name('approval-queue');

        Route::post('/approve/{result}', function (Result $result) {
            if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
                return back()->withErrors(['error' => 'Result is not pending ward approval.']);
            }
            DB::transaction(function () use ($result) {
                $result->update(['certification_status' => Result::STATUS_WARD_CERTIFIED]);
                $result->update(['certification_status' => Result::STATUS_PENDING_CONSTITUENCY]);
            });
            AuditLog::record(action: 'certification.ward.approved', event: 'updated', module: 'Certification', auditable: $result);
            return back()->with('success', 'Result certified at ward level.');
        })->name('approve');

        Route::post('/reject/{result}', function (Result $result, Request $request) {
            if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
                return back()->withErrors(['error' => 'Result is not pending ward approval.']);
            }
            $result->update([
                'certification_status'  => Result::STATUS_SUBMITTED,
                'last_rejection_reason' => $request->input('comments', 'Rejected at ward level'),
                'last_rejected_by'      => Auth::id(),
                'last_rejected_at'      => now(),
            ]);
            AuditLog::record(action: 'certification.ward.rejected', event: 'updated', module: 'Certification', auditable: $result);
            return back()->with('success', 'Result rejected and returned to officer.');
        })->name('reject');

        Route::get('/analytics', fn() => Inertia::render('Ward/Analytics', ['auth' => ['user' => Auth::user()]]))->name('analytics');
    });

    // ── CONSTITUENCY APPROVER ─────────────────────────────────────────────────
    Route::prefix('constituency')->name('constituency.')->middleware('role:constituency-approver')->group(function () {

        Route::get('/dashboard', function () {
            $user         = Auth::user();
            $constituency = AdministrativeHierarchy::where('assigned_approver_id', $user->id)->where('level', 'constituency')->first();
            $pending      = $constituency
                ? Result::where('certification_status', Result::STATUS_PENDING_CONSTITUENCY)
                    ->whereHas('pollingStation.ward', fn($q) => $q->where('parent_id', $constituency->id))->count()
                : 0;
            return Inertia::render('Constituency/Dashboard', [
                'auth'           => ['user' => $user],
                'constituency'   => $constituency,
                'pendingResults' => $pending,
                'statistics'     => ['approved' => 0, 'totalWards' => 0],
            ]);
        })->name('dashboard');

        Route::get('/approval-queue', fn() => Inertia::render('Constituency/ApprovalQueue', ['auth' => ['user' => Auth::user()], 'wardResults' => []]))->name('approval-queue');

        Route::post('/approve/{result}', function (Result $result) {
            if ($result->certification_status !== Result::STATUS_PENDING_CONSTITUENCY) {
                return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
            }
            DB::transaction(function () use ($result) {
                $result->update(['certification_status' => Result::STATUS_CONSTITUENCY_CERTIFIED]);
                $result->update(['certification_status' => Result::STATUS_PENDING_ADMIN_AREA]);
            });
            AuditLog::record(action: 'certification.constituency.approved', event: 'updated', module: 'Certification', auditable: $result);
            return back()->with('success', 'Result certified at constituency level.');
        })->name('approve');

        Route::post('/reject/{result}', function (Result $result, Request $request) {
            if ($result->certification_status !== Result::STATUS_PENDING_CONSTITUENCY) {
                return back()->withErrors(['error' => 'Not pending constituency approval.']);
            }
            $result->update([
                'certification_status'  => Result::STATUS_PENDING_WARD,
                'last_rejection_reason' => $request->input('comments', 'Rejected at constituency level'),
                'last_rejected_by'      => Auth::id(),
                'last_rejected_at'      => now(),
            ]);
            return back()->with('success', 'Result returned to ward level.');
        })->name('reject');

        Route::get('/ward-breakdowns', fn() => Inertia::render('Constituency/WardBreakdowns', ['auth' => ['user' => Auth::user()], 'wards' => []]))->name('ward-breakdowns');
        Route::get('/reports', fn() => Inertia::render('Constituency/Reports', ['auth' => ['user' => Auth::user()], 'reports' => []]))->name('reports');
    });

    // ── ADMIN AREA APPROVER ───────────────────────────────────────────────────
    Route::prefix('admin-area')->name('admin-area.')->middleware('role:admin-area-approver')->group(function () {

        Route::get('/dashboard', fn() => Inertia::render('AdminArea/Dashboard', [
            'auth' => ['user' => Auth::user()], 'adminArea' => null, 'pendingResults' => 0,
            'statistics' => ['approved' => 0, 'constituencies' => 0, 'progress' => 0],
        ]))->name('dashboard');

        Route::get('/approval-queue', fn() => Inertia::render('AdminArea/ApprovalQueue', [
            'auth' => ['user' => Auth::user()], 'constituencyResults' => [],
        ]))->name('approval-queue');

        Route::post('/certify/{result}', function (Result $result) {
            if ($result->certification_status !== Result::STATUS_PENDING_ADMIN_AREA) {
                return back()->withErrors(['error' => 'Not pending admin area approval.']);
            }
            DB::transaction(function () use ($result) {
                $result->update(['certification_status' => Result::STATUS_ADMIN_AREA_CERTIFIED]);
                $result->update(['certification_status' => Result::STATUS_PENDING_NATIONAL]);
            });
            AuditLog::record(action: 'certification.admin_area.approved', event: 'updated', module: 'Certification', auditable: $result);
            return back()->with('success', 'Result certified at admin area level.');
        })->name('certify');

        Route::post('/reject/{result}', function (Result $result, Request $request) {
            if ($result->certification_status !== Result::STATUS_PENDING_ADMIN_AREA) {
                return back()->withErrors(['error' => 'Not pending admin area approval.']);
            }
            $result->update([
                'certification_status'  => Result::STATUS_PENDING_CONSTITUENCY,
                'last_rejection_reason' => $request->input('comments', 'Rejected at admin area level'),
                'last_rejected_by'      => Auth::id(),
                'last_rejected_at'      => now(),
            ]);
            return back()->with('success', 'Result returned to constituency level.');
        })->name('reject');

        Route::get('/constituency-breakdowns', fn() => Inertia::render('AdminArea/ConstituencyBreakdowns', ['auth' => ['user' => Auth::user()], 'constituencies' => []]))->name('constituency-breakdowns');
        Route::get('/analytics', fn() => Inertia::render('AdminArea/Analytics', ['auth' => ['user' => Auth::user()]]))->name('analytics');
    });

    // ── IEC CHAIRMAN ──────────────────────────────────────────────────────────
    Route::prefix('chairman')->name('chairman.')->middleware('role:iec-chairman')->group(function () {

        Route::get('/dashboard', function () {
            $pending = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)->count();
            return Inertia::render('Chairman/Dashboard', [
                'auth'            => ['user' => Auth::user()],
                'pendingNational' => $pending,
                'statistics'      => [
                    'nationallyCertified' => Result::where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->count(),
                    'totalStations'       => PollingStation::count(),
                    'totalVoters'         => PollingStation::sum('registered_voters'),
                    'nationalProgress'    => 0,
                ],
                'recentActivity' => [],
            ]);
        })->name('dashboard');

        Route::get('/national-queue', function () {
            $results = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)
                ->with(['pollingStation', 'candidateVotes'])
                ->get()
                ->map(fn($r) => [
                    'id'          => $r->id,
                    'area'        => $r->pollingStation->name ?? 'Unknown',
                    'votes'       => $r->total_votes_cast,
                    'progress'    => 100,
                    'constituencies' => 1,
                    'certified_at'=> $r->updated_at?->format('Y-m-d H:i'),
                ]);
            return Inertia::render('Chairman/NationalQueue', ['auth' => ['user' => Auth::user()], 'adminAreaResults' => $results]);
        })->name('national-queue');

        Route::post('/certify-national/{result}', function (Result $result) {
            if ($result->certification_status !== Result::STATUS_PENDING_NATIONAL) {
                return back()->withErrors(['error' => 'Not pending national certification.']);
            }
            $result->update([
                'certification_status'    => Result::STATUS_NATIONALLY_CERTIFIED,
                'nationally_certified_at' => now(),
            ]);
            AuditLog::record(action: 'certification.national.approved', event: 'updated', module: 'Certification', auditable: $result);
            return back()->with('success', 'Result nationally certified!');
        })->name('certify-national');

        Route::post('/reject/{result}', function (Result $result, Request $request) {
            if ($result->certification_status !== Result::STATUS_PENDING_NATIONAL) {
                return back()->withErrors(['error' => 'Not pending national approval.']);
            }
            $result->update([
                'certification_status'  => Result::STATUS_PENDING_ADMIN_AREA,
                'last_rejection_reason' => $request->input('comments', 'Rejected at national level'),
                'last_rejected_by'      => Auth::id(),
                'last_rejected_at'      => now(),
            ]);
            return back()->with('success', 'Result returned to admin area level.');
        })->name('reject');

        Route::get('/all-results', fn() => Inertia::render('Chairman/AllResults', ['auth' => ['user' => Auth::user()], 'results' => []]))->name('all-results');
        Route::get('/analytics', fn() => Inertia::render('Chairman/Analytics', ['auth' => ['user' => Auth::user()], 'nationalStats' => [], 'regionalBreakdown' => []]))->name('analytics');

        Route::get('/publish', fn() => Inertia::render('Chairman/Publish', [
            'auth'           => ['user' => Auth::user()],
            'readinessCheck' => ['allCertified' => false, 'partyAcceptances' => false, 'auditComplete' => false],
            'summary'        => [],
        ]))->name('publish');

        Route::post('/publish-results', function () {
            $election = Election::where('status', 'active')->first();
            if (!$election) return back()->withErrors(['error' => 'No active election found.']);
            $election->update(['status' => 'certified']);
            return redirect('/results')->with('success', 'Results published successfully!');
        })->name('publish-results');
    });

    // ── IEC ADMINISTRATOR ─────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware('role:iec-administrator')->group(function () {

        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard', [
                'auth'        => ['user' => Auth::user()],
                'statistics'  => [
                    'totalUsers'      => User::count(),
                    'totalStations'   => PollingStation::count(),
                    'activeElections' => Election::where('status', 'active')->count(),
                ],
                'systemStatus' => ['status' => 'Running'],
            ]);
        })->name('dashboard');

        // ── Users ─────────────────────────────────────────────────────────────
        Route::get('/users', function () {
            return Inertia::render('Admin/Users', [
                'auth'  => ['user' => Auth::user()],
                'users' => User::with('roles')->paginate(20),
            ]);
        })->name('users');

        Route::get('/users/create', fn() => Inertia::render('Admin/UserCreate', ['auth' => ['user' => Auth::user()]]))->name('users.create');

        Route::post('/users', function (Request $request) {
            $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'role'     => 'required|string',
            ]);
            try {
                $user = User::create([
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'password' => bcrypt($request->password),
                    'status'   => 'active',
                ]);
                $user->assignRole($request->role);
                AuditLog::record(action: 'user.created', event: 'created', module: 'UserManagement', auditable: $user);
                return redirect()->route('admin.users')->with('success', 'User created successfully!');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
            }
        })->name('users.store');

        Route::get('/users/{user}/edit', function (User $user) {
            return Inertia::render('Admin/UserEdit', [
                'auth'            => ['user' => Auth::user()],
                'user'            => $user->load('roles'),
                'roles'           => ['polling-officer','ward-approver','constituency-approver','admin-area-approver','iec-chairman','iec-administrator','party-representative','election-monitor'],
                'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
                'wards'           => AdministrativeHierarchy::where('level', 'ward')->select('id', 'name')->get(),
                'constituencies'  => AdministrativeHierarchy::where('level', 'constituency')->select('id', 'name')->get(),
                'adminAreas'      => AdministrativeHierarchy::where('level', 'admin_area')->select('id', 'name')->get(),
                'parties'         => PoliticalParty::select('id', 'name')->get(),
            ]);
        })->name('users.edit');

        Route::put('/users/{user}', function (Request $request, User $user) {
            $request->validate([
                'name'   => 'required|string|max:255',
                'email'  => 'required|email|unique:users,email,'.$user->id,
                'status' => 'required|in:active,inactive,suspended',
                'role'   => 'required|string',
            ]);
            try {
                $user->update(['name' => $request->name, 'email' => $request->email, 'status' => $request->status]);
                $user->syncRoles([$request->role]);
                if ($request->role === 'polling-officer' && $request->polling_station_id) {
                    PollingStation::where('assigned_officer_id', $user->id)->update(['assigned_officer_id' => null]);
                    PollingStation::where('id', $request->polling_station_id)->update(['assigned_officer_id' => $user->id]);
                }
                if (in_array($request->role, ['ward-approver','constituency-approver','admin-area-approver'])) {
                    $fieldMap = ['ward-approver' => 'ward_id', 'constituency-approver' => 'constituency_id', 'admin-area-approver' => 'admin_area_id'];
                    $field = $fieldMap[$request->role] ?? null;
                    if ($field && $request->$field) {
                        AdministrativeHierarchy::where('assigned_approver_id', $user->id)->update(['assigned_approver_id' => null]);
                        AdministrativeHierarchy::where('id', $request->$field)->update(['assigned_approver_id' => $user->id]);
                    }
                }
                return redirect()->route('admin.users')->with('success', 'User updated successfully!');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
            }
        })->name('users.update');

        // ── Party Representatives ─────────────────────────────────────────────
        Route::get('/party-representatives', function () {
            return Inertia::render('Admin/PartyRepresentatives', [
                'auth'            => ['user' => Auth::user()],
                'representatives' => \App\Models\PartyRepresentative::with(['user', 'politicalParty', 'pollingStations'])->paginate(20),
            ]);
        })->name('party-representatives');

        Route::get('/party-representatives/create', function () {
            return Inertia::render('Admin/PartyRepresentativeCreate', [
                'auth'            => ['user' => Auth::user()],
                'users'           => User::whereDoesntHave('partyRepresentative')->select('id', 'name', 'email')->get(),
                'parties'         => PoliticalParty::select('id', 'name')->get(),
                'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
            ]);
        })->name('party-representatives.create');

        Route::post('/party-representatives', function (Request $request) {
            $request->validate([
                'user_id'               => 'required|exists:users,id',
                'political_party_id'    => 'required|exists:political_parties,id',
                'polling_station_ids'   => 'required|array|min:1',
                'polling_station_ids.*' => 'exists:polling_stations,id',
                'designation'           => 'nullable|string|max:255',
            ]);
            try {
                $election = Election::where('status', 'active')->firstOrFail();
                $rep = \App\Models\PartyRepresentative::create([
                    'user_id'              => $request->user_id,
                    'political_party_id'   => $request->political_party_id,
                    'election_id'          => $election->id,
                    'designation'          => $request->designation,
                    'accreditation_number' => 'PR-'.strtoupper(uniqid()),
                ]);
                foreach ($request->polling_station_ids as $sid) {
                    DB::table('party_representative_polling_station')->insert([
                        'party_representative_id' => $rep->id,
                        'polling_station_id'       => $sid,
                        'assigned_at'              => now(),
                        'assigned_by'              => Auth::id(),
                    ]);
                }
                User::find($request->user_id)->assignRole('party-representative');
                return redirect()->route('admin.party-representatives')->with('success', 'Party representative created!');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
            }
        })->name('party-representatives.store');

        // ── Election Monitors ─────────────────────────────────────────────────
        Route::get('/election-monitors', function () {
            return Inertia::render('Admin/ElectionMonitors', [
                'auth'     => ['user' => Auth::user()],
                'monitors' => \App\Models\ElectionMonitor::with(['user', 'pollingStations'])->paginate(20),
            ]);
        })->name('election-monitors');

        Route::get('/election-monitors/create', function () {
            return Inertia::render('Admin/ElectionMonitorCreate', [
                'auth'            => ['user' => Auth::user()],
                // Only show users with election-monitor role who don't have a monitor record yet
                'users'           => User::role('election-monitor')
                    ->whereDoesntHave('electionMonitor')
                    ->select('id', 'name', 'email')
                    ->get(),
                'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
            ]);
        })->name('election-monitors.create');

        Route::post('/election-monitors', function (Request $request) {
            $request->validate([
                'user_id'               => 'required|exists:users,id',
                'organization'          => 'nullable|string|max:255',
                'type'                  => 'required|in:domestic,international,civil_society',
                'polling_station_ids'   => 'required|array|min:1',
                'polling_station_ids.*' => 'exists:polling_stations,id',
            ]);
            try {
                $election = Election::where('status', 'active')->firstOrFail();
                $monitor  = \App\Models\ElectionMonitor::create([
                    'user_id'              => $request->user_id,
                    'election_id'          => $election->id,
                    'organization'         => $request->organization,
                    'type'                 => $request->type,
                    'accreditation_number' => 'EM-'.strtoupper(uniqid()),
                ]);
                foreach ($request->polling_station_ids as $sid) {
                    DB::table('election_monitor_polling_station')->insert([
                        'election_monitor_id' => $monitor->id,
                        'polling_station_id'  => $sid,
                        'assigned_at'         => now(),
                    ]);
                }
                // Only assign role if they don't have it yet
                $user = User::find($request->user_id);
                if (!$user->hasRole('election-monitor')) {
                    $user->assignRole('election-monitor');
                }
                return redirect()->route('admin.election-monitors')->with('success', 'Election monitor created!');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
            }
        })->name('election-monitors.store');

        // ── Roles ─────────────────────────────────────────────────────────────
        Route::get('/roles', function () {
            $roles          = \Spatie\Permission\Models\Role::with('permissions')->get();
            $allPermissions = \Spatie\Permission\Models\Permission::orderBy('name')->get();
            return Inertia::render('Admin/Roles', [
                'auth'           => ['user' => Auth::user()],
                'roles'          => $roles,
                'allPermissions' => $allPermissions,
            ]);
        })->name('roles');

        Route::post('/roles/{id}/permissions', function (Request $request, $id) {
            $role = \Spatie\Permission\Models\Role::findOrFail($id);
            $role->syncPermissions($request->permissions ?? []);
            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            return back()->with('success', "Permissions updated for role: {$role->name}");
        })->name('roles.permissions.update');

        // ── Elections ─────────────────────────────────────────────────────────
        Route::get('/elections', function () {
            $elections = Election::latest()->get()->map(fn($e) => [
                'id'     => $e->id,
                'name'   => $e->name,
                'type'   => $e->type,
                'date'   => $e->start_date?->format('Y-m-d'),
                'status' => $e->status,
            ]);
            return Inertia::render('Admin/Elections', [
                'auth'      => ['user' => Auth::user()],
                'elections' => $elections,
                'flash'     => session()->only(['success', 'error']),
            ]);
        })->name('elections');

        Route::get('/elections/create', fn() => Inertia::render('Admin/ElectionCreate', ['auth' => ['user' => Auth::user()]]))->name('elections.create');

        Route::post('/elections', function (Request $request) {
            $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:presidential,parliamentary,local,referendum',
                'date' => 'required|date', // Removed after:today — allows any valid date
            ]);
            try {
                $typeMap = ['local' => 'local_government', 'referendum' => 'by_election'];
                Election::create([
                    'name'       => $request->name,
                    'type'       => $typeMap[$request->type] ?? $request->type,
                    'start_date' => $request->date,
                    'end_date'   => $request->date,
                    'status'     => 'active',
                    'created_by' => Auth::id(),
                ]);
                return redirect()->route('admin.elections')->with('success', 'Election created successfully!');
            } catch (\Exception $e) {
                Log::error('Election creation failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['error' => 'Failed to create election: '.$e->getMessage()]);
            }
        })->name('elections.store');

        // ── Polling Stations ──────────────────────────────────────────────────
        Route::get('/polling-stations', function () {
            $stations = PollingStation::with('ward')->get()->map(fn($s) => [
                'id'     => $s->id,
                'code'   => $s->code,
                'name'   => $s->name,
                'ward'   => $s->ward->name ?? 'N/A',
                'voters' => $s->registered_voters,
            ]);
            return Inertia::render('Admin/PollingStations', [
                'auth'     => ['user' => Auth::user()],
                'stations' => $stations,
            ]);
        })->name('polling-stations');

        Route::get('/polling-stations/create', function () {
            return Inertia::render('Admin/PollingStationCreate', [
                'auth'     => ['user' => Auth::user()],
                'wards'    => AdministrativeHierarchy::where('level', 'ward')->get(['id', 'name']),
                // Officers for assignment dropdown
                'officers' => User::role('polling-officer')
                    ->whereDoesntHave('assignedStation')
                    ->select('id', 'name', 'email')
                    ->get(),
                'election' => Election::where('status', 'active')->first(['id', 'name']),
            ]);
        })->name('polling-stations.create');

        Route::post('/polling-stations', function (Request $request) {
            $request->validate([
                'code'               => 'required|string|unique:polling_stations,code',
                'name'               => 'required|string|max:255',
                'address'            => 'nullable|string',
                'ward_id'            => 'required|integer|exists:administrative_hierarchy,id',
                'latitude'           => 'required|numeric|between:-90,90',
                'longitude'          => 'required|numeric|between:-180,180',
                'registered_voters'  => 'required|integer|min:0',
                'assigned_officer_id'=> 'nullable|exists:users,id',
                'is_active'          => 'boolean',
                'is_test_station'    => 'boolean',
            ]);
            try {
                $election = Election::where('status', 'active')->first();
                PollingStation::create([
                    'code'                => strtoupper($request->code),
                    'name'                => $request->name,
                    'address'             => $request->address,
                    'ward_id'             => $request->ward_id,
                    'election_id'         => $election?->id ?? 1,
                    'latitude'            => $request->latitude,
                    'longitude'           => $request->longitude,
                    'registered_voters'   => $request->registered_voters,
                    'assigned_officer_id' => $request->assigned_officer_id ?: null,
                    'is_active'           => $request->boolean('is_active', true),
                    'is_test_station'     => $request->boolean('is_test_station', false),
                ]);
                return redirect()->route('admin.polling-stations')->with('success', 'Polling station registered!');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
            }
        })->name('polling-stations.store');

        // ── Parties ───────────────────────────────────────────────────────────
        Route::get('/parties', function () {
            return Inertia::render('Admin/Parties', [
                'auth'    => ['user' => Auth::user()],
                'parties' => PoliticalParty::all(),
            ]);
        })->name('parties');

        Route::get('/parties/create', fn() => Inertia::render('Admin/PartyCreate', ['auth' => ['user' => Auth::user()]]))->name('parties.create');

        Route::post('/parties', function (Request $request) {
            $request->validate([
                'name'         => 'required|string|max:255',
                'abbreviation' => 'required|string|max:10',
                'color'        => 'nullable|string|max:7',
                'leader_name'  => 'nullable|string|max:255',
                'leader_photo' => 'nullable|image|max:5120',
                'symbol'       => 'nullable|image|max:5120',
                'motto'        => 'nullable|string|max:500',
                'headquarters' => 'nullable|string|max:255',
                'website'      => 'nullable|url|max:255',
            ]);
            try {
                $election = Election::where('status', 'active')->first();

                $leaderPhotoPath = null;
                if ($request->hasFile('leader_photo')) {
                    $leaderPhotoPath = $request->file('leader_photo')->store('party-photos/leaders', 'public');
                }
                $symbolPath = null;
                if ($request->hasFile('symbol')) {
                    $symbolPath = $request->file('symbol')->store('party-photos/symbols', 'public');
                }

                PoliticalParty::create([
                    'election_id'       => $election?->id ?? 1,
                    'name'              => $request->name,
                    'abbreviation'      => strtoupper($request->abbreviation),
                    'slug'              => Str::slug($request->name),
                    'color'             => $request->color ?? '#3b82f6',
                    'leader_name'       => $request->leader_name,
                    'leader_photo_path' => $leaderPhotoPath,
                    'symbol_path'       => $symbolPath,
                    'motto'             => $request->motto,
                    'headquarters'      => $request->headquarters,
                    'website'           => $request->website,
                ]);
                return redirect()->route('admin.parties')->with('success', 'Party registered!');
            } catch (\Exception $e) {
                return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
            }
        })->name('parties.store');

        // ── Audit Logs ────────────────────────────────────────────────────────
        Route::get('/audit-logs', function (Request $request) {
            $query = AuditLog::with('user');
            if ($request->filled('user'))      $query->whereHas('user', fn($q) => $q->where('name', 'like', '%'.$request->user.'%')->orWhere('email', 'like', '%'.$request->user.'%'));
            if ($request->filled('action'))    $query->where('action', 'like', '%'.$request->action.'%');
            if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
            if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);
            return Inertia::render('Admin/AuditLogs', [
                'auth'    => ['user' => Auth::user()],
                'logs'    => $query->latest()->paginate(50),
                'filters' => $request->only(['user', 'action', 'date_from', 'date_to']),
            ]);
        })->name('audit-logs');

        // ── Settings ──────────────────────────────────────────────────────────
        Route::get('/settings', fn() => Inertia::render('Admin/Settings', [
            'auth'     => ['user' => Auth::user()],
            'settings' => [
                'system_name'            => config('app.name', 'IEC NERTP'),
                'system_email'           => config('mail.from.address', 'admin@iec.gm'),
                'timezone'               => config('app.timezone', ''),
                'require_2fa'            => false,
                'gps_validation_enabled' => true,
                'max_file_size'          => 10240,
                'sms_enabled'            => false,
                ],
            ]))->name('settings');
        Route::post('/settings', function (Request $request) {
        $request->validate([
            'system_name'            => 'required|string|max:255',
            'system_email'           => 'required|email',
            'timezone'               => 'required|string',
            'require_2fa'            => 'boolean',
            'gps_validation_enabled' => 'boolean',
            'max_file_size'          => 'required|integer|min:1024|max:51200',
            'sms_enabled'            => 'boolean',
        ]);
        Log::info('System settings updated', $request->all());
        return back()->with('success', 'Settings saved successfully!');
    })->name('settings.update');

    // ── System Health (JSON API endpoint) ─────────────────────────────────
    Route::get('/system-health', fn() => Inertia::render('Admin/SystemHealth', ['auth' => ['user' => Auth::user()]]))->name('system-health');

    Route::get('/system-health/data', function () {
        $data = [];

        // Database
        try {
            DB::connection()->getPdo();
            $data['database'] = ['status' => 'online', 'driver' => DB::connection()->getDriverName()];
        } catch (\Exception $e) {
            $data['database'] = ['status' => 'offline', 'driver' => 'unknown', 'error' => $e->getMessage()];
        }

        // Cache
        try {
            Cache::put('healthcheck', 1, 5);
            Cache::get('healthcheck');
            $data['cache'] = ['status' => 'online', 'driver' => config('cache.default')];
        } catch (\Exception $e) {
            $data['cache'] = ['status' => 'offline', 'driver' => config('cache.default'), 'error' => $e->getMessage()];
        }

        // Queue
        try {
            $pending = DB::table('jobs')->count();
            $failed  = DB::table('failed_jobs')->count();
            $data['queue'] = ['status' => 'running', 'pending' => $pending, 'failed' => $failed];
        } catch (\Exception $e) {
            $data['queue'] = ['status' => 'unknown', 'pending' => 0, 'failed' => 0];
        }

        // Disk
        try {
            $total = disk_total_space(storage_path());
            $free  = disk_free_space(storage_path());
            $used  = $total - $free;
            $data['disk'] = [
                'total'            => round($total / 1073741824, 2) . ' GB',
                'free'             => round($free  / 1073741824, 2) . ' GB',
                'used'             => round($used  / 1073741824, 2) . ' GB',
                'used_percentage'  => round(($used / $total) * 100, 1) . '%',
            ];
        } catch (\Exception $e) {
            $data['disk'] = null;
        }

        // Memory
        $data['memory'] = [
            'php_memory_used'  => round(memory_get_usage(true)      / 1048576, 2) . ' MB',
            'php_memory_peak'  => round(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
            'php_memory_limit' => ini_get('memory_limit'),
        ];

        // App info
        $data['app'] = [
            'environment'     => app()->environment(),
            'debug'           => config('app.debug'),
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        // Log info
        $logPath = storage_path('logs/laravel.log');
        try {
            $data['logs'] = [
                'exists'        => file_exists($logPath),
                'size'          => file_exists($logPath) ? round(filesize($logPath) / 1048576, 2) . ' MB' : '0 MB',
                'recent_errors' => file_exists($logPath)
                    ? substr_count(file_get_contents($logPath), '[' . now()->format('Y-m-d') . ']')
                    : 0,
            ];
        } catch (\Exception $e) {
            $data['logs'] = ['exists' => false, 'size' => '0 MB', 'recent_errors' => 0];
        }

        return response()->json($data);
    })->name('system-health.data');

    // ── Backups ───────────────────────────────────────────────────────────
    Route::get('/backups', fn() => Inertia::render('Admin/Backups', ['auth' => ['user' => Auth::user()]]))->name('backups');

    Route::get('/backups/list', function () {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            return response()->json([]);
        }
        $files = glob($backupDir . '/*.zip') ?: [];
        $backups = collect($files)->map(function ($file) {
            return [
                'name' => basename($file),
                'path' => basename($file),
                'size' => round(filesize($file) / 1048576, 2) . ' MB',
                'date' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        })->sortByDesc('date')->values()->toArray();
        return response()->json($backups);
    })->name('backups.list');

    Route::post('/backups/create', function () {
        try {
            // Use spatie/laravel-backup
            Artisan::call('backup:run --only-db');
            $output = Artisan::output();
            return response()->json(['success' => true, 'message' => 'Backup created successfully!']);
        } catch (\Exception $e) {
            Log::error('Backup failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Backup failed: '.$e->getMessage()], 500);
        }
    })->name('backups.create');

    Route::get('/backups/download', function (Request $request) {
        $filename = basename($request->query('file', ''));
        $path     = storage_path('app/backups/' . $filename);
        if (!$filename || !file_exists($path)) {
            abort(404, 'Backup file not found.');
        }
        return response()->download($path);
    })->name('backups.download');

    // ── Other Admin pages ─────────────────────────────────────────────────
    Route::get('/parties/{id}/candidates', function ($id) {
        return Inertia::render('Admin/Parties', ['auth' => ['user' => Auth::user()], 'parties' => PoliticalParty::all()]);
    })->name('parties.candidates');
});

// ── PARTY REPRESENTATIVE ──────────────────────────────────────────────────
Route::prefix('party')->name('party.')->middleware('role:party-representative')->group(function () {
    Route::get('/dashboard', fn() => Inertia::render('Party/Dashboard', [
        'auth' => ['user' => Auth::user()], 'party' => null,
        'assignedStations' => [], 'statistics' => ['pendingAcceptance' => 0, 'accepted' => 0, 'disputed' => 0],
    ]))->name('dashboard');
    Route::get('/stations', fn() => Inertia::render('Party/Stations', ['auth' => ['user' => Auth::user()], 'stations' => []]))->name('stations');
    Route::get('/pending-acceptance', fn() => Inertia::render('Party/PendingAcceptance', ['auth' => ['user' => Auth::user()], 'pendingResults' => []]))->name('pending-acceptance');
    Route::get('/dashboard-overview', fn() => Inertia::render('Party/DashboardOverview', ['auth' => ['user' => Auth::user()]]))->name('dashboard-overview');
});

// ── ELECTION MONITOR ──────────────────────────────────────────────────────
Route::prefix('monitor')->name('monitor.')->middleware('role:election-monitor')->group(function () {
    Route::get('/dashboard', fn() => Inertia::render('Monitor/Dashboard', [
        'auth' => ['user' => Auth::user()], 'assignedStations' => [],
        'observations' => [], 'statistics' => ['flagged' => 0, 'visited' => 0],
    ]))->name('dashboard');
    Route::get('/stations', fn() => Inertia::render('Monitor/Stations', ['auth' => ['user' => Auth::user()], 'stations' => []]))->name('stations');
    Route::get('/submit-observation', fn() => Inertia::render('Monitor/SubmitObservation', ['auth' => ['user' => Auth::user()]]))->name('submit-observation');
    Route::post('/observations', fn() => back()->with('success', 'Observation submitted!'))->name('observations.store');
    Route::get('/observations', fn() => Inertia::render('Monitor/Observations', ['auth' => ['user' => Auth::user()], 'observations' => []]))->name('observations');
});
