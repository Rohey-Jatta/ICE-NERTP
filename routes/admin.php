<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\PoliticalParty;
use App\Models\User;

Route::middleware(['auth', 'role:iec-administrator'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
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

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::get('/users', function () {
        return Inertia::render('Admin/Users', [
            'auth'  => ['user' => Auth::user()],
            'users' => User::with('roles')->paginate(20),
        ]);
    })->name('users');

    // FIXED: Pass actual polling stations, wards, constituencies, admin areas
    Route::get('/users/create', function () {
        return Inertia::render('Admin/UserCreate', [
            'auth'            => ['user' => Auth::user()],
            'pollingStations' => PollingStation::select('id', 'name', 'code')->orderBy('code')->get(),
            'wards'           => AdministrativeHierarchy::where('level', 'ward')->select('id', 'name')->orderBy('name')->get(),
            'constituencies'  => AdministrativeHierarchy::where('level', 'constituency')->select('id', 'name')->orderBy('name')->get(),
            'adminAreas'      => AdministrativeHierarchy::where('level', 'admin_area')->select('id', 'name')->orderBy('name')->get(),
        ]);
    })->name('users.create');

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

            // Assign polling station if provided
            if ($request->role === 'polling-officer' && $request->polling_station_id) {
                PollingStation::where('id', $request->polling_station_id)
                    ->update(['assigned_officer_id' => $user->id]);
            }

            // Assign ward/constituency/admin area if provided
            if (in_array($request->role, ['ward-approver', 'constituency-approver', 'admin-area-approver'])) {
                $fieldMap = [
                    'ward-approver'          => ['field' => 'ward_id',           'level' => 'ward'],
                    'constituency-approver'  => ['field' => 'constituency_id',   'level' => 'constituency'],
                    'admin-area-approver'    => ['field' => 'admin_area_id',      'level' => 'admin_area'],
                ];
                $cfg   = $fieldMap[$request->role];
                $value = $request->input($cfg['field']);
                if ($value) {
                    AdministrativeHierarchy::where('id', $value)
                        ->update(['assigned_approver_id' => $user->id]);
                }
            }

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
            'pollingStations' => PollingStation::select('id', 'name', 'code')->orderBy('code')->get(),
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
                $field    = $fieldMap[$request->role] ?? null;
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

    // ── Party Representatives ─────────────────────────────────────────────────
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

    // ADDED: Edit route for party representatives
    Route::get('/party-representatives/{id}/edit', function ($id) {
        $rep = \App\Models\PartyRepresentative::with(['user', 'politicalParty', 'pollingStations'])->findOrFail($id);
        return Inertia::render('Admin/PartyRepresentativeEdit', [
            'auth'            => ['user' => Auth::user()],
            'representative'  => $rep,
            'parties'         => PoliticalParty::select('id', 'name')->get(),
            'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
        ]);
    })->name('party-representatives.edit');

    Route::put('/party-representatives/{id}', function (Request $request, $id) {
        $rep = \App\Models\PartyRepresentative::findOrFail($id);
        $request->validate([
            'political_party_id'    => 'required|exists:political_parties,id',
            'designation'           => 'nullable|string|max:255',
            'polling_station_ids'   => 'required|array|min:1',
            'polling_station_ids.*' => 'exists:polling_stations,id',
            'is_active'             => 'boolean',
        ]);
        try {
            $rep->update([
                'political_party_id' => $request->political_party_id,
                'designation'        => $request->designation,
                'is_active'          => $request->boolean('is_active', true),
            ]);
            // Sync polling station assignments
            DB::table('party_representative_polling_station')
                ->where('party_representative_id', $rep->id)
                ->delete();
            foreach ($request->polling_station_ids as $sid) {
                DB::table('party_representative_polling_station')->insert([
                    'party_representative_id' => $rep->id,
                    'polling_station_id'      => $sid,
                    'assigned_at'             => now(),
                    'assigned_by'             => Auth::id(),
                ]);
            }
            AuditLog::record(action: 'party_representative.updated', event: 'updated', module: 'UserManagement', auditable: $rep);
            return redirect()->route('admin.party-representatives')->with('success', 'Party representative updated!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('party-representatives.update');

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

    // ── Election Monitors ─────────────────────────────────────────────────────
    Route::get('/election-monitors', function () {
        return Inertia::render('Admin/ElectionMonitors', [
            'auth'     => ['user' => Auth::user()],
            'monitors' => \App\Models\ElectionMonitor::with(['user', 'pollingStations'])->paginate(20),
        ]);
    })->name('election-monitors');

    Route::get('/election-monitors/create', function () {
        return Inertia::render('Admin/ElectionMonitorCreate', [
            'auth'            => ['user' => Auth::user()],
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
            $user = User::find($request->user_id);
            if (!$user->hasRole('election-monitor')) {
                $user->assignRole('election-monitor');
            }
            return redirect()->route('admin.election-monitors')->with('success', 'Election monitor created!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
        }
    })->name('election-monitors.store');

    // ── Roles ─────────────────────────────────────────────────────────────────
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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        return back()->with('success', "Permissions updated for role: {$role->name}");
    })->name('roles.permissions.update');

    // ── Elections ─────────────────────────────────────────────────────────────
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

    Route::get('/elections/create', fn() => Inertia::render('Admin/ElectionCreate', [
        'auth' => ['user' => Auth::user()],
    ]))->name('elections.create');

    Route::post('/elections', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:presidential,parliamentary,local,referendum',
            'date' => 'required|date',
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

    // ── Polling Stations ──────────────────────────────────────────────────────
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
            'officers' => User::role('polling-officer')
                ->whereDoesntHave('assignedStation')
                ->select('id', 'name', 'email')
                ->get(),
            'election' => Election::where('status', 'active')->first(['id', 'name']),
        ]);
    })->name('polling-stations.create');

    Route::post('/polling-stations', function (Request $request) {
        $request->validate([
            'code'                => 'required|string|unique:polling_stations,code',
            'name'                => 'required|string|max:255',
            'address'             => 'nullable|string',
            'ward_id'             => 'required|integer|exists:administrative_hierarchy,id',
            'latitude'            => 'required|numeric|between:-90,90',
            'longitude'           => 'required|numeric|between:-180,180',
            'registered_voters'   => 'required|integer|min:0',
            'assigned_officer_id' => 'nullable|exists:users,id',
            'is_active'           => 'boolean',
            'is_test_station'     => 'boolean',
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

    // ── Parties ───────────────────────────────────────────────────────────────
    Route::get('/parties', function () {
        return Inertia::render('Admin/Parties', [
            'auth'    => ['user' => Auth::user()],
            'parties' => PoliticalParty::all(),
            'flash'   => session()->only(['success', 'error']),
        ]);
    })->name('parties');

    Route::get('/parties/create', fn() => Inertia::render('Admin/PartyCreate', [
        'auth' => ['user' => Auth::user()],
    ]))->name('parties.create');

    Route::get('/parties/{id}/edit', function ($id) {
        $party = PoliticalParty::findOrFail($id);
        return Inertia::render('Admin/PartyEdit', [
            'auth'  => ['user' => Auth::user()],
            'party' => $party,
        ]);
    })->name('parties.edit');

    Route::put('/parties/{id}', function (Request $request, $id) {
        $party = PoliticalParty::findOrFail($id);
        $request->validate([
            'name'         => 'required|string|max:255',
            'abbreviation' => 'required|string|max:10',
            'color'        => 'nullable|string|max:7',
            'leader_name'  => 'nullable|string|max:255',
            'motto'        => 'nullable|string|max:500',
            'headquarters' => 'nullable|string|max:255',
            'website'      => 'nullable|url|max:255',
        ]);
        try {
            $party->update([
                'name'         => $request->name,
                'abbreviation' => strtoupper($request->abbreviation),
                'slug'         => Str::slug($request->name),
                'color'        => $request->color ?? '#3b82f6',
                'leader_name'  => $request->leader_name,
                'motto'        => $request->motto,
                'headquarters' => $request->headquarters,
                'website'      => $request->website,
            ]);
            return redirect()->route('admin.parties')->with('success', 'Party updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('parties.update');

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
            $election        = Election::where('status', 'active')->first();
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

    Route::get('/parties/{id}/candidates', fn($id) => Inertia::render('Admin/Parties', [
        'auth'    => ['user' => Auth::user()],
        'parties' => PoliticalParty::all(),
    ]))->name('parties.candidates');

    // ── Audit Logs ────────────────────────────────────────────────────────────
    Route::get('/audit-logs', function (Request $request) {
        $query = \App\Models\AuditLog::with('user');
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

    // ── Settings ──────────────────────────────────────────────────────────────
    Route::get('/settings', fn() => Inertia::render('Admin/Settings', [
        'auth'     => ['user' => Auth::user()],
        'settings' => [
            'system_name'            => config('app.name', 'IEC NERTP'),
            'system_email'           => config('mail.from.address', 'admin@iec.gm'),
            'timezone'               => config('app.timezone', 'UTC'),
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

    // ── System Health ─────────────────────────────────────────────────────────
    Route::get('/system-health', fn() => Inertia::render('Admin/SystemHealth', [
        'auth' => ['user' => Auth::user()],
    ]))->name('system-health');

    Route::get('/system-health/data', function () {
        $data = [];

        try {
            DB::connection()->getPdo();
            $data['database'] = ['status' => 'online', 'driver' => DB::connection()->getDriverName()];
        } catch (\Exception $e) {
            $data['database'] = ['status' => 'offline', 'driver' => 'unknown', 'error' => $e->getMessage()];
        }

        try {
            Cache::put('healthcheck', 1, 5);
            Cache::get('healthcheck');
            $data['cache'] = ['status' => 'online', 'driver' => config('cache.default')];
        } catch (\Exception $e) {
            $data['cache'] = ['status' => 'offline', 'driver' => config('cache.default'), 'error' => $e->getMessage()];
        }

        try {
            $data['queue'] = [
                'status'  => 'running',
                'pending' => DB::table('jobs')->count(),
                'failed'  => DB::table('failed_jobs')->count(),
            ];
        } catch (\Exception $e) {
            $data['queue'] = ['status' => 'unknown', 'pending' => 0, 'failed' => 0];
        }

        try {
            $total = disk_total_space(storage_path());
            $free  = disk_free_space(storage_path());
            $used  = $total - $free;
            $data['disk'] = [
                'total'           => round($total / 1073741824, 2) . ' GB',
                'free'            => round($free  / 1073741824, 2) . ' GB',
                'used'            => round($used  / 1073741824, 2) . ' GB',
                'used_percentage' => round(($used / $total) * 100, 1) . '%',
            ];
        } catch (\Exception $e) {
            $data['disk'] = null;
        }

        $data['memory'] = [
            'php_memory_used'  => round(memory_get_usage(true)      / 1048576, 2) . ' MB',
            'php_memory_peak'  => round(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
            'php_memory_limit' => ini_get('memory_limit'),
        ];

        $data['app'] = [
            'environment'     => app()->environment(),
            'debug'           => config('app.debug'),
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

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

    // ── Backups ───────────────────────────────────────────────────────────────
    Route::get('/backups', fn() => Inertia::render('Admin/Backups', [
        'auth' => ['user' => Auth::user()],
    ]))->name('backups');

    Route::get('/backups/list', function () {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            return response()->json([]);
        }
        $files   = glob($backupDir . '/*.zip') ?: [];
        $backups = collect($files)->map(fn($file) => [
            'name' => basename($file),
            'path' => basename($file),
            'size' => round(filesize($file) / 1048576, 2) . ' MB',
            'date' => date('Y-m-d H:i:s', filemtime($file)),
        ])->sortByDesc('date')->values()->toArray();
        return response()->json($backups);
    })->name('backups.list');

    Route::post('/backups/create', function () {
        try {
            Artisan::call('backup:run --only-db');
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
});
