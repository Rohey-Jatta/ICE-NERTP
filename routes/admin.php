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
use App\Models\Device;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\PoliticalParty;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\DeviceBindingService;
use App\Http\Controllers\Admin\SettingsController;

Route::middleware(['auth', 'role:iec-administrator'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $cacheKey   = 'admin_dashboard_stats';
        $statistics = Cache::remember($cacheKey, 30, function () {
            return [
                'totalUsers'      => User::count(),
                'totalStations'   => PollingStation::count(),
                'activeElections' => Election::where('status', 'active')->count(),
            ];
        });

        return Inertia::render('Admin/Dashboard', [
            'auth'         => ['user' => Auth::user()],
            'statistics'   => $statistics,
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
            ->when($role !== '', fn ($q) => $q->where('roles.name', $role))
            ->when($status !== '', fn ($q) => $q->where('users.status', $status))
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
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
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
            $admin = Auth::user();
            $user  = User::create([
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

            app(AuditTrailService::class)->record([
                'action'         => 'user.created',
                'event'          => 'created',
                'module'         => 'UserManagement',
                'auditable_type' => User::class,
                'auditable_id'   => $user->id,
                'new_values'     => ['name' => $user->name, 'email' => $user->email, 'role' => $request->role, 'status' => 'active'],
                'description'    => sprintf(
                    'IEC Administrator %s (ID:%d) created user "%s" (ID:%d) with email "%s", '
                    . 'role "%s", phone "%s" at %s.',
                    $admin->name, $admin->id,
                    $user->name, $user->id,
                    $user->email, $request->role, $request->phone,
                    now()->toDateTimeString()
                ),
            ]);

            return redirect()->route('admin.users')->with('success', 'User created successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }
    })->name('users.store')->middleware(['permission:manage-users', 'permission:assign-roles']);

    Route::get('/users/{user}/edit', function (User $user) {
        $devices = Device::where('user_id', $user->id)->orderByDesc('last_used_at')->get()
            ->map(fn($d) => [
                'id'           => $d->id,
                'device_name'  => $d->device_name,
                'device_type'  => $d->device_type,
                'os'           => $d->os,
                'browser'      => $d->browser,
                'is_trusted'   => $d->is_trusted,
                'is_revoked'   => $d->is_revoked,
                'verified_at'  => $d->verified_at?->format('Y-m-d H:i:s'),
                'last_used_at' => $d->last_used_at?->format('Y-m-d H:i:s'),
                'last_used_ip' => $d->last_used_ip,
            ]);

        return Inertia::render('Admin/UserEdit', [
            'auth'                  => ['user' => Auth::user()],
            'user'                  => $user->load('roles'),
            'roles'                 => ['polling-officer','ward-approver','constituency-approver','admin-area-approver','iec-chairman','iec-administrator','party-representative','election-monitor'],
            'pollingStations'       => PollingStation::select('id', 'name', 'code')->orderBy('code')->get(),
            'wards'                 => AdministrativeHierarchy::where('level', 'ward')->select('id', 'name')->get(),
            'constituencies'        => AdministrativeHierarchy::where('level', 'constituency')->select('id', 'name')->get(),
            'adminAreas'            => AdministrativeHierarchy::where('level', 'admin_area')->select('id', 'name')->get(),
            'parties'               => PoliticalParty::select('id', 'name')->get(),
            'devices'               => $devices,
            'requiresDeviceBinding' => Device::roleRequiresBinding($user->getRoleNames()->first() ?? ''),
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
            $admin     = Auth::user();
            $oldRole   = $user->getRoleNames()->first() ?? 'none';
            $oldStatus = $user->status;
            $oldValues = [
                'name'   => $user->name,
                'email'  => $user->email,
                'status' => $user->status,
                'role'   => $oldRole,
            ];

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
                $fieldMap = [
                    'ward-approver'         => 'ward_id',
                    'constituency-approver' => 'constituency_id',
                    'admin-area-approver'   => 'admin_area_id',
                ];
                $field = $fieldMap[$request->role] ?? null;
                if ($field && $request->$field) {
                    AdministrativeHierarchy::where('assigned_approver_id', $user->id)->update(['assigned_approver_id' => null]);
                    AdministrativeHierarchy::where('id', $request->$field)->update(['assigned_approver_id' => $user->id]);
                }
            }

            $changes = [];
            if ($oldRole !== $request->role)           $changes[] = "role: \"{$oldRole}\" → \"{$request->role}\"";
            if ($oldStatus !== $request->status)       $changes[] = "status: \"{$oldStatus}\" → \"{$request->status}\"";
            if ($oldValues['name'] !== $request->name) $changes[] = "name: \"{$oldValues['name']}\" → \"{$request->name}\"";
            if ($oldValues['email'] !== $request->email) $changes[] = "email: \"{$oldValues['email']}\" → \"{$request->email}\"";

            app(AuditTrailService::class)->record([
                'action'         => 'user.updated',
                'event'          => 'updated',
                'module'         => 'UserManagement',
                'auditable_type' => User::class,
                'auditable_id'   => $user->id,
                'old_values'     => $oldValues,
                'new_values'     => ['name' => $request->name, 'email' => $request->email, 'status' => $request->status, 'role' => $request->role],
                'description'    => sprintf(
                    'IEC Administrator %s (ID:%d) updated user "%s" (ID:%d) at %s. Changes: %s.',
                    $admin->name, $admin->id,
                    $user->name, $user->id,
                    now()->toDateTimeString(),
                    empty($changes) ? 'no field changes detected' : implode('; ', $changes)
                ),
            ]);

            return redirect()->route('admin.users')->with('success', 'User updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }
    })->name('users.update')->middleware(['permission:manage-users', 'permission:assign-roles']);

    Route::delete('/users/{user}', function (User $user) {
        try {
            $admin = Auth::user();
            if ($user->id === Auth::id()) {
                return back()->withErrors(['error' => 'You cannot delete your own account.']);
            }

            $deletedRole = $user->getRoleNames()->first() ?? 'none';

            app(AuditTrailService::class)->record([
                'action'         => 'user.deleted',
                'event'          => 'deleted',
                'module'         => 'UserManagement',
                'auditable_type' => User::class,
                'auditable_id'   => $user->id,
                'old_values'     => ['name' => $user->name, 'email' => $user->email, 'role' => $deletedRole, 'status' => $user->status],
                'description'    => sprintf(
                    'IEC Administrator %s (ID:%d) permanently deleted user "%s" (ID:%d, email:"%s", role:"%s") at %s.',
                    $admin->name, $admin->id,
                    $user->name, $user->id,
                    $user->email, $deletedRole,
                    now()->toDateTimeString()
                ),
            ]);

            $user->delete();
            return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    })->name('users.destroy')->middleware(['permission:manage-users', 'permission:deactivate-user']);

    // ── Device Management ─────────────────────────────────────────────────────
    Route::post('/users/{user}/devices/reset', function (User $user, DeviceBindingService $deviceService) {
        try {
            $admin = Auth::user();
            $deviceService->revokeAllDevices($user, Auth::id());

            app(AuditTrailService::class)->record([
                'action'         => 'admin.device.reset',
                'event'          => 'updated',
                'module'         => 'DeviceManagement',
                'auditable_type' => User::class,
                'auditable_id'   => $user->id,
                'description'    => sprintf(
                    'IEC Administrator %s (ID:%d) reset ALL device bindings for user "%s" (ID:%d) at %s. '
                    . 'User may now register a new device on next login.',
                    $admin->name, $admin->id,
                    $user->name, $user->id,
                    now()->toDateTimeString()
                ),
            ]);

            return back()->with('success', "Device binding reset for {$user->name}. They can now log in from a new device.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to reset device: ' . $e->getMessage()]);
        }
    })->name('users.devices.reset')->middleware('permission:manage-users');

    Route::delete('/devices/{device}', function (Device $device, DeviceBindingService $deviceService) {
        try {
            $admin      = Auth::user();
            $targetUser = $device->user;
            $deviceService->revokeDevice(Auth::user(), $device->id);

            app(AuditTrailService::class)->record([
                'action'      => 'admin.device.revoked',
                'event'       => 'updated',
                'module'      => 'DeviceManagement',
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) revoked device "%s" (ID:%d, OS:%s, browser:%s) '
                    . 'belonging to user "%s" (ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $device->device_name, $device->id, $device->os, $device->browser,
                    $targetUser?->name ?? 'Unknown', $targetUser?->id ?? 0,
                    now()->toDateTimeString()
                ),
            ]);

            return back()->with('success', 'Device revoked successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to revoke device: ' . $e->getMessage()]);
        }
    })->name('devices.revoke')->middleware('permission:manage-users');

    // ── Party Representatives ─────────────────────────────────────────────────
    Route::get('/party-representatives', function () {
        return Inertia::render('Admin/PartyRepresentatives', [
            'auth'            => ['user' => Auth::user()],
            'representatives' => \App\Models\PartyRepresentative::with(['user', 'politicalParty', 'pollingStations'])->paginate(20),
            'flash'           => session()->only(['success', 'error']),
        ]);
    })->name('party-representatives')->middleware('permission:register-parties');

    Route::get('/party-representatives/create', function () {
        $election = Election::where('status', 'active')->latest()->first()
            ?? Election::whereNotIn('status', ['archived'])->latest()->first();
        return Inertia::render('Admin/PartyRepresentativeCreate', [
            'auth'            => ['user' => Auth::user()],
            'users'           => User::role('party-representative')->select('id', 'name', 'email')->get(),
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
            $admin = Auth::user();
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

            app(AuditTrailService::class)->record([
                'action'      => 'party_representative.updated',
                'event'       => 'updated',
                'module'      => 'UserManagement',
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) updated party representative "%s" (ID:%d): '
                    . 'party ID set to %d, %d station(s) assigned at %s.',
                    $admin->name, $admin->id,
                    $rep->user?->name ?? 'Unknown', $rep->id,
                    $request->political_party_id,
                    count($request->polling_station_ids),
                    now()->toDateTimeString()
                ),
            ]);

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
            $admin    = Auth::user();
            $election = Election::where('status', 'active')->first()
                ?? Election::whereNotIn('status', ['archived'])->latest()->first();
            if (!$election) {
                return back()->withErrors(['error' => 'No elections found. Please create an election first.']);
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

            $targetUser = User::find($request->user_id);
            app(AuditTrailService::class)->record([
                'action'      => 'party_representative.created',
                'event'       => 'created',
                'module'      => 'UserManagement',
                'election_id' => $election->id,
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) created party representative record for user "%s" (ID:%d) '
                    . 'for election "%s". Party ID: %d, %d station(s) assigned, accreditation: %s.',
                    $admin->name, $admin->id,
                    $targetUser?->name ?? 'Unknown', $request->user_id,
                    $election->name, $request->political_party_id,
                    count($request->polling_station_ids),
                    $rep->accreditation_number
                ),
            ]);

            return redirect()->route('admin.party-representatives')->with('success', 'Party representative created!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
        }
    })->name('party-representatives.store')->middleware('permission:register-parties');

    Route::delete('/party-representatives/{id}', function ($id) {
        try {
            $admin = Auth::user();
            $rep   = \App\Models\PartyRepresentative::findOrFail($id);
            DB::table('party_representative_polling_station')
                ->where('party_representative_id', $rep->id)->delete();

            app(AuditTrailService::class)->record([
                'action'      => 'party_representative.deleted',
                'event'       => 'deleted',
                'module'      => 'UserManagement',
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) removed party representative record for user "%s" (ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $rep->user?->name ?? 'Unknown', $rep->user_id,
                    now()->toDateTimeString()
                ),
            ]);

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
        $election = Election::where('status', 'active')->latest()->first()
            ?? Election::whereNotIn('status', ['archived'])->latest()->first();
        return Inertia::render('Admin/ElectionMonitorCreate', [
            'auth'            => ['user' => Auth::user()],
            'users'           => User::role('election-monitor')->select('id', 'name', 'email')->get(),
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
            $admin = Auth::user();
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

            app(AuditTrailService::class)->record([
                'action'      => 'election_monitor.updated',
                'event'       => 'updated',
                'module'      => 'UserManagement',
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) updated election monitor "%s" (ID:%d): '
                    . 'type "%s", %d station(s) assigned, active=%s at %s.',
                    $admin->name, $admin->id,
                    $monitor->user?->name ?? 'Unknown', $monitor->id,
                    $request->type, count($request->polling_station_ids),
                    $request->boolean('is_active') ? 'yes' : 'no',
                    now()->toDateTimeString()
                ),
            ]);

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
            $admin    = Auth::user();
            $election = Election::where('status', 'active')->first()
                ?? Election::whereNotIn('status', ['archived'])->latest()->first();
            if (!$election) {
                return back()->withErrors(['error' => 'No elections found. Please create an election first.']);
            }

            $monitor = \App\Models\ElectionMonitor::create([
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
            $targetUser = User::find($request->user_id);
            if (!$targetUser->hasRole('election-monitor')) {
                $targetUser->assignRole('election-monitor');
            }

            app(AuditTrailService::class)->record([
                'action'      => 'election_monitor.created',
                'event'       => 'created',
                'module'      => 'UserManagement',
                'election_id' => $election->id,
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) accredited election monitor "%s" (ID:%d, org:"%s", type:"%s") '
                    . 'for election "%s". %d station(s) assigned, accreditation: %s at %s.',
                    $admin->name, $admin->id,
                    $targetUser?->name ?? 'Unknown', $request->user_id,
                    $request->organization ?? 'Independent', $request->type,
                    $election->name, count($request->polling_station_ids),
                    $monitor->accreditation_number,
                    now()->toDateTimeString()
                ),
            ]);

            return redirect()->route('admin.election-monitors')->with('success', 'Election monitor created!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
        }
    })->name('election-monitors.store')->middleware('permission:manage-election-monitors');

    Route::delete('/election-monitors/{id}', function ($id) {
        try {
            $admin   = Auth::user();
            $monitor = \App\Models\ElectionMonitor::findOrFail($id);
            DB::table('election_monitor_polling_station')
                ->where('election_monitor_id', $monitor->id)->delete();

            app(AuditTrailService::class)->record([
                'action'      => 'election_monitor.deleted',
                'event'       => 'deleted',
                'module'      => 'UserManagement',
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) removed election monitor "%s" (ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $monitor->user?->name ?? 'Unknown', $monitor->id,
                    now()->toDateTimeString()
                ),
            ]);

            $monitor->delete();
            return redirect()->route('admin.election-monitors')->with('success', 'Election monitor removed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove: ' . $e->getMessage()]);
        }
    })->name('election-monitors.destroy')->middleware('permission:manage-election-monitors');

    // ── Roles ─────────────────────────────────────────────────────────────────
    Route::get('/roles', function () {
        return Inertia::render('Admin/Roles', [
            'auth'           => ['user' => Auth::user()],
            'roles'          => \Spatie\Permission\Models\Role::with('permissions')->get(),
            'allPermissions' => \Spatie\Permission\Models\Permission::orderBy('name')->get(),
        ]);
    })->name('roles')->middleware('permission:assign-roles');

    Route::post('/roles/{id}/permissions', function (Request $request, $id) {
        $admin = Auth::user();
        $role  = \Spatie\Permission\Models\Role::findOrFail($id);
        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->toArray();

        $role->syncPermissions($request->permissions ?? []);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = collect($request->permissions ?? [])->sort()->values()->toArray();
        $removed        = array_diff($oldPermissions, $newPermissions);
        $added          = array_diff($newPermissions, $oldPermissions);

        app(AuditTrailService::class)->record([
            'action'     => 'role.permissions.updated',
            'event'      => 'updated',
            'module'     => 'UserManagement',
            'old_values' => ['permissions' => $oldPermissions],
            'new_values' => ['permissions' => $newPermissions],
            'description' => sprintf(
                'IEC Administrator %s (ID:%d) updated permissions for role "%s" at %s. '
                . 'Removed: [%s]. Added: [%s]. Full permission set now: [%s].',
                $admin->name, $admin->id,
                $role->name, now()->toDateTimeString(),
                implode(', ', $removed) ?: 'none',
                implode(', ', $added)   ?: 'none',
                implode(', ', $newPermissions) ?: 'none'
            ),
        ]);

        return back()->with('success', "Permissions updated for role: {$role->name}");
    })->name('roles.permissions.update')->middleware('permission:assign-roles');

    // ── Elections ─────────────────────────────────────────────────────────────
    Route::get('/elections', function () {
        $elections  = Election::with('participatingParties')->latest()->get()->map(fn($e) => [
            'id'     => $e->id, 'name' => $e->name, 'type' => $e->type,
            'date'   => $e->start_date?->format('Y-m-d'), 'status' => $e->status,
            'allow_provisional_public_display' => $e->allow_provisional_public_display,
            'participating_parties' => $e->participatingParties->map(fn($p) => [
                'id' => $p->id, 'name' => $p->name, 'abbreviation' => $p->abbreviation, 'color' => $p->color,
            ]),
        ]);
        $allParties = PoliticalParty::select('id', 'name', 'abbreviation', 'color')->orderBy('name')->get();
        return Inertia::render('Admin/Elections', [
            'auth'       => ['user' => Auth::user()],
            'elections'  => $elections,
            'allParties' => $allParties,
            'flash'      => session()->only(['success', 'error']),
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
            $admin   = Auth::user();
            $typeMap = ['local' => 'local_government', 'referendum' => 'by_election'];
            $slug    = Str::slug($request->name);

            if (Election::where('slug', $slug)->withTrashed()->exists()) {
                return back()->withErrors(['error' => 'An election with the name "' . $request->name . '" already exists.']);
            }

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

            if ($request->party_ids && count($request->party_ids) > 0) {
                $election->participatingParties()->sync($request->party_ids);
            }

            app(AuditTrailService::class)->electionEvent(
                action:       'election.created',
                electionId:   $election->id,
                electionName: $election->name,
                description:  sprintf(
                    'IEC Administrator %s (ID:%d) created election "%s" (ID:%d, type:"%s") '
                    . 'scheduled for %s. %d participating party/parties assigned. '
                    . 'Public display: %s.',
                    $admin->name, $admin->id,
                    $election->name, $election->id, $election->type,
                    $request->date,
                    count($request->party_ids ?? []),
                    $request->boolean('allow_provisional_public_display') ? 'enabled' : 'disabled'
                ),
                extra: ['event' => 'created']
            );

            return redirect()->route('admin.elections')->with('success', 'Election created successfully!');
        } catch (\Exception $e) {
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
            $admin     = Auth::user();
            $oldStatus = $election->status;
            $oldValues = ['name' => $election->name, 'type' => $election->type, 'status' => $election->status];
            $typeMap   = ['local' => 'local_government', 'referendum' => 'by_election'];

            $election->update([
                'name'       => $request->name,
                'type'       => $typeMap[$request->type] ?? $request->type,
                'start_date' => $request->date,
                'end_date'   => $request->date,
                'status'     => $request->status,
                'allow_provisional_public_display' => $request->boolean('allow_provisional_public_display'),
            ]);
            $election->participatingParties()->sync($request->party_ids ?? []);

            app(AuditTrailService::class)->electionEvent(
                action:       'election.updated',
                electionId:   $election->id,
                electionName: $election->name,
                description:  sprintf(
                    'IEC Administrator %s (ID:%d) updated election "%s" (ID:%d) at %s. '
                    . 'Status: "%s" → "%s". Type: "%s" → "%s". '
                    . '%d participating parties now assigned.',
                    $admin->name, $admin->id,
                    $election->name, $election->id,
                    now()->toDateTimeString(),
                    $oldStatus, $request->status,
                    $oldValues['type'], $election->type,
                    count($request->party_ids ?? [])
                ),
                extra: ['event' => 'updated', 'old_values' => $oldValues]
            );

            return redirect()->route('admin.elections')->with('success', 'Election updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update election: ' . $e->getMessage()]);
        }
    })->name('elections.update')->middleware('permission:edit-election');

    Route::patch('/elections/{election}/toggle-status', function (Election $election) {
        $admin     = Auth::user();
        $oldStatus = $election->status;
        $newStatus = $election->status === 'archived' ? 'active' : 'archived';

        $election->update(['status' => $newStatus]);

        app(AuditTrailService::class)->electionEvent(
            action:       "election.{$newStatus}",
            electionId:   $election->id,
            electionName: $election->name,
            description:  sprintf(
                'IEC Administrator %s (ID:%d) changed election "%s" (ID:%d) status from "%s" to "%s" at %s.',
                $admin->name, $admin->id,
                $election->name, $election->id,
                $oldStatus, $newStatus,
                now()->toDateTimeString()
            ),
            extra: ['event' => 'updated', 'previous_status' => $oldStatus, 'new_status' => $newStatus]
        );

        $verb = $newStatus === 'active' ? 'activated' : 'deactivated';
        return redirect()->route('admin.elections')->with('success', "Election {$verb} successfully!");
    })->name('elections.toggle-status')->middleware('permission:edit-election');

    Route::delete('/elections/{election}', function (Election $election) {
        try {
            $admin = Auth::user();
            app(AuditTrailService::class)->electionEvent(
                action:       'election.deleted',
                electionId:   $election->id,
                electionName: $election->name,
                description:  sprintf(
                    'IEC Administrator %s (ID:%d) deleted election "%s" (ID:%d, status:"%s") at %s.',
                    $admin->name, $admin->id,
                    $election->name, $election->id, $election->status,
                    now()->toDateTimeString()
                ),
                extra: ['event' => 'deleted', 'old_values' => ['name' => $election->name, 'status' => $election->status]]
            );
            $election->delete();
            return redirect()->route('admin.elections')->with('success', "Election \"{$election->name}\" deleted.");
        } catch (\Exception $e) {
            return redirect()->route('admin.elections')
                ->with('error', 'Failed to delete election: ' . $e->getMessage());
        }
    })->name('elections.destroy')->middleware('permission:edit-election');

    Route::delete('/elections/{id}/force', function ($id) {
        try {
            $admin    = Auth::user();
            $election = Election::findOrFail($id);

            app(AuditTrailService::class)->electionEvent(
                action:       'election.force_deleted',
                electionId:   $election->id,
                electionName: $election->name,
                description:  sprintf(
                    'IEC Administrator %s (ID:%d) FORCE-DELETED election "%s" (ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $election->name, $election->id,
                    now()->toDateTimeString()
                ),
                extra: ['event' => 'deleted']
            );
            $election->delete();
            return redirect()->route('admin.elections')
                ->with('success', "Election \"{$election->name}\" deleted successfully.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete election: ' . $e->getMessage()]);
        }
    })->name('elections.force-destroy')->middleware('permission:edit-election');

    // ── Polling Stations ──────────────────────────────────────────────────────
    Route::get('/polling-stations', function () {
        $stations = PollingStation::with('ward')->get()->map(fn($s) => [
            'id' => $s->id, 'code' => $s->code, 'name' => $s->name,
            'ward' => $s->ward->name ?? 'N/A', 'voters' => $s->registered_voters,
        ]);
        return Inertia::render('Admin/PollingStations', [
            'auth'     => ['user' => Auth::user()],
            'stations' => $stations,
        ]);
    })->name('polling-stations')->middleware('permission:manage-polling-stations');

    Route::get('/polling-stations/create', function () {
        $activeElection = Election::where('status', 'active')->latest()->first();
        return Inertia::render('Admin/PollingStationCreate', [
            'auth'              => ['user' => Auth::user()],
            'hasActiveElection' => $activeElection !== null,
            'wards'             => AdministrativeHierarchy::where('level', 'ward')->get(['id', 'name']),
            'officers'          => User::role('polling-officer')
                ->whereDoesntHave('assignedStation')
                ->select('id', 'name', 'email')->get(),
            'election'          => $activeElection
                ? ['id' => $activeElection->id, 'name' => $activeElection->name]
                : null,
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
            return back()->withErrors(['error' => 'No active election found.']);
        }

        try {
            $admin   = Auth::user();
            $station = PollingStation::create([
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

            $officer = $request->assigned_officer_id
                ? User::find($request->assigned_officer_id)
                : null;

            app(AuditTrailService::class)->record([
                'action'             => 'polling_station.created',
                'event'              => 'created',
                'module'             => 'PollingStation',
                'election_id'        => $election->id,
                'polling_station_id' => $station->id,
                'auditable_type'     => PollingStation::class,
                'auditable_id'       => $station->id,
                'new_values'         => [
                    'code'              => $station->code,
                    'name'              => $station->name,
                    'registered_voters' => $station->registered_voters,
                    'ward_id'           => $station->ward_id,
                    'is_test_station'   => $station->is_test_station,
                ],
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) registered polling station "%s" (code:%s, ID:%d) '
                    . 'with %d registered voters for election "%s". '
                    . 'Assigned officer: %s. Test station: %s.',
                    $admin->name, $admin->id,
                    $station->name, $station->code, $station->id,
                    $station->registered_voters,
                    $election->name,
                    $officer ? "{$officer->name} (ID:{$officer->id})" : 'none',
                    $station->is_test_station ? 'yes' : 'no'
                ),
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
            $admin     = Auth::user();
            $oldValues = [
                'code'                => $station->code,
                'name'                => $station->name,
                'registered_voters'   => $station->registered_voters,
                'assigned_officer_id' => $station->assigned_officer_id,
                'is_active'           => $station->is_active,
            ];

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

            $changes = [];
            if ($oldValues['code'] !== strtoupper($request->code))
                $changes[] = "code: \"{$oldValues['code']}\" → \"" . strtoupper($request->code) . "\"";
            if ((int) $oldValues['registered_voters'] !== (int) $request->registered_voters)
                $changes[] = "registered voters: {$oldValues['registered_voters']} → {$request->registered_voters}";
            if ($oldValues['assigned_officer_id'] !== ($request->assigned_officer_id ?: null))
                $changes[] = 'assigned officer changed';
            if ($oldValues['is_active'] !== $request->boolean('is_active'))
                $changes[] = 'active status changed';

            app(AuditTrailService::class)->record([
                'action'             => 'polling_station.updated',
                'event'              => 'updated',
                'module'             => 'PollingStation',
                'polling_station_id' => $station->id,
                'auditable_type'     => PollingStation::class,
                'auditable_id'       => $station->id,
                'old_values'         => $oldValues,
                'new_values'         => ['code' => $station->code, 'name' => $station->name, 'registered_voters' => $station->registered_voters],
                'description'        => sprintf(
                    'IEC Administrator %s (ID:%d) updated polling station "%s" (code:%s, ID:%d) at %s. Changes: %s.',
                    $admin->name, $admin->id,
                    $station->name, $station->code, $station->id,
                    now()->toDateTimeString(),
                    empty($changes) ? 'no field changes detected' : implode('; ', $changes)
                ),
            ]);

            return redirect()->route('admin.polling-stations')->with('success', 'Polling station updated!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: '.$e->getMessage()]);
        }
    })->name('polling-stations.update')->middleware('permission:manage-polling-stations');

    Route::delete('/polling-stations/{id}', function ($id) {
        try {
            $admin   = Auth::user();
            $station = PollingStation::findOrFail($id);

            if ($station->results()->exists()) {
                return back()->withErrors(['error' => 'Cannot delete: this station has submitted results.']);
            }

            app(AuditTrailService::class)->record([
                'action'             => 'polling_station.deleted',
                'event'              => 'deleted',
                'module'             => 'PollingStation',
                'polling_station_id' => $station->id,
                'old_values'         => ['code' => $station->code, 'name' => $station->name, 'registered_voters' => $station->registered_voters],
                'description'        => sprintf(
                    'IEC Administrator %s (ID:%d) deleted polling station "%s" (code:%s, ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $station->name, $station->code, $station->id,
                    now()->toDateTimeString()
                ),
            ]);

            $station->delete();
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
                        ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'ballot_number' => $c->ballot_number, 'photo_url' => $c->photo_path ? asset('storage/' . $c->photo_path) : null])
                    : collect();
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
                    'participating_elections' => $party->elections->map(fn($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status]),
                ];
            });

        $allElections = Election::whereNotIn('status', ['archived'])->orderByDesc('created_at')->get(['id', 'name', 'status']);

        return Inertia::render('Admin/Parties', [
            'auth'           => ['user' => Auth::user()],
            'parties'        => $parties,
            'flash'          => session()->only(['success', 'error']),
            'activeElection' => $activeElection ? ['id' => $activeElection->id, 'name' => $activeElection->name] : null,
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
            return back()->withErrors(['error' => 'No active election found.']);
        }

        try {
            $admin           = Auth::user();
            $leaderPhotoPath = null;
            if ($request->hasFile('leader_photo') && $request->file('leader_photo')->isValid()) {
                $leaderPhotoPath = $request->file('leader_photo')->store('party-photos/leaders', 'public');
            }
            $symbolPath = null;
            if ($request->hasFile('symbol') && $request->file('symbol')->isValid()) {
                $symbolPath = $request->file('symbol')->store('party-photos/symbols', 'public');
            }

            $colorsArr   = $request->input('colors', []);
            $colorParts  = is_array($colorsArr) && count(array_filter($colorsArr)) > 0
                ? array_filter($colorsArr)
                : array_filter([$request->input('color_0'), $request->input('color_1'), $request->input('color_2')]);
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

            app(AuditTrailService::class)->record([
                'action'      => 'party.created',
                'event'       => 'created',
                'module'      => 'PartyManagement',
                'election_id' => $election->id,
                'new_values'  => ['name' => $party->name, 'abbreviation' => $party->abbreviation, 'color' => $colorString],
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) registered political party "%s" (abbreviation:"%s", ID:%d) '
                    . 'for election "%s". Colors: %s.',
                    $admin->name, $admin->id,
                    $party->name, $party->abbreviation, $party->id,
                    $election->name, $colorString
                ),
            ]);

            return redirect()->route('admin.parties.edit', $party->id)->with('success', 'Party registered! Now add candidates.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to register party: '.$e->getMessage()]);
        }
    })->name('parties.store')->middleware('permission:register-parties');

    Route::get('/parties/{id}/edit', function ($id) {
        $party          = PoliticalParty::findOrFail($id);
        $activeElection = Election::where('status', 'active')->latest()->first();
        $candidates     = $activeElection
            ? Candidate::where('political_party_id', $party->id)->where('election_id', $activeElection->id)->get()
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'ballot_number' => $c->ballot_number, 'photo_url' => $c->photo_path ? asset('storage/' . $c->photo_path) : null, 'election_name' => $activeElection->name])
                ->values()->toArray()
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
            $admin     = Auth::user();
            $oldValues = ['name' => $party->name, 'abbreviation' => $party->abbreviation, 'color' => $party->color];

            $colorsArr   = $request->input('colors', []);
            $colorParts  = is_array($colorsArr) && count(array_filter($colorsArr)) > 0
                ? array_filter($colorsArr)
                : array_filter([$request->input('color_0'), $request->input('color_1'), $request->input('color_2')]);
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

            app(AuditTrailService::class)->record([
                'action'         => 'party.updated',
                'event'          => 'updated',
                'module'         => 'PartyManagement',
                'auditable_type' => PoliticalParty::class,
                'auditable_id'   => $party->id,
                'old_values'     => $oldValues,
                'new_values'     => ['name' => $party->name, 'abbreviation' => $party->abbreviation, 'color' => $colorString],
                'description'    => sprintf(
                    'IEC Administrator %s (ID:%d) updated political party "%s" (ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $party->name, $party->id,
                    now()->toDateTimeString()
                ),
            ]);

            return redirect()->route('admin.parties')->with('success', 'Party updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('parties.update-post')->middleware('permission:register-parties');

    Route::post('/parties/{id}/add-to-election', function (Request $request, $id) {
        $request->validate(['election_id' => 'required|exists:elections,id']);
        try {
            $admin    = Auth::user();
            $party    = PoliticalParty::findOrFail($id);
            $election = Election::findOrFail($request->election_id);
            $election->participatingParties()->syncWithoutDetaching([$party->id]);

            app(AuditTrailService::class)->record([
                'action'      => 'party.added_to_election',
                'event'       => 'updated',
                'module'      => 'PartyManagement',
                'election_id' => $election->id,
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) added party "%s" (ID:%d) to election "%s" (ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $party->name, $party->id,
                    $election->name, $election->id,
                    now()->toDateTimeString()
                ),
            ]);

            return redirect()->route('admin.parties')->with('success', "Party added to {$election->name}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('parties.add-to-election')->middleware('permission:register-parties');

    Route::delete('/parties/{partyId}/remove-from-election/{electionId}', function ($partyId, $electionId) {
        try {
            $admin    = Auth::user();
            $party    = PoliticalParty::findOrFail($partyId);
            $election = Election::findOrFail($electionId);
            $election->participatingParties()->detach($party->id);

            app(AuditTrailService::class)->record([
                'action'      => 'party.removed_from_election',
                'event'       => 'updated',
                'module'      => 'PartyManagement',
                'election_id' => $election->id,
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) removed party "%s" (ID:%d) from election "%s" (ID:%d) at %s.',
                    $admin->name, $admin->id,
                    $party->name, $party->id,
                    $election->name, $election->id,
                    now()->toDateTimeString()
                ),
            ]);

            return redirect()->route('admin.parties')->with('success', "Party removed from {$election->name}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('parties.remove-from-election')->middleware('permission:register-parties');

    Route::delete('/parties/{id}', function ($id) {
        try {
            $admin = Auth::user();
            $party = PoliticalParty::findOrFail($id);

            $candidateIds = Candidate::where('political_party_id', $party->id)->pluck('id');
            \App\Models\ResultCandidateVote::whereIn('candidate_id', $candidateIds)->delete();
            \App\Models\PartyAcceptance::where('political_party_id', $party->id)->delete();
            Candidate::where('political_party_id', $party->id)->forceDelete();

            $repIds = \App\Models\PartyRepresentative::where('political_party_id', $party->id)->pluck('id');
            DB::table('party_representative_polling_station')->whereIn('party_representative_id', $repIds)->delete();
            \App\Models\PartyRepresentative::where('political_party_id', $party->id)->delete();
            DB::table('election_political_party')->where('political_party_id', $id)->delete();

            app(AuditTrailService::class)->record([
                'action'      => 'party.deleted',
                'event'       => 'deleted',
                'module'      => 'PartyManagement',
                'old_values'  => ['name' => $party->name, 'abbreviation' => $party->abbreviation],
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) permanently deleted political party "%s" (abbreviation:"%s", ID:%d) '
                    . 'and all associated candidates, representatives, and acceptances at %s.',
                    $admin->name, $admin->id,
                    $party->name, $party->abbreviation, $party->id,
                    now()->toDateTimeString()
                ),
            ]);

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

        $admin    = Auth::user();
        $election = Election::find($request->election_id);

        app(AuditTrailService::class)->record([
            'action'      => 'candidate.created',
            'event'       => 'created',
            'module'      => 'ElectionManagement',
            'election_id' => (int) $request->election_id,
            'new_values'  => ['name' => $candidate->name, 'ballot_number' => $candidate->ballot_number],
            'description' => sprintf(
                'IEC Administrator %s (ID:%d) added candidate "%s" (ballot #%s, ID:%d) '
                . 'to party "%s" for election "%s" at %s.',
                $admin->name, $admin->id,
                $candidate->name, $candidate->ballot_number ?? 'N/A', $candidate->id,
                $party->name,
                $election?->name ?? 'Unknown',
                now()->toDateTimeString()
            ),
        ]);

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
        $admin    = Auth::user();
        $election = Election::find($candidate->election_id);

        if ($candidate->photo_path && Storage::disk('public')->exists($candidate->photo_path)) {
            Storage::disk('public')->delete($candidate->photo_path);
        }

        app(AuditTrailService::class)->record([
            'action'      => 'candidate.deleted',
            'event'       => 'deleted',
            'module'      => 'ElectionManagement',
            'election_id' => $candidate->election_id,
            'old_values'  => ['name' => $candidate->name, 'ballot_number' => $candidate->ballot_number],
            'description' => sprintf(
                'IEC Administrator %s (ID:%d) removed candidate "%s" (ballot #%s, ID:%d) '
                . 'from election "%s" at %s.',
                $admin->name, $admin->id,
                $candidate->name, $candidate->ballot_number ?? 'N/A', $candidate->id,
                $election?->name ?? 'Unknown',
                now()->toDateTimeString()
            ),
        ]);

        $candidate->delete();
        return response()->json(['success' => true]);
    })->name('candidates.destroy')->middleware('permission:register-candidates');

    // ── Administrative Hierarchy ──────────────────────────────────────────────
    Route::get('/hierarchy/admin-areas', function () {
        $adminAreas = AdministrativeHierarchy::where('level', 'admin_area')
            ->with('election')->withCount('children')->orderBy('name')->get()
            ->map(fn($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'election_name' => $a->election->name ?? '—', 'children_count' => $a->children_count]);
        return Inertia::render('Admin/Hierarchy/AdminAreas', ['auth' => ['user' => Auth::user()], 'adminAreas' => $adminAreas, 'flash' => session()->only(['success', 'error'])]);
    })->name('hierarchy.admin-areas')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/admin-areas/create', function () {
        return Inertia::render('Admin/Hierarchy/AdminAreaCreate', [
            'auth'      => ['user' => Auth::user()],
            'elections' => Election::whereIn('status', ['active', 'draft', 'configured'])->orderByDesc('created_at')->get(['id', 'name']),
        ]);
    })->name('hierarchy.admin-areas.create')->middleware('permission:configure-workflow');

    Route::post('/hierarchy/admin-areas', function (Request $request) {
        $request->validate(['election_id' => 'required|exists:elections,id', 'code' => 'required|string|max:20', 'name' => 'required|string|max:255']);
        try {
            $admin = Auth::user();
            $node  = AdministrativeHierarchy::create(['election_id' => $request->election_id, 'level' => 'admin_area', 'parent_id' => null, 'code' => strtoupper($request->code), 'name' => $request->name]);

            app(AuditTrailService::class)->record([
                'action'      => 'hierarchy.admin_area.created',
                'event'       => 'created',
                'module'      => 'ElectionManagement',
                'election_id' => $request->election_id,
                'description' => sprintf('IEC Administrator %s (ID:%d) created administrative area "%s" (code:%s, ID:%d) at %s.', $admin->name, $admin->id, $node->name, $node->code, $node->id, now()->toDateTimeString()),
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
            'elections' => Election::whereIn('status', ['active', 'draft', 'configured'])->orderByDesc('created_at')->get(['id', 'name']),
        ]);
    })->name('hierarchy.admin-areas.edit')->middleware('permission:configure-workflow');

    Route::put('/hierarchy/admin-areas/{id}', function (Request $request, $id) {
        $request->validate(['election_id' => 'required|exists:elections,id', 'code' => 'required|string|max:20', 'name' => 'required|string|max:255']);
        $node = AdministrativeHierarchy::findOrFail($id);
        $node->update(['election_id' => $request->election_id, 'code' => strtoupper($request->code), 'name' => $request->name]);
        return redirect()->route('admin.hierarchy.admin-areas')->with('success', 'Admin Area updated successfully!');
    })->name('hierarchy.admin-areas.update')->middleware('permission:configure-workflow');

    Route::delete('/hierarchy/admin-areas/{id}', function ($id) {
        try {
            $area = AdministrativeHierarchy::findOrFail($id);
            if ($area->children()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete: this admin area has constituencies.']);
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
            ->map(fn($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'parent_name' => $c->parent->name ?? '—', 'children_count' => $c->children_count]);
        return Inertia::render('Admin/Hierarchy/Constituencies', ['auth' => ['user' => Auth::user()], 'constituencies' => $constituencies, 'flash' => session()->only(['success', 'error'])]);
    })->name('hierarchy.constituencies')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/constituencies/create', function () {
        return Inertia::render('Admin/Hierarchy/ConstituencyCreate', ['auth' => ['user' => Auth::user()], 'adminAreas' => AdministrativeHierarchy::where('level', 'admin_area')->orderBy('name')->get(['id', 'name'])]);
    })->name('hierarchy.constituencies.create')->middleware('permission:configure-workflow');

    Route::post('/hierarchy/constituencies', function (Request $request) {
        $request->validate(['parent_id' => 'required|exists:administrative_hierarchy,id', 'code' => 'required|string|max:20', 'name' => 'required|string|max:255']);
        try {
            $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
            AdministrativeHierarchy::create(['election_id' => $parent->election_id, 'level' => 'constituency', 'parent_id' => $request->parent_id, 'code' => strtoupper($request->code), 'name' => $request->name]);
            return redirect()->route('admin.hierarchy.constituencies')->with('success', 'Constituency registered successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.constituencies.store')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/constituencies/{id}/edit', function ($id) {
        return Inertia::render('Admin/Hierarchy/ConstituencyEdit', [
            'auth'         => ['user' => Auth::user()],
            'constituency' => AdministrativeHierarchy::findOrFail($id),
            'adminAreas'   => AdministrativeHierarchy::where('level', 'admin_area')->orderBy('name')->get(['id', 'name']),
        ]);
    })->name('hierarchy.constituencies.edit')->middleware('permission:configure-workflow');

    Route::put('/hierarchy/constituencies/{id}', function (Request $request, $id) {
        $request->validate(['parent_id' => 'required|exists:administrative_hierarchy,id', 'code' => 'required|string|max:20', 'name' => 'required|string|max:255']);
        $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
        AdministrativeHierarchy::findOrFail($id)->update(['parent_id' => $request->parent_id, 'election_id' => $parent->election_id, 'code' => strtoupper($request->code), 'name' => $request->name]);
        return redirect()->route('admin.hierarchy.constituencies')->with('success', 'Constituency updated successfully!');
    })->name('hierarchy.constituencies.update')->middleware('permission:configure-workflow');

    Route::delete('/hierarchy/constituencies/{id}', function ($id) {
        try {
            $c = AdministrativeHierarchy::findOrFail($id);
            if ($c->children()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete: this constituency has wards.']);
            }
            $c->delete();
            return redirect()->route('admin.hierarchy.constituencies')->with('success', 'Constituency deleted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.constituencies.destroy')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/wards', function () {
        $wards = AdministrativeHierarchy::where('level', 'ward')
            ->with(['parent', 'parent.parent'])->withCount(['pollingStations as stations_count'])
            ->orderBy('name')->get()
            ->map(fn($w) => ['id' => $w->id, 'code' => $w->code, 'name' => $w->name, 'parent_name' => $w->parent->name ?? '—', 'grandparent_name' => $w->parent?->parent->name ?? '—', 'stations_count' => $w->stations_count]);
        return Inertia::render('Admin/Hierarchy/Wards', ['auth' => ['user' => Auth::user()], 'wards' => $wards, 'flash' => session()->only(['success', 'error'])]);
    })->name('hierarchy.wards')->middleware('permission:configure-workflow');

    Route::get('/hierarchy/wards/create', function () {
        $constituencies = AdministrativeHierarchy::where('level', 'constituency')->with('parent')->orderBy('name')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'parent_name' => $c->parent?->name ?? null]);
        return Inertia::render('Admin/Hierarchy/WardCreate', ['auth' => ['user' => Auth::user()], 'constituencies' => $constituencies]);
    })->name('hierarchy.wards.create')->middleware('permission:configure-workflow');

    Route::post('/hierarchy/wards', function (Request $request) {
        $request->validate(['parent_id' => 'required|exists:administrative_hierarchy,id', 'code' => 'required|string|max:20', 'name' => 'required|string|max:255']);
        try {
            $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
            AdministrativeHierarchy::create(['election_id' => $parent->election_id, 'level' => 'ward', 'parent_id' => $request->parent_id, 'code' => strtoupper($request->code), 'name' => $request->name]);
            return redirect()->route('admin.hierarchy.wards')->with('success', 'Ward registered successfully!');
        } catch (\Exception $e) {
            return back()->withError(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.wards.store')->middleware('permission:configure-workflow');    
    
    Route::get('/hierarchy/wards/{id}/edit', function ($id) {
        $constituencies = AdministrativeHierarchy::where('level', 'constituency')->with('parent')->orderBy('name')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'parent_name' => $c->parent?->name ?? null]);
        return Inertia::render('Admin/Hierarchy/WardEdit', [
            'auth'           => ['user' => Auth::user()],
            'ward'           => AdministrativeHierarchy::findOrFail($id),
            'constituencies' => $constituencies,
        ]);
    })->name('hierarchy.wards.edit')->middleware('permission:configure-workflow');

    Route::put('/hierarchy/wards/{id}', function (Request $request, $id) {
        $request->validate([
            'parent_id' => 'required|exists:administrative_hierarchy,id',
            'code'      => 'required|string|max:20',
            'name'      => 'required|string|max:255',
        ]);
        try {
            $parent = AdministrativeHierarchy::findOrFail($request->parent_id);
            AdministrativeHierarchy::findOrFail($id)->update([
                'parent_id'  => $request->parent_id,
                'election_id'=> $parent->election_id,
                'code'       => strtoupper($request->code),
                'name'       => $request->name,
            ]);
            return redirect()->route('admin.hierarchy.wards')->with('success', 'Ward updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.wards.update')->middleware('permission:configure-workflow');

    Route::delete('/hierarchy/wards/{id}', function ($id) {
        try {
            $ward = AdministrativeHierarchy::findOrFail($id);
            if (PollingStation::where('ward_id', $ward->id)->exists()) {
                return back()->withErrors(['error' => 'Cannot delete: this ward has polling stations.']);
            }
            $ward->delete();
            return redirect()->route('admin.hierarchy.wards')->with('success', 'Ward deleted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('hierarchy.wards.destroy')->middleware('permission:configure-workflow');

    // ── Audit Logs ────────────────────────────────────────────────────────────
    Route::get('/audit-logs', function (Request $request) {
        $query = AuditLog::with('user')->latest();

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }
        if ($request->filled('workflow_stage')) {
            $query->where('workflow_stage', $request->workflow_stage);
        }
        if ($request->filled('decision')) {
            $query->where('decision', $request->decision);
        }
        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }
        if ($request->filled('polling_station_id')) {
            $query->where('polling_station_id', $request->polling_station_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $logs = $query->paginate(50)->withQueryString()->through(fn ($log) => [
            'id'                     => $log->id,
            'correlation_id'         => $log->correlation_id,
            'user_name'              => $log->user?->name ?? 'System',
            'user_role'              => $log->user_role,
            'action'                 => $log->action,
            'module'                 => $log->module,
            'description'            => $log->description,
            'workflow_stage'         => $log->workflow_stage,
            'decision'               => $log->decision,
            'previous_custodian_role'=> $log->previous_custodian_role,
            'current_custodian_role' => $log->current_custodian_role,
            'next_custodian_role'    => $log->next_custodian_role,
            'previous_status'        => $log->previous_status,
            'new_status'             => $log->new_status,
            'comment_text'           => $log->comment_text,
            'election_id'            => $log->election_id,
            'polling_station_id'     => $log->polling_station_id,
            'result_id'              => $log->result_id,
            'ip_address'             => $log->ip_address,
            'os'                     => $log->os,
            'browser'                => $log->browser,
            'device_type'            => $log->device_type,
            'outcome'                => $log->outcome,
            'failure_reason'         => $log->failure_reason,
            'old_values'             => $log->old_values,
            'new_values'             => $log->new_values,
            'created_at'             => $log->created_at?->format('Y-m-d H:i:s'),
            'created_at_human'       => $log->created_at?->diffForHumans(),
        ]);

        // Distinct values for filter dropdowns
        $modules        = AuditLog::distinct()->orderBy('module')->pluck('module')->filter()->values();
        $workflowStages = AuditLog::distinct()->whereNotNull('workflow_stage')->pluck('workflow_stage')->filter()->values();
        $decisions      = AuditLog::distinct()->whereNotNull('decision')->pluck('decision')->filter()->values();
        $elections      = Election::orderByDesc('start_date')->get(['id', 'name']);

        return Inertia::render('Admin/AuditLogs', [
            'auth'           => ['user' => Auth::user()],
            'logs'           => $logs,
            'filters'        => $request->only(['user_id', 'module', 'action', 'workflow_stage', 'decision', 'election_id', 'polling_station_id', 'date_from', 'date_to', 'outcome', 'search']),
            'modules'        => $modules,
            'workflowStages' => $workflowStages,
            'decisions'      => $decisions,
            'elections'      => $elections,
        ]);
    })->name('audit-logs')->middleware('permission:view-audit-logs');

    // ── Chain-of-custody timeline for a single result ─────────────────────────
    Route::get('/audit-logs/chain/{resultId}', function (int $resultId) {
        $logs = AuditLog::with('user')
            ->where('result_id', $resultId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($log) => [
                'id'                     => $log->id,
                'action'                 => $log->action,
                'description'            => $log->description,
                'user_name'              => $log->user?->name ?? 'System',
                'user_role'              => $log->user_role,
                'workflow_stage'         => $log->workflow_stage,
                'decision'               => $log->decision,
                'previous_custodian_role'=> $log->previous_custodian_role,
                'current_custodian_role' => $log->current_custodian_role,
                'next_custodian_role'    => $log->next_custodian_role,
                'previous_status'        => $log->previous_status,
                'new_status'             => $log->new_status,
                'comment_text'           => $log->comment_text,
                'ip_address'             => $log->ip_address,
                'os'                     => $log->os,
                'browser'                => $log->browser,
                'outcome'                => $log->outcome,
                'failure_reason'         => $log->failure_reason,
                'created_at'             => $log->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['chain' => $logs, 'result_id' => $resultId]);
    })->name('audit-logs.chain')->middleware('permission:view-audit-logs');

    // ── Audit Log CSV Export ──────────────────────────────────────────────────
    // Streams a UTF-8 CSV of the filtered audit log.
    // Filter params are identical to the Phase 3 filter panel so the export
    // always matches exactly what the user sees on screen.
    Route::get('/audit-logs/export', [
        \App\Http\Controllers\Admin\AuditLogExportController::class,
        'export',
    ])->name('audit-logs.export')->middleware('permission:view-audit-logs');

    // Chain-of-custody CSV for a single result (regulatory/legal use).
    Route::get('/audit-logs/chain/{resultId}/export', [
        \App\Http\Controllers\Admin\AuditLogExportController::class,
        'exportChain',
    ])->name('audit-logs.chain.export')->middleware('permission:view-audit-logs');

// ── Settings ──────────────────────────────────────────────────────────────
    // Backed by App\Models\Setting + App\Services\SettingsService (cached
    // forever, rebuilt on write) and shared globally to every Inertia page
    // via HandleInertiaRequests::share(). See app/Http/Controllers/Admin/SettingsController.php.
    Route::get('/settings', [SettingsController::class, 'edit'])
        ->name('settings')
        ->middleware('permission:system-settings');

    Route::post('/settings', [SettingsController::class, 'update'])
        ->name('settings.update')
        ->middleware('permission:system-settings');

    Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache'])
        ->name('settings.clear-cache')
        ->middleware('permission:system-settings');

    // ── Backups ───────────────────────────────────────────────────────────────
    Route::get('/backups', function () {
        $backups = [];
        if (Storage::disk('local')->exists('backups')) {
            $files = Storage::disk('local')->files('backups');
            foreach (array_reverse($files) as $file) {
                $backups[] = [
                    'name'       => basename($file),
                    'size'       => Storage::disk('local')->size($file),
                    'created_at' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($file)),
                ];
            }
        }
        return Inertia::render('Admin/Backups', [
            'auth'    => ['user' => Auth::user()],
            'backups' => $backups,
        ]);
    })->name('backups');

    Route::post('/backups/create', function () {
        $admin = Auth::user();
        try {
            Artisan::call('backup:run');

            app(AuditTrailService::class)->record([
                'action'      => 'system.backup_created',
                'event'       => 'action',
                'module'      => 'System',
                'description' => sprintf(
                    'IEC Administrator %s (ID:%d) manually triggered a system backup at %s.',
                    $admin->name, $admin->id, now()->toDateTimeString()
                ),
            ]);

            return back()->with('success', 'Backup created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Backup failed: ' . $e->getMessage()]);
        }
    })->name('backups.create');

    // ── System Health ─────────────────────────────────────────────────────────
    Route::get('/system-health', function () {
        return Inertia::render('Admin/SystemHealth', [
            'auth' => ['user' => Auth::user()],
            'health' => [
                'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
                'cache'    => Cache::store()->getStore() !== null ? 'operational' : 'error',
                'storage'  => Storage::disk('public')->exists('.gitignore') ? 'writable' : 'check permissions',
                'queue'    => config('queue.default'),
            ],
        ]);
    })->name('system-health');
});