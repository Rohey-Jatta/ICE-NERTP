<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\AuditLog;
use App\Models\Candidate;
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
        $cacheKey = 'admin_dashboard_stats';
        $statistics = Cache::remember($cacheKey, 30, function () {
            return [
                'totalUsers'      => User::count(),
                'totalStations'   => PollingStation::count(),
                'activeElections' => Election::where('status', 'active')->count(),
            ];
        });

        return Inertia::render('Admin/Dashboard', [
            'auth'        => ['user' => Auth::user()],
            'statistics'  => $statistics,
            'systemStatus' => ['status' => 'Running'],
        ]);
    })->name('dashboard');

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::get('/users', function (Request $request) {
        $search = trim((string) $request->query('search', ''));
        $role   = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));

        $users = User::with('roles')
            ->leftJoin('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('users.*')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('users.phone', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->where('roles.name', $role))
            ->when($status !== '', fn ($query) => $query->where('users.status', $status))
            ->orderByRaw("CASE roles.name
                WHEN 'iec-administrator'     THEN 1
                WHEN 'iec-chairman'          THEN 2
                WHEN 'admin-area-approver'   THEN 3
                WHEN 'constituency-approver' THEN 4
                WHEN 'ward-approver'         THEN 5
                WHEN 'polling-officer'       THEN 6
                WHEN 'party-representative'  THEN 7
                WHEN 'election-monitor'      THEN 8
                ELSE 9 END")
            ->orderBy('users.name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'auth'    => ['user' => Auth::user()],
            'users'   => $users,
            'filters' => [
                'search' => $search,
                'role'   => $role,
                'status' => $status,
            ],
        ]);
    })->name('users')->middleware('permission:manage-users');

    Route::get('/users/create', function () {
        return Inertia::render('Admin/UserCreate', [
            'auth'            => ['user' => Auth::user()],
            'pollingStations' => PollingStation::select('id', 'name', 'code')->orderBy('code')->get(),
            'wards'           => AdministrativeHierarchy::where('level', 'ward')->select('id', 'name')->orderBy('name')->get(),
            'constituencies'  => AdministrativeHierarchy::where('level', 'constituency')->select('id', 'name')->orderBy('name')->get(),
            'adminAreas'      => AdministrativeHierarchy::where('level', 'admin_area')->select('id', 'name')->orderBy('name')->get(),
        ]);
    })->name('users.create')->middleware(['permission:manage-users', 'permission:assign-roles']);

    Route::post('/users', function (Request $request) {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|string|max:20',
            'password' => 'required|string|min:8',
            'role'     => 'required|string',
        ]);
        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'phone'    => $request->phone,
                'password' => bcrypt($request->password),
                'status'   => 'active',
            ]);
            $user->assignRole($request->role);

            if ($request->role === 'polling-officer' && $request->polling_station_id) {
                PollingStation::where('id', $request->polling_station_id)
                    ->update(['assigned_officer_id' => $user->id]);
            }

            if (in_array($request->role, ['ward-approver', 'constituency-approver', 'admin-area-approver'])) {
                $fieldMap = [
                    'ward-approver'         => ['field' => 'ward_id',         'level' => 'ward'],
                    'constituency-approver' => ['field' => 'constituency_id', 'level' => 'constituency'],
                    'admin-area-approver'   => ['field' => 'admin_area_id',   'level' => 'admin_area'],
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
    })->name('users.store')->middleware(['permission:manage-users', 'permission:assign-roles']);

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
    })->name('users.edit')->middleware(['permission:manage-users', 'permission:assign-roles']);

    Route::put('/users/{user}', function (Request $request, User $user) {
        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email,'.$user->id,
            'phone'  => 'required|string|max:20',
            'status' => 'required|in:active,inactive,suspended',
            'role'   => 'required|string',
        ]);
        try {
            $user->update([
                'name'   => $request->name,
                'email'  => $request->email,
                'phone'  => $request->phone,
                'status' => $request->status,
            ]);
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
    })->name('users.update')->middleware(['permission:manage-users', 'permission:assign-roles']);

    Route::delete('/users/{user}', function (User $user) {
        try {
            if ($user->id === Auth::id()) {
                return back()->withErrors(['error' => 'You cannot delete your own account.']);
            }
            AuditLog::record(action: 'user.deleted', event: 'deleted', module: 'UserManagement', auditable: $user);
            $user->delete();
            return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    })->name('users.destroy')->middleware(['permission:manage-users', 'permission:deactivate-user']);

    // ── Party Representatives ─────────────────────────────────────────────────
    Route::get('/party-representatives', function () {
        return Inertia::render('Admin/PartyRepresentatives', [
            'auth'            => ['user' => Auth::user()],
            'representatives' => \App\Models\PartyRepresentative::with(['user', 'politicalParty', 'pollingStations'])->paginate(20),
            'flash'           => session()->only(['success', 'error']),
        ]);
    })->name('party-representatives')->middleware('permission:register-parties');

    Route::get('/party-representatives/create', function () {
        // Fall back to any non-archived election if no active one
        $election = Election::where('status', 'active')->latest()->first()
            ?? Election::whereNotIn('status', ['archived'])->latest()->first();

        return Inertia::render('Admin/PartyRepresentativeCreate', [
            'auth'            => ['user' => Auth::user()],
            // Removed whereDoesntHave — too restrictive; same user can rep across elections
            'users'           => User::role('party-representative')
                ->select('id', 'name', 'email')
                ->get(),
            'parties'         => PoliticalParty::select('id', 'name')->get(),
            'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
            'hasElection'     => $election !== null,
        ]);
    })->name('party-representatives.create')->middleware('permission:register-parties');

    Route::get('/party-representatives/{id}/edit', function ($id) {
        $rep = \App\Models\PartyRepresentative::with(['user', 'politicalParty', 'pollingStations'])->findOrFail($id);
        return Inertia::render('Admin/PartyRepresentativeEdit', [
            'auth'            => ['user' => Auth::user()],
            'representative'  => $rep,
            'parties'         => PoliticalParty::select('id', 'name')->get(),
            'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
        ]);
    })->name('party-representatives.edit')->middleware('permission:register-parties');

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
            DB::table('party_representative_polling_station')
                ->where('party_representative_id', $rep->id)->delete();
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
    })->name('party-representatives.update')->middleware('permission:register-parties');

    Route::post('/party-representatives', function (Request $request) {
        $request->validate([
            'user_id'               => 'required|exists:users,id',
            'political_party_id'    => 'required|exists:political_parties,id',
            'polling_station_ids'   => 'required|array|min:1',
            'polling_station_ids.*' => 'exists:polling_stations,id',
            'designation'           => 'nullable|string|max:255',
        ]);
        try {
            // Fall back to any non-archived election if no active one exists
            $election = Election::where('status', 'active')->first()
                ?? Election::whereNotIn('status', ['archived'])->latest()->first();

            if (!$election) {
                return back()->withErrors(['error' => 'No elections found. Please create an election first before adding party representatives.']);
            }

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
    })->name('party-representatives.store')->middleware('permission:register-parties');

    // ── DELETE Party Representative ───────────────────────────────────────────
    Route::delete('/party-representatives/{id}', function ($id) {
        try {
            $rep = \App\Models\PartyRepresentative::findOrFail($id);
            DB::table('party_representative_polling_station')
                ->where('party_representative_id', $rep->id)->delete();
            AuditLog::record(action: 'party_representative.deleted', event: 'deleted', module: 'UserManagement');
            $rep->delete();
            return redirect()->route('admin.party-representatives')->with('success', 'Party representative removed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove: ' . $e->getMessage()]);
        }
    })->name('party-representatives.destroy')->middleware('permission:register-parties');

    // ── Election Monitors ─────────────────────────────────────────────────────
    Route::get('/election-monitors', function () {
        return Inertia::render('Admin/ElectionMonitors', [
            'auth'     => ['user' => Auth::user()],
            'monitors' => \App\Models\ElectionMonitor::with(['user', 'pollingStations'])->paginate(20),
            'flash'    => session()->only(['success', 'error']),
        ]);
    })->name('election-monitors')->middleware('permission:manage-election-monitors');

    Route::get('/election-monitors/create', function () {
        // Fall back to any non-archived election if no active one
        $election = Election::where('status', 'active')->latest()->first()
            ?? Election::whereNotIn('status', ['archived'])->latest()->first();

        return Inertia::render('Admin/ElectionMonitorCreate', [
            'auth'            => ['user' => Auth::user()],
            // Removed whereDoesntHave — too restrictive
            'users'           => User::role('election-monitor')
                ->select('id', 'name', 'email')->get(),
            'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
            'hasElection'     => $election !== null,
        ]);
    })->name('election-monitors.create')->middleware('permission:manage-election-monitors');

    Route::get('/election-monitors/{id}/edit', function ($id) {
        $monitor = \App\Models\ElectionMonitor::with(['user', 'pollingStations'])->findOrFail($id);
        return Inertia::render('Admin/ElectionMonitorEdit', [
            'auth'            => ['user' => Auth::user()],
            'monitor'         => $monitor,
            'pollingStations' => PollingStation::select('id', 'name', 'code')->get(),
        ]);
    })->name('election-monitors.edit')->middleware('permission:manage-election-monitors');

    Route::put('/election-monitors/{id}', function (Request $request, $id) {
        $monitor = \App\Models\ElectionMonitor::findOrFail($id);
        $request->validate([
            'organization'          => 'nullable|string|max:255',
            'type'                  => 'required|in:domestic,international,civil_society',
            'is_active'             => 'boolean',
            'polling_station_ids'   => 'required|array|min:1',
            'polling_station_ids.*' => 'exists:polling_stations,id',
        ]);
        try {
            $monitor->update([
                'organization' => $request->organization,
                'type'         => $request->type,
                'is_active'    => $request->boolean('is_active', true),
            ]);
            DB::table('election_monitor_polling_station')
                ->where('election_monitor_id', $monitor->id)->delete();
            foreach ($request->polling_station_ids as $sid) {
                DB::table('election_monitor_polling_station')->insert([
                    'election_monitor_id' => $monitor->id,
                    'polling_station_id'  => $sid,
                    'assigned_at'         => now(),
                ]);
            }
            AuditLog::record(action: 'election_monitor.updated', event: 'updated', module: 'UserManagement', auditable: $monitor);
            return redirect()->route('admin.election-monitors')->with('success', 'Election monitor updated!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('election-monitors.update')->middleware('permission:manage-election-monitors');

    Route::post('/election-monitors', function (Request $request) {
        $request->validate([
            'user_id'               => 'required|exists:users,id',
            'organization'          => 'nullable|string|max:255',
            'type'                  => 'required|in:domestic,international,civil_society',
            'polling_station_ids'   => 'required|array|min:1',
            'polling_station_ids.*' => 'exists:polling_stations,id',
        ]);
        try {
            // Fall back to any non-archived election if no active one exists
            $election = Election::where('status', 'active')->first()
                ?? Election::whereNotIn('status', ['archived'])->latest()->first();

            if (!$election) {
                return back()->withErrors(['error' => 'No elections found. Please create an election first before adding election monitors.']);
            }

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
            if (!$user->hasRole('election-monitor')) $user->assignRole('election-monitor');
            return redirect()->route('admin.election-monitors')->with('success', 'Election monitor created!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
        }
    })->name('election-monitors.store')->middleware('permission:manage-election-monitors');

    // ── DELETE Election Monitor ───────────────────────────────────────────────
    Route::delete('/election-monitors/{id}', function ($id) {
        try {
            $monitor = \App\Models\ElectionMonitor::findOrFail($id);
            DB::table('election_monitor_polling_station')
                ->where('election_monitor_id', $monitor->id)->delete();
            AuditLog::record(action: 'election_monitor.deleted', event: 'deleted', module: 'UserManagement');
            $monitor->delete();
            return redirect()->route('admin.election-monitors')->with('success', 'Election monitor removed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove: ' . $e->getMessage()]);
        }
    })->name('election-monitors.destroy')->middleware('permission:manage-election-monitors');

    // ── Roles ─────────────────────────────────────────────────────────────────
    Route::get('/roles', function () {
        $roles          = \Spatie\Permission\Models\Role::with('permissions')->get();
        $allPermissions = \Spatie\Permission\Models\Permission::orderBy('name')->get();
        return Inertia::render('Admin/Roles', [
            'auth'           => ['user' => Auth::user()],
            'roles'          => $roles,
            'allPermissions' => $allPermissions,
        ]);
    })->name('roles')->middleware('permission:assign-roles');

    Route::post('/roles/{id}/permissions', function (Request $request, $id) {
        $role = \Spatie\Permission\Models\Role::findOrFail($id);
        $role->syncPermissions($request->permissions ?? []);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        return back()->with('success', "Permissions updated for role: {$role->name}");
    })->name('roles.permissions.update')->middleware('permission:assign-roles');

    // ── Elections ─────────────────────────────────────────────────────────────
    Route::get('/elections', function () {
        $elections = Election::with('participatingParties')->latest()->get()->map(fn($e) => [
            'id'                  => $e->id,
            'name'                => $e->name,
            'type'                => $e->type,
            'date'                => $e->start_date?->format('Y-m-d'),
            'status'              => $e->status,
            'allow_provisional_public_display' => $e->allow_provisional_public_display,
            'participating_parties' => $e->participatingParties->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'abbreviation' => $p->abbreviation,
                'color'        => $p->color,
            ]),
        ]);

        $allParties = PoliticalParty::select('id', 'name', 'abbreviation', 'color')->orderBy('name')->get();

        return Inertia::render('Admin/Elections', [
            'auth'      => ['user' => Auth::user()],
            'elections' => $elections,
            'allParties' => $allParties,
            'flash'     => session()->only(['success', 'error']),
        ]);
    })->name('elections')->middleware('permission:create-election|edit-election');

    Route::get('/elections/create', function () {
        return Inertia::render('Admin/ElectionCreate', [
            'auth'       => ['user' => Auth::user()],
            'allParties' => PoliticalParty::select('id', 'name', 'abbreviation', 'color')->orderBy('name')->get(),
        ]);
    })->name('elections.create')->middleware('permission:create-election');

    Route::post('/elections', function (Request $request) {
        \Log::info('Election creation attempt', $request->all());

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|in:presidential,parliamentary,local,referendum',
            'date'        => 'required|date',
            'allow_provisional_public_display' => 'boolean',
            'party_ids'   => 'nullable|array',
            'party_ids.*' => 'exists:political_parties,id',
        ]);

        \Log::info('Validation passed', $validated);

        try {
            $typeMap = ['local' => 'local_government', 'referendum' => 'by_election'];

            $slug = Str::slug($request->name);
            $existingElection = Election::where('slug', $slug)
                ->withTrashed()
                ->first();

            if ($existingElection) {
                \Log::warning('Duplicate election slug detected', ['slug' => $slug]);
                return back()->withErrors([
                    'error' => 'An election with the name "' . $request->name . '" already exists. Please use a different name.'
                ]);
            }

            \Log::info('Creating election', [
                'name' => $request->name,
                'type' => $typeMap[$request->type] ?? $request->type,
                'slug' => $slug,
            ]);

            $election = Election::create([
                'name'       => $request->name,
                'slug'       => $slug,
                'type'       => $typeMap[$request->type] ?? $request->type,
                'start_date' => $request->date,
                'end_date'   => $request->date,
                'status'     => 'active',
                'allow_provisional_public_display' => $request->boolean('allow_provisional_public_display'),
                'created_by' => Auth::id(),
            ]);

            \Log::info('Election created successfully', ['election_id' => $election->id, 'name' => $election->name]);

            if ($request->party_ids && count($request->party_ids) > 0) {
                $election->participatingParties()->sync($request->party_ids);
                \Log::info('Parties synced', ['count' => count($request->party_ids)]);
            }

            AuditLog::record(action: 'election.created', event: 'created', module: 'ElectionManagement', auditable: $election);

            \Log::info('Redirecting to elections list');
            return redirect()->route('admin.elections')->with('success', 'Election created successfully!');
        } catch (\Exception $e) {
            \Log::error('Election creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return back()->withErrors(['error' => 'Failed to create election: ' . $e->getMessage()]);
        }
    })->name('elections.store')->middleware('permission:create-election');

    Route::put('/elections/{election}', function (Request $request, Election $election) {
        $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|in:presidential,parliamentary,local,referendum',
            'date'        => 'required|date',
            'status'      => 'required|in:draft,configured,active,results_pending,certifying,certified,archived',
            'allow_provisional_public_display' => 'boolean',
            'party_ids'   => 'nullable|array',
            'party_ids.*' => 'exists:political_parties,id',
        ]);
        try {
            $typeMap = ['local' => 'local_government', 'referendum' => 'by_election'];
            $election->update([
                'name'       => $request->name,
                'type'       => $typeMap[$request->type] ?? $request->type,
                'start_date' => $request->date,
                'end_date'   => $request->date,
                'status'     => $request->status,
                'allow_provisional_public_display' => $request->boolean('allow_provisional_public_display'),
            ]);

            $election->participatingParties()->sync($request->party_ids ?? []);

            AuditLog::record(action: 'election.updated', event: 'updated', module: 'ElectionManagement', auditable: $election);
            return redirect()->route('admin.elections')->with('success', 'Election updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update election: ' . $e->getMessage()]);
        }
    })->name('elections.update')->middleware('permission:edit-election');

    Route::patch('/elections/{election}/toggle-status', function (Election $election) {
        $newStatus = $election->status === 'archived' ? 'active' : 'archived';
        $election->update(['status' => $newStatus]);
        AuditLog::record(action: "election.{$newStatus}", event: 'updated', module: 'ElectionManagement', auditable: $election,
            extra: ['outcome' => 'success', 'new_status' => $newStatus]);
        $verb = $newStatus === 'active' ? 'activated' : 'deactivated';
        return redirect()->route('admin.elections')->with('success', "Election {$verb} successfully!");
    })->name('elections.toggle-status')->middleware('permission:edit-election');

    Route::delete('/elections/{election}', function (Election $election) {
        try {
            AuditLog::record(
                action: 'election.deleted',
                event: 'deleted',
                module: 'ElectionManagement',
                auditable: $election,
                extra: ['outcome' => 'success', 'election_name' => $election->name]
            );
            $election->delete();
            return redirect()->route('admin.elections')
                ->with('success', "Election \"{$election->name}\" deleted. All related data (parties, stations, candidates) has been preserved.");
        } catch (\Exception $e) {
            return redirect()->route('admin.elections')
                ->with('error', 'Failed to delete election: ' . $e->getMessage());
        }
    })->name('elections.destroy')->middleware('permission:edit-election');

    Route::delete('/elections/{id}/force', function ($id) {
        try {
            $election = Election::findOrFail($id);
            AuditLog::record(
                action: 'election.force_deleted',
                event: 'deleted',
                module: 'ElectionManagement',
                extra: [
                    'outcome'       => 'success',
                    'election_id'   => $election->id,
                    'election_name' => $election->name,
                ]
            );
            $election->delete();
            return redirect()->route('admin.elections')
                ->with('success', "Election \"{$election->name}\" deleted successfully. All related data has been preserved.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete election: ' . $e->getMessage()]);
        }
    })->name('elections.force-destroy')->middleware('permission:edit-election');

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
    })->name('polling-stations')->middleware('permission:manage-polling-stations');

    Route::get('/polling-stations/create', function () {
        $activeElection = Election::where('status', 'active')->latest()->first();
        return Inertia::render('Admin/PollingStationCreate', [
            'auth'             => ['user' => Auth::user()],
            'hasActiveElection'=> $activeElection !== null,
            'wards'            => AdministrativeHierarchy::where('level', 'ward')->get(['id', 'name']),
            'officers'         => User::role('polling-officer')
                ->whereDoesntHave('assignedStation')
                ->select('id', 'name', 'email')->get(),
            'election'         => $activeElection ? ['id' => $activeElection->id, 'name' => $activeElection->name] : null,
        ]);
    })->name('polling-stations.create')->middleware('permission:manage-polling-stations');

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

        $election = Election::where('status', 'active')->first();
        if (!$election) {
            return back()->withErrors(['error' => 'No active election found. Please create and activate an election before registering polling stations.']);
        }

        try {
            PollingStation::create([
                'code'                => strtoupper($request->code),
                'name'                => $request->name,
                'address'             => $request->address,
                'ward_id'             => $request->ward_id,
                'election_id'         => $election->id,
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
    })->name('polling-stations.store')->middleware('permission:manage-polling-stations');

    Route::get('/polling-stations/{id}/edit', function ($id) {
        $station = PollingStation::with(['ward', 'assignedOfficer'])->findOrFail($id);
        return Inertia::render('Admin/PollingStationEdit', [
            'auth'     => ['user' => Auth::user()],
            'station'  => $station,
            'wards'    => AdministrativeHierarchy::where('level', 'ward')->get(['id', 'name']),
            'officers' => User::role('polling-officer')->select('id', 'name', 'email')->get(),
            'election' => Election::where('status', 'active')->first(['id', 'name']),
        ]);
    })->name('polling-stations.edit')->middleware('permission:manage-polling-stations');

    Route::put('/polling-stations/{id}', function (Request $request, $id) {
        $station = PollingStation::findOrFail($id);
        $request->validate([
            'code'                => 'required|string|unique:polling_stations,code,'.$id,
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
            $station->update([
                'code'                => strtoupper($request->code),
                'name'                => $request->name,
                'address'             => $request->address,
                'ward_id'             => $request->ward_id,
                'latitude'            => $request->latitude,
                'longitude'           => $request->longitude,
                'registered_voters'   => $request->registered_voters,
                'assigned_officer_id' => $request->assigned_officer_id ?: null,
                'is_active'           => $request->boolean('is_active', true),
                'is_test_station'     => $request->boolean('is_test_station', false),
            ]);
            AuditLog::record(action: 'polling_station.updated', event: 'updated', module: 'PollingStation', auditable: $station);
            return redirect()->route('admin.polling-stations')->with('success', 'Polling station updated!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
        }
    })->name('polling-stations.update')->middleware('permission:manage-polling-stations');

    Route::delete('/polling-stations/{id}', function ($id) {
        try {
            $station = PollingStation::findOrFail($id);
            if ($station->results()->exists()) {
                return back()->withErrors(['error' => 'Cannot delete: this station has submitted results. Remove results first.']);
            }
            $station->delete();
            AuditLog::record(action: 'polling_station.deleted', event: 'deleted', module: 'PollingStation');
            return redirect()->route('admin.polling-stations')->with('success', 'Polling station deleted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('polling-stations.destroy')->middleware('permission:manage-polling-stations');

    // ── Parties ───────────────────────────────────────────────────────────────
    Route::get('/parties', function () {
        $activeElection = Election::where('status', 'active')->latest()->first();

        $parties = PoliticalParty::withTrashed(false)
            ->with(['candidates', 'elections'])
            ->orderBy('name')
            ->get()
            ->map(function ($party) use ($activeElection) {
                $candidates = $activeElection
                    ? Candidate::where('political_party_id', $party->id)
                        ->where('election_id', $activeElection->id)
                        ->get()
                        ->map(fn($c) => [
                            'id'            => $c->id,
                            'name'          => $c->name,
                            'ballot_number' => $c->ballot_number,
                            'photo_url'     => $c->photo_path ? asset('storage/' . $c->photo_path) : null,
                        ])
                    : Candidate::where('political_party_id', $party->id)
                        ->get()
                        ->map(fn($c) => [
                            'id'            => $c->id,
                            'name'          => $c->name,
                            'ballot_number' => $c->ballot_number,
                            'photo_url'     => $c->photo_path ? asset('storage/' . $c->photo_path) : null,
                        ]);

                return [
                    'id'                      => $party->id,
                    'name'                    => $party->name,
                    'abbreviation'            => $party->abbreviation,
                    'color'                   => $party->color,
                    'colors_array'            => $party->colors_array,
                    'leader_name'             => $party->leader_name,
                    'leader_photo_path'       => $party->leader_photo_path,
                    'leader_photo_url'        => $party->leader_photo_path ? asset('storage/' . $party->leader_photo_path) : null,
                    'symbol_path'             => $party->symbol_path,
                    'symbol_url'              => $party->symbol_path ? asset('storage/' . $party->symbol_path) : null,
                    'motto'                   => $party->motto,
                    'headquarters'            => $party->headquarters,
                    'website'                 => $party->website,
                    'candidates'              => $candidates,
                    'participating_elections' => $party->elections->map(fn($e) => [
                        'id'     => $e->id,
                        'name'   => $e->name,
                        'status' => $e->status,
                    ]),
                ];
            });

        $allElections = Election::whereNotIn('status', ['archived'])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'status']);

        return Inertia::render('Admin/Parties', [
            'auth'           => ['user' => Auth::user()],
            'parties'        => $parties,
            'flash'          => session()->only(['success', 'error']),
            'activeElection' => $activeElection
                ? ['id' => $activeElection->id, 'name' => $activeElection->name]
                : null,
            'elections'      => $allElections,
        ]);
    })->name('parties')->middleware('permission:register-parties');

    Route::get('/parties/create', function () {
        $activeElection = Election::where('status', 'active')->latest()->first();
        return Inertia::render('Admin/PartyCreate', [
            'auth'             => ['user' => Auth::user()],
            'activeElectionId' => $activeElection?->id,
        ]);
    })->name('parties.create')->middleware('permission:register-parties');

    Route::post('/parties', function (Request $request) {
        $request->validate([
            'name'         => 'required|string|max:255',
            'abbreviation' => 'required|string|max:10',
            'leader_name'  => 'nullable|string|max:255',
            'leader_photo' => 'nullable|image|max:5120',
            'symbol'       => 'nullable|image|max:5120',
            'motto'        => 'nullable|string|max:500',
            'headquarters' => 'nullable|string|max:255',
            'website'      => 'nullable|url|max:255',
        ]);

        $election = Election::where('status', 'active')->first();
        if (!$election) {
            return back()->withErrors([
                'error' => 'No active election found. Please create and activate an election before registering parties.',
            ]);
        }

        try {
            $leaderPhotoPath = null;
            if ($request->hasFile('leader_photo') && $request->file('leader_photo')->isValid()) {
                $leaderPhotoPath = $request->file('leader_photo')->store('party-photos/leaders', 'public');
            }
            $symbolPath = null;
            if ($request->hasFile('symbol') && $request->file('symbol')->isValid()) {
                $symbolPath = $request->file('symbol')->store('party-photos/symbols', 'public');
            }

            $colorsArr = $request->input('colors', []);
            if (is_array($colorsArr) && count(array_filter($colorsArr)) > 0) {
                $colorParts = array_filter($colorsArr);
            } else {
                $colorParts = array_filter([
                    $request->input('color_0'),
                    $request->input('color_1'),
                    $request->input('color_2'),
                ]);
            }
            $colorString = implode(',', array_values($colorParts)) ?: '#3b82f6';

            $party = PoliticalParty::create([
                'election_id'       => $election->id,
                'name'              => $request->name,
                'abbreviation'      => strtoupper($request->abbreviation),
                'slug'              => Str::slug($request->name),
                'color'             => $colorString,
                'leader_name'       => $request->leader_name,
                'leader_photo_path' => $leaderPhotoPath,
                'symbol_path'       => $symbolPath,
                'motto'             => $request->motto,
                'headquarters'      => $request->headquarters,
                'website'           => $request->website,
            ]);

            $election->participatingParties()->syncWithoutDetaching([$party->id]);

            return redirect()->route('admin.parties.edit', $party->id)->with('success', 'Party registered! Now add candidates.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to register party: '.$e->getMessage()]);
        }
    })->name('parties.store')->middleware('permission:register-parties');

    Route::get('/parties/{id}/edit', function ($id) {
        $party          = PoliticalParty::findOrFail($id);
        $activeElection = Election::where('status', 'active')->latest()->first();

        $candidates = $activeElection
            ? Candidate::where('political_party_id', $party->id)
                ->where('election_id', $activeElection->id)
                ->get()
                ->map(fn($c) => [
                    'id'            => $c->id,
                    'name'          => $c->name,
                    'ballot_number' => $c->ballot_number,
                    'photo_url'     => $c->photo_path ? asset('storage/' . $c->photo_path) : null,
                    'election_name' => $activeElection->name,
                ])
                ->values()
                ->toArray()
            : [];

        return Inertia::render('Admin/PartyEdit', [
            'auth'  => ['user' => Auth::user()],
            'party' => array_merge($party->toArray(), [
                'leader_photo_url' => $party->leader_photo_path ? asset('storage/' . $party->leader_photo_path) : null,
                'symbol_url'       => $party->symbol_path ? asset('storage/' . $party->symbol_path) : null,
                'colors_array'     => $party->colors_array,
            ]),
            'candidates'       => $candidates,
            'activeElectionId' => $activeElection?->id,
            'flash'            => session()->only(['success', 'error']),
        ]);
    })->name('parties.edit')->middleware('permission:register-parties');

    Route::post('/parties/{id}/update', function (Request $request, $id) {
        $party = PoliticalParty::findOrFail($id);

        $request->validate([
            'name'         => 'required|string|max:255',
            'abbreviation' => 'required|string|max:10',
            'leader_name'  => 'nullable|string|max:255',
            'leader_photo' => 'nullable|image|max:5120',
            'symbol'       => 'nullable|image|max:5120',
            'motto'        => 'nullable|string|max:500',
            'headquarters' => 'nullable|string|max:255',
            'website'      => 'nullable|url|max:255',
        ]);

        try {
            $colorsArr = $request->input('colors', []);
            if (is_array($colorsArr) && count(array_filter($colorsArr)) > 0) {
                $colorParts = array_filter($colorsArr);
            } else {
                $colorParts = array_filter([
                    $request->input('color_0'),
                    $request->input('color_1'),
                    $request->input('color_2'),
                ]);
            }
            $colorString = implode(',', array_values($colorParts)) ?: ($party->color ?? '#3b82f6');

            $leaderPhotoPath = $party->leader_photo_path;
            if ($request->hasFile('leader_photo') && $request->file('leader_photo')->isValid()) {
                if ($leaderPhotoPath && Storage::disk('public')->exists($leaderPhotoPath)) {
                    Storage::disk('public')->delete($leaderPhotoPath);
                }
                $leaderPhotoPath = $request->file('leader_photo')->store('party-photos/leaders', 'public');
            }

            $symbolPath = $party->symbol_path;
            if ($request->hasFile('symbol') && $request->file('symbol')->isValid()) {
                if ($symbolPath && Storage::disk('public')->exists($symbolPath)) {
                    Storage::disk('public')->delete($symbolPath);
                }
                $symbolPath = $request->file('symbol')->store('party-photos/symbols', 'public');
            }

            $party->update([
                'name'              => $request->name,
                'abbreviation'      => strtoupper($request->abbreviation),
                'slug'              => Str::slug($request->name),
                'color'             => $colorString,
                'leader_name'       => $request->leader_name,
                'leader_photo_path' => $leaderPhotoPath,
                'symbol_path'       => $symbolPath,
                'motto'             => $request->motto,
                'headquarters'      => $request->headquarters,
                'website'           => $request->website,
            ]);

            AuditLog::record(action: 'party.updated', event: 'updated', module: 'PartyManagement', auditable: $party);
            return redirect()->route('admin.parties')->with('success', 'Party updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('parties.update-post')->middleware('permission:register-parties');

    Route::post('/parties/{id}/add-to-election', function (Request $request, $id) {
        $request->validate(['election_id' => 'required|exists:elections,id']);
        try {
            $party    = PoliticalParty::findOrFail($id);
            $election = Election::findOrFail($request->election_id);
            $election->participatingParties()->syncWithoutDetaching([$party->id]);
            AuditLog::record(action: 'party.added_to_election', event: 'updated', module: 'PartyManagement', auditable: $party,
                extra: ['election_id' => $election->id, 'outcome' => 'success']);
            return redirect()->route('admin.parties')->with('success', "Party added to {$election->name}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('parties.add-to-election')->middleware('permission:register-parties');

    Route::delete('/parties/{partyId}/remove-from-election/{electionId}', function ($partyId, $electionId) {
        try {
            $party    = PoliticalParty::findOrFail($partyId);
            $election = Election::findOrFail($electionId);
            $election->participatingParties()->detach($party->id);
            return redirect()->route('admin.parties')->with('success', "Party removed from {$election->name}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('parties.remove-from-election')->middleware('permission:register-parties');

    Route::delete('/parties/{id}', function ($id) {
        try {
            $party = PoliticalParty::findOrFail($id);

            $candidateIds = Candidate::where('political_party_id', $party->id)->pluck('id');
            \App\Models\ResultCandidateVote::whereIn('candidate_id', $candidateIds)->delete();
            \App\Models\PartyAcceptance::where('political_party_id', $party->id)->delete();
            Candidate::where('political_party_id', $party->id)->forceDelete();

            $repIds = \App\Models\PartyRepresentative::where('political_party_id', $party->id)->pluck('id');
            DB::table('party_representative_polling_station')->whereIn('party_representative_id', $repIds)->delete();
            \App\Models\PartyRepresentative::where('political_party_id', $party->id)->delete();

            DB::table('election_political_party')->where('political_party_id', $id)->delete();

            AuditLog::record(action: 'party.deleted', event: 'deleted', module: 'PartyManagement', auditable: $party);
            $party->forceDelete();

            return redirect()->route('admin.parties')->with('success', 'Party deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete party: ' . $e->getMessage()]);
        }
    })->name('parties.destroy')->middleware('permission:register-parties');

    // ── Candidate Management ──────────────────────────────────────────────────
    Route::post('/parties/{party}/candidates', function (Request $request, PoliticalParty $party) {
        $request->validate([
            'name'          => 'required|string|max:255',
            'ballot_number' => 'nullable|string|max:10',
            'election_id'   => 'required|exists:elections,id',
            'photo'         => 'nullable|image|max:5120',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $photoPath = $request->file('photo')->store('candidate-photos', 'public');
        }

        $candidate = Candidate::create([
            'election_id'        => $request->election_id,
            'political_party_id' => $party->id,
            'name'               => $request->name,
            'ballot_number'      => $request->ballot_number,
            'photo_path'         => $photoPath,
            'is_active'          => true,
            'is_independent'     => false,
        ]);

        AuditLog::record(
            action:    'candidate.created',
            event:     'created',
            module:    'ElectionManagement',
            auditable: $candidate,
            extra:     ['outcome' => 'success', 'election_id' => (int) $request->election_id]
        );

        return response()->json([
            'candidate' => [
                'id'            => $candidate->id,
                'name'          => $candidate->name,
                'ballot_number' => $candidate->ballot_number,
                'photo_url'     => $photoPath ? asset('storage/' . $photoPath) : null,
            ]
        ], 201);
    })->name('parties.candidates.store')->middleware('permission:register-candidates');

    Route::delete('/candidates/{candidate}', function (Candidate $candidate) {
        if ($candidate->photo_path && Storage::disk('public')->exists($candidate->photo_path)) {
            Storage::disk('public')->delete($candidate->photo_path);
        }

        AuditLog::record(
            action:    'candidate.deleted',
            event:     'deleted',
            module:    'ElectionManagement',
            auditable: $candidate,
            extra:     ['outcome' => 'success']
        );

        $candidate->delete();
        return response()->json(['success' => true]);
    })->name('candidates.destroy')->middleware('permission:register-candidates');

    // ── Administrative Hierarchy ──────────────────────────────────────────────
    Route::get('/hierarchy/admin-areas', function () {
        $adminAreas = AdministrativeHierarchy::where('level', 'admin_area')
            ->with('election')->withCount('children')->orderBy('name')->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'code'           => $a->code,
                'name'           => $a->name,
                'election_name'  => $a->election->name ?? '—',
                'children_count' => $a->children_count,
            ]);
        return Inertia::render('Admin/Hierarchy/AdminAreas', [
            'auth'       => ['user' => Auth::user()],
            'adminAreas' => $adminAreas,
            'flash'      => session()->only(['success', 'error']),
        ]);
    })->name('hierarchy.admin-areas')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/admin-areas/create', function () {
        return Inertia::render('Admin/Hierarchy/AdminAreaCreate', [
            'auth'      => ['user' => Auth::user()],
            'elections' => Election::whereIn('status', ['active', 'draft', 'configured'])
                ->orderByDesc('created_at')->get(['id', 'name']),
        ]);
    })->name('hierarchy.admin-areas.create')->middleware('permission:configure-workflow');

    Route::post('/hierarchy/admin-areas', function (Request $request) {
        $request->validate([
            'election_id' => 'required|exists:elections,id',
            'code'        => 'required|string|max:20',
            'name'        => 'required|string|max:255',
        ]);
        try {
            AdministrativeHierarchy::create([
                'election_id' => $request->election_id,
                'level'       => 'admin_area',
                'parent_id'   => null,
                'code'        => strtoupper($request->code),
                'name'        => $request->name,
            ]);
            return redirect()->route('admin.hierarchy.admin-areas')->with('success', 'Admin Area registered successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.admin-areas.store')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/admin-areas/{id}/edit', function ($id) {
        $adminArea = AdministrativeHierarchy::findOrFail($id);
        return Inertia::render('Admin/Hierarchy/AdminAreaEdit', [
            'auth'      => ['user' => Auth::user()],
            'adminArea' => $adminArea,
            'elections' => Election::whereIn('status', ['active', 'draft', 'configured'])
                ->orderByDesc('created_at')->get(['id', 'name']),
        ]);
    })->name('hierarchy.admin-areas.edit')->middleware('permission:configure-workflow');

    Route::put('/hierarchy/admin-areas/{id}', function (Request $request, $id) {
        $request->validate([
            'election_id' => 'required|exists:elections,id',
            'code'        => 'required|string|max:20',
            'name'        => 'required|string|max:255',
        ]);
        AdministrativeHierarchy::findOrFail($id)->update([
            'election_id' => $request->election_id,
            'code'        => strtoupper($request->code),
            'name'        => $request->name,
        ]);
        return redirect()->route('admin.hierarchy.admin-areas')->with('success', 'Admin Area updated successfully!');
    })->name('hierarchy.admin-areas.update')->middleware('permission:configure-workflow');

    Route::delete('/hierarchy/admin-areas/{id}', function ($id) {
        try {
            $area = AdministrativeHierarchy::findOrFail($id);
            if ($area->children()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete: this admin area has constituencies. Delete them first.']);
            }
            $area->delete();
            return redirect()->route('admin.hierarchy.admin-areas')->with('success', 'Admin Area deleted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.admin-areas.destroy')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/constituencies', function () {
        $constituencies = AdministrativeHierarchy::where('level', 'constituency')
            ->with('parent')->withCount('children')->orderBy('name')->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'code'           => $c->code,
                'name'           => $c->name,
                'parent_name'    => $c->parent->name ?? '—',
                'children_count' => $c->children_count,
            ]);
        return Inertia::render('Admin/Hierarchy/Constituencies', [
            'auth'           => ['user' => Auth::user()],
            'constituencies' => $constituencies,
            'flash'          => session()->only(['success', 'error']),
        ]);
    })->name('hierarchy.constituencies')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/constituencies/create', function () {
        return Inertia::render('Admin/Hierarchy/ConstituencyCreate', [
            'auth'       => ['user' => Auth::user()],
            'adminAreas' => AdministrativeHierarchy::where('level', 'admin_area')->orderBy('name')->get(['id', 'name']),
        ]);
    })->name('hierarchy.constituencies.create')->middleware('permission:configure-workflow');

    Route::post('/hierarchy/constituencies', function (Request $request) {
        $request->validate([
            'parent_id' => 'required|exists:administrative_hierarchy,id',
            'code'      => 'required|string|max:20',
            'name'      => 'required|string|max:255',
        ]);
        try {
            $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
            AdministrativeHierarchy::create([
                'election_id' => $parent->election_id,
                'level'       => 'constituency',
                'parent_id'   => $request->parent_id,
                'code'        => strtoupper($request->code),
                'name'        => $request->name,
            ]);
            return redirect()->route('admin.hierarchy.constituencies')->with('success', 'Constituency registered successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.constituencies.store')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/constituencies/{id}/edit', function ($id) {
        $constituency = AdministrativeHierarchy::findOrFail($id);
        return Inertia::render('Admin/Hierarchy/ConstituencyEdit', [
            'auth'         => ['user' => Auth::user()],
            'constituency' => $constituency,
            'adminAreas'   => AdministrativeHierarchy::where('level', 'admin_area')->orderBy('name')->get(['id', 'name']),
        ]);
    })->name('hierarchy.constituencies.edit')->middleware('permission:configure-workflow');

    Route::put('/hierarchy/constituencies/{id}', function (Request $request, $id) {
        $request->validate([
            'parent_id' => 'required|exists:administrative_hierarchy,id',
            'code'      => 'required|string|max:20',
            'name'      => 'required|string|max:255',
        ]);
        $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
        AdministrativeHierarchy::findOrFail($id)->update([
            'parent_id'   => $request->parent_id,
            'election_id' => $parent->election_id,
            'code'        => strtoupper($request->code),
            'name'        => $request->name,
        ]);
        return redirect()->route('admin.hierarchy.constituencies')->with('success', 'Constituency updated successfully!');
    })->name('hierarchy.constituencies.update')->middleware('permission:configure-workflow');

    Route::delete('/hierarchy/constituencies/{id}', function ($id) {
        try {
            $c = AdministrativeHierarchy::findOrFail($id);
            if ($c->children()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete: this constituency has wards. Delete them first.']);
            }
            $c->delete();
            return redirect()->route('admin.hierarchy.constituencies')->with('success', 'Constituency deleted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.constituencies.destroy')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/wards', function () {
        $wards = AdministrativeHierarchy::where('level', 'ward')
            ->with(['parent', 'parent.parent'])
            ->withCount(['pollingStations as stations_count'])
            ->orderBy('name')->get()
            ->map(fn($w) => [
                'id'               => $w->id,
                'code'             => $w->code,
                'name'             => $w->name,
                'parent_name'      => $w->parent->name ?? '—',
                'grandparent_name' => $w->parent?->parent->name ?? '—',
                'stations_count'   => $w->stations_count,
            ]);
        return Inertia::render('Admin/Hierarchy/Wards', [
            'auth'  => ['user' => Auth::user()],
            'wards' => $wards,
            'flash' => session()->only(['success', 'error']),
        ]);
    })->name('hierarchy.wards')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/wards/create', function () {
        $constituencies = AdministrativeHierarchy::where('level', 'constituency')
            ->with('parent')->orderBy('name')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'parent_name' => $c->parent?->name ?? null]);
        return Inertia::render('Admin/Hierarchy/WardCreate', [
            'auth'           => ['user' => Auth::user()],
            'constituencies' => $constituencies,
        ]);
    })->name('hierarchy.wards.create')->middleware('permission:configure-workflow');

    Route::post('/hierarchy/wards', function (Request $request) {
        $request->validate([
            'parent_id' => 'required|exists:administrative_hierarchy,id',
            'code'      => 'required|string|max:20',
            'name'      => 'required|string|max:255',
        ]);
        try {
            $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
            AdministrativeHierarchy::create([
                'election_id' => $parent->election_id,
                'level'       => 'ward',
                'parent_id'   => $request->parent_id,
                'code'        => strtoupper($request->code),
                'name'        => $request->name,
            ]);
            return redirect()->route('admin.hierarchy.wards')->with('success', 'Ward registered successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.wards.store')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/wards/{id}/edit', function ($id) {
        $ward = AdministrativeHierarchy::findOrFail($id);
        $constituencies = AdministrativeHierarchy::where('level', 'constituency')
            ->with('parent')->orderBy('name')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'parent_name' => $c->parent->name ?? null]);
        return Inertia::render('Admin/Hierarchy/WardEdit', [
            'auth'           => ['user' => Auth::user()],
            'ward'           => $ward,
            'constituencies' => $constituencies,
        ]);
    })->name('hierarchy.wards.edit')->middleware('permission:configure-workflow');

    Route::put('/hierarchy/wards/{id}', function (Request $request, $id) {
        $request->validate([
            'parent_id' => 'required|exists:administrative_hierarchy,id',
            'code'      => 'required|string|max:20',
            'name'      => 'required|string|max:255',
        ]);
        $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
        AdministrativeHierarchy::findOrFail($id)->update([
            'parent_id'   => $request->parent_id,
            'election_id' => $parent->election_id,
            'code'        => strtoupper($request->code),
            'name'        => $request->name,
        ]);
        return redirect()->route('admin.hierarchy.wards')->with('success', 'Ward updated successfully!');
    })->name('hierarchy.wards.update')->middleware('permission:configure-workflow');

    Route::delete('/hierarchy/wards/{id}', function ($id) {
        try {
            $ward = AdministrativeHierarchy::findOrFail($id);
            if (PollingStation::where('ward_id', $id)->exists()) {
                return back()->withErrors(['error' => 'Cannot delete: this ward has polling stations. Remove them first.']);
            }
            $ward->delete();
            return redirect()->route('admin.hierarchy.wards')->with('success', 'Ward deleted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.wards.destroy')->middleware('permission:configure-workflow');

    // ── Audit Logs ────────────────────────────────────────────────────────────
    Route::get('/audit-logs', function (Request $request) {
        $query = \App\Models\AuditLog::with('user');
        if ($request->filled('user'))      $query->whereHas('user', fn($q) => $q->where('name', 'like', '%'.$request->user.'%')->orWhere('email', 'like', '%'.$request->user.'%'));
        if ($request->filled('action'))    $query->where('action', 'like', '%'.$request->action.'%');
        if ($request->filled('module'))    $query->where('module', $request->module);
        if ($request->filled('outcome'))   $query->where('outcome', $request->outcome);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);
        return Inertia::render('Admin/AuditLogs', [
            'auth'    => ['user' => Auth::user()],
            'logs'    => $query->latest()->paginate(50)->withQueryString(),
            'filters' => $request->only(['user', 'action', 'module', 'outcome', 'date_from', 'date_to']),
        ]);
    })->name('audit-logs')->middleware('permission:view-audit-logs');

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
            'public_results_enabled' => true,
            'provisional_banner'     => true,
            'audit_retention_days'   => 365,
            'session_timeout_minutes'=> 30,
        ],
    ]))->name('settings')->middleware('permission:system-settings');

    Route::post('/settings', function (Request $request) {
        $request->validate([
            'system_name'            => 'required|string|max:255',
            'system_email'           => 'required|email',
            'timezone'               => 'required|string',
            'require_2fa'            => 'boolean',
            'gps_validation_enabled' => 'boolean',
            'max_file_size'          => 'required|integer|min:1024|max:51200',
            'sms_enabled'            => 'boolean',
            'public_results_enabled' => 'boolean',
            'provisional_banner'     => 'boolean',
            'audit_retention_days'   => 'required|integer|min:30|max:2555',
            'session_timeout_minutes'=> 'required|integer|min:5|max:240',
        ]);
        Log::info('System settings updated', $request->all());
        AuditLog::record(
            action: 'settings.updated',
            event: 'updated',
            module: 'System',
            extra: [
                'outcome' => 'success',
                'new_values' => $request->except(['_token']),
            ]
        );
        return back()->with('success', 'Settings saved successfully!');
    })->name('settings.update')->middleware('permission:system-settings');

    // ── System Health ─────────────────────────────────────────────────────────
    Route::get('/system-health', fn() => Inertia::render('Admin/SystemHealth', [
        'auth' => ['user' => Auth::user()],
    ]))->name('system-health')->middleware('permission:system-settings');

    Route::get('/system-health/data', function () {
        $data = [];
        try { DB::connection()->getPdo(); $data['database'] = ['status' => 'online', 'driver' => DB::connection()->getDriverName()]; }
        catch (\Exception $e) { $data['database'] = ['status' => 'offline', 'driver' => 'unknown', 'error' => $e->getMessage()]; }
        try { Cache::put('healthcheck', 1, 5); Cache::get('healthcheck'); $data['cache'] = ['status' => 'online', 'driver' => config('cache.default')]; }
        catch (\Exception $e) { $data['cache'] = ['status' => 'offline', 'driver' => config('cache.default'), 'error' => $e->getMessage()]; }
        try { $data['queue'] = ['status' => 'running', 'pending' => DB::table('jobs')->count(), 'failed' => DB::table('failed_jobs')->count()]; }
        catch (\Exception $e) { $data['queue'] = ['status' => 'unknown', 'pending' => 0, 'failed' => 0]; }
        try { $total = disk_total_space(storage_path()); $free = disk_free_space(storage_path()); $used = $total - $free;
            $data['disk'] = ['total' => round($total/1073741824, 2).' GB', 'free' => round($free/1073741824, 2).' GB', 'used' => round($used/1073741824, 2).' GB', 'used_percentage' => round(($used/$total)*100, 1).'%']; }
        catch (\Exception $e) { $data['disk'] = null; }
        $data['memory'] = ['php_memory_used' => round(memory_get_usage(true)/1048576, 2).' MB', 'php_memory_peak' => round(memory_get_peak_usage(true)/1048576, 2).' MB', 'php_memory_limit' => ini_get('memory_limit')];
        $data['app'] = ['environment' => app()->environment(), 'debug' => config('app.debug'), 'php_version' => PHP_VERSION, 'laravel_version' => app()->version()];
        $logPath = storage_path('logs/laravel.log');
        try { $data['logs'] = ['exists' => file_exists($logPath), 'size' => file_exists($logPath) ? round(filesize($logPath)/1048576, 2).' MB' : '0 MB', 'recent_errors' => file_exists($logPath) ? substr_count(file_get_contents($logPath), '['.now()->format('Y-m-d').']') : 0]; }
        catch (\Exception $e) { $data['logs'] = ['exists' => false, 'size' => '0 MB', 'recent_errors' => 0]; }
        return response()->json($data);
    })->name('system-health.data')->middleware('permission:system-settings');

    // ── Backups ───────────────────────────────────────────────────────────────
    Route::get('/backups', fn() => Inertia::render('Admin/Backups', ['auth' => ['user' => Auth::user()]]))->name('backups')->middleware('permission:system-settings');

    Route::get('/backups/list', function () {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) return response()->json([]);
        $files   = glob($backupDir . '/*.zip') ?: [];
        $backups = collect($files)->map(fn($file) => ['name' => basename($file), 'path' => basename($file), 'size' => round(filesize($file)/1048576, 2).' MB', 'date' => date('Y-m-d H:i:s', filemtime($file))])->sortByDesc('date')->values()->toArray();
        return response()->json($backups);
    })->name('backups.list')->middleware('permission:system-settings');

    Route::post('/backups/create', function () {
        try { Artisan::call('backup:run --only-db'); return response()->json(['success' => true, 'message' => 'Backup created successfully!']); }
        catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Backup failed: '.$e->getMessage()], 500); }
    })->name('backups.create')->middleware('permission:system-settings');

    Route::get('/backups/download', function (Request $request) {
        $filename = basename($request->query('file', ''));
        $path     = storage_path('app/backups/' . $filename);
        if (!$filename || !file_exists($path)) abort(404, 'Backup file not found.');
        return response()->download($path);
    })->name('backups.download')->middleware('permission:system-settings');
});
