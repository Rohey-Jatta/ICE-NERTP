<?php

use App\Models\Election;
use App\Models\ElectionMonitor;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Services\AuditTrailService;
use App\Services\ObservationPDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::middleware(['auth', 'role:election-monitor'])
    ->prefix('monitor')
    ->name('monitor.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->with(['pollingStations', 'election'])
            ->first();

        $activeElection = Election::where('status', 'active')->latest()->first();

        $observationStats = ['total' => 0, 'pending' => 0, 'resolved' => 0, 'critical' => 0];
        $assignedStations = [];

        if ($monitor) {
            $assignedStations = $monitor->pollingStations->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ])->values();

            $stationIds = $monitor->pollingStations->pluck('id');

            if ($stationIds->isNotEmpty()) {
                $obsCounts = DB::table('monitor_observations')
                    ->where('election_monitor_id', $monitor->id)
                    ->when($activeElection, fn($q) => $q->where('election_id', $activeElection->id))
                    ->selectRaw(
                        'COUNT(*) as total, '
                        . 'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending, '
                        . 'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved, '
                        . 'SUM(CASE WHEN severity = ? THEN 1 ELSE 0 END) as critical',
                        ['pending', 'resolved', 'critical']
                    )
                    ->first();

                $observationStats = [
                    'total'    => (int) ($obsCounts->total    ?? 0),
                    'pending'  => (int) ($obsCounts->pending  ?? 0),
                    'resolved' => (int) ($obsCounts->resolved ?? 0),
                    'critical' => (int) ($obsCounts->critical ?? 0),
                ];
            }
        }

        return Inertia::render('Monitor/Dashboard', [
            'auth'              => ['user' => $user],
            'monitor'           => $monitor ? [
                'id'           => $monitor->id,
                'organization' => $monitor->organization,
                'type'         => $monitor->type,
                'election'     => $monitor->election ? ['id' => $monitor->election->id, 'name' => $monitor->election->name] : null,
            ] : null,
            'assignedStations'  => $assignedStations,
            'observationStats'  => $observationStats,
            'activeElection'    => $activeElection ? ['id' => $activeElection->id, 'name' => $activeElection->name] : null,
            'noAssignment'      => $monitor === null,
        ]);
    })->name('dashboard');

    // ── Submit Observation ────────────────────────────────────────────────────
    Route::get('/observations/create', function () {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->with('pollingStations')
            ->first();

        if (!$monitor) {
            return redirect()->route('monitor.dashboard')
                ->with('error', 'You are not accredited as an election monitor for any station.');
        }

        $activeElection = Election::where('status', 'active')->latest()->first();

        return Inertia::render('Monitor/ObservationCreate', [
            'auth'            => ['user' => $user],
            'monitor'         => ['id' => $monitor->id, 'organization' => $monitor->organization, 'type' => $monitor->type],
            'assignedStations'=> $monitor->pollingStations->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ])->values(),
            'activeElection'  => $activeElection ? ['id' => $activeElection->id, 'name' => $activeElection->name] : null,
            'observationTypes'=> [
                'irregularity', 'incident', 'violence', 'voter_intimidation',
                'ballot_stuffing', 'equipment_failure', 'access_denied', 'general',
            ],
            'severityLevels'  => ['low', 'medium', 'high', 'critical'],
        ]);
    })->name('observations.create')->middleware('permission:submit-observation');

    Route::post('/observations', function (Request $request) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->with('pollingStations')
            ->first();

        if (!$monitor) {
            return back()->withErrors(['error' => 'You are not accredited as an election monitor.']);
        }

        $validated = $request->validate([
            'polling_station_id' => 'required|exists:polling_stations,id',
            'type'               => 'required|string|in:irregularity,incident,violence,voter_intimidation,ballot_stuffing,equipment_failure,access_denied,general',
            'severity'           => 'required|string|in:low,medium,high,critical',
            'title'              => 'required|string|max:255',
            'description'        => 'required|string|max:5000',
            'occurred_at'        => 'nullable|date',
            'documents'          => 'nullable|array|max:5',
            'documents.*'        => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
        ]);

        // Verify the monitor is assigned to this station
        $assignedStationIds = $monitor->pollingStations->pluck('id');
        if (!$assignedStationIds->contains($validated['polling_station_id'])) {
            return back()->withErrors(['error' => 'You are not assigned to this polling station.']);
        }

        $activeElection = Election::where('status', 'active')->latest()->first();
        if (!$activeElection) {
            return back()->withErrors(['error' => 'No active election. Observations cannot be submitted outside an active election period.']);
        }

        // Handle document uploads
        $documentPaths = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $doc) {
                if ($doc->isValid()) {
                    $documentPaths[] = $doc->store('monitor-observations', 'public');
                }
            }
        }

        try {
            $observation = DB::table('monitor_observations')->insertGetId([
                'election_monitor_id' => $monitor->id,
                'election_id'         => $activeElection->id,
                'polling_station_id'  => $validated['polling_station_id'],
                'type'                => $validated['type'],
                'severity'            => $validated['severity'],
                'title'               => $validated['title'],
                'description'         => $validated['description'],
                'occurred_at'         => $validated['occurred_at'] ?? now(),
                'latitude'            => $validated['latitude'] ?? null,
                'longitude'           => $validated['longitude'] ?? null,
                'status'              => 'pending',
                'documents'           => json_encode($documentPaths),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $station = PollingStation::find($validated['polling_station_id']);

            // Forensic audit record
            app(AuditTrailService::class)->observationSubmitted(
                observationId:    $observation,
                electionId:       $activeElection->id,
                pollingStationId: $validated['polling_station_id'],
                stationCode:      $station?->code ?? (string) $validated['polling_station_id'],
                type:             $validated['type'],
                severity:         $validated['severity'],
                title:            $validated['title']
            );

            // Auto-escalate critical observations to Incidents table
            if ($validated['severity'] === 'critical') {
                $this->createCriticalIncident(
                    $observation,
                    $activeElection->id,
                    $validated,
                    $station,
                    $monitor
                );
            }

            return redirect()->route('monitor.observations.index')
                ->with('success', 'Observation submitted successfully.');

        } catch (\Exception $e) {
            Log::error('[Monitor] Observation submission failed', [
                'monitor_id' => $monitor->id,
                'error'      => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to submit observation: ' . $e->getMessage()]);
        }
    })->name('observations.store')->middleware('permission:submit-observation');

    // ── My Observations ───────────────────────────────────────────────────────
    Route::get('/observations', function () {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)->first();

        if (!$monitor) {
            return Inertia::render('Monitor/Observations', [
                'auth'         => ['user' => $user],
                'observations' => [],
                'noAssignment' => true,
            ]);
        }

        $activeElection = Election::where('status', 'active')->latest()->first();

        $observations = DB::table('monitor_observations as mo')
            ->leftJoin('polling_stations as ps', 'ps.id', '=', 'mo.polling_station_id')
            ->where('mo.election_monitor_id', $monitor->id)
            ->when($activeElection, fn($q) => $q->where('mo.election_id', $activeElection->id))
            ->orderByDesc('mo.created_at')
            ->select([
                'mo.id', 'mo.type', 'mo.severity', 'mo.title',
                'mo.description', 'mo.status', 'mo.occurred_at',
                'mo.created_at', 'mo.documents',
                'ps.name as station_name', 'ps.code as station_code',
            ])
            ->get()
            ->map(fn($o) => [
                'id'           => $o->id,
                'type'         => $o->type,
                'severity'     => $o->severity,
                'title'        => $o->title,
                'description'  => $o->description,
                'status'       => $o->status,
                'station_name' => $o->station_name ?? 'Unknown',
                'station_code' => $o->station_code ?? '—',
                'occurred_at'  => $o->occurred_at,
                'submitted_at' => $o->created_at,
                'document_count' => count(json_decode($o->documents ?? '[]', true)),
            ]);

        return Inertia::render('Monitor/Observations', [
            'auth'         => ['user' => $user],
            'observations' => $observations,
            'noAssignment' => false,
        ]);
    })->name('observations.index')->middleware('permission:submit-observation');

    // ── Single Observation Detail ─────────────────────────────────────────────
    Route::get('/observations/{id}', function (int $id) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)->first();

        if (!$monitor) {
            abort(403, 'Not accredited as an election monitor.');
        }

        $observation = DB::table('monitor_observations as mo')
            ->leftJoin('polling_stations as ps', 'ps.id', '=', 'mo.polling_station_id')
            ->where('mo.id', $id)
            ->where('mo.election_monitor_id', $monitor->id)
            ->select([
                'mo.*',
                'ps.name as station_name',
                'ps.code as station_code',
            ])
            ->first();

        if (!$observation) {
            abort(404, 'Observation not found.');
        }

        $docs     = json_decode($observation->documents ?? '[]', true);
        $docUrls  = array_map(fn($path) => asset('storage/' . $path), $docs);

        return Inertia::render('Monitor/ObservationDetail', [
            'auth'        => ['user' => $user],
            'observation' => [
                'id'           => $observation->id,
                'type'         => $observation->type,
                'severity'     => $observation->severity,
                'title'        => $observation->title,
                'description'  => $observation->description,
                'status'       => $observation->status,
                'station_name' => $observation->station_name ?? 'Unknown',
                'station_code' => $observation->station_code ?? '—',
                'occurred_at'  => $observation->occurred_at,
                'submitted_at' => $observation->created_at,
                'latitude'     => $observation->latitude,
                'longitude'    => $observation->longitude,
                'document_urls'=> $docUrls,
            ],
        ]);
    })->name('observations.show')->middleware('permission:submit-observation');

    // ── Download observation as PDF ───────────────────────────────────────────
    Route::get('/observations/{id}/pdf', function (int $id, ObservationPDFService $pdfService) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)->first();

        if (!$monitor) {
            abort(403);
        }

        $observation = DB::table('monitor_observations')
            ->where('id', $id)
            ->where('election_monitor_id', $monitor->id)
            ->first();

        if (!$observation) {
            abort(404);
        }

        $auditTrail = app(AuditTrailService::class);
        $auditTrail->record([
            'action'      => 'monitor.observation.pdf_downloaded',
            'event'       => 'action',
            'module'      => 'ElectionMonitor',
            'election_id' => $observation->election_id,
            'polling_station_id' => $observation->polling_station_id,
            'description' => sprintf(
                'Election Monitor %s (ID:%d) downloaded PDF for observation ID:%d (type:%s, severity:%s) at %s.',
                $user->name, $user->id,
                $observation->id,
                $observation->type,
                $observation->severity,
                now()->toDateTimeString()
            ),
        ]);

        return $pdfService->downloadSingle($observation);
    })->name('observations.pdf')->middleware('permission:submit-observation');

    // ── Batch PDF download ────────────────────────────────────────────────────
    Route::post('/observations/batch-pdf', function (Request $request, ObservationPDFService $pdfService) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)->first();

        if (!$monitor) {
            abort(403);
        }

        $request->validate([
            'observation_ids'   => 'required|array|min:1|max:50',
            'observation_ids.*' => 'integer|exists:monitor_observations,id',
        ]);

        $observations = DB::table('monitor_observations')
            ->whereIn('id', $request->observation_ids)
            ->where('election_monitor_id', $monitor->id)
            ->get();

        if ($observations->isEmpty()) {
            return back()->withErrors(['error' => 'No accessible observations found for the selected IDs.']);
        }

        app(AuditTrailService::class)->record([
            'action'      => 'monitor.observation.batch_pdf_downloaded',
            'event'       => 'action',
            'module'      => 'ElectionMonitor',
            'description' => sprintf(
                'Election Monitor %s (ID:%d) downloaded batch PDF for %d observations at %s.',
                $user->name, $user->id,
                $observations->count(),
                now()->toDateTimeString()
            ),
        ]);

        return $pdfService->downloadBatch($observations);
    })->name('observations.batch-pdf')->middleware('permission:submit-observation');

    // ── My Assigned Stations ──────────────────────────────────────────────────
    Route::get('/stations', function () {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->with(['pollingStations.ward'])
            ->first();

        if (!$monitor) {
            return Inertia::render('Monitor/Stations', [
                'auth'     => ['user' => $user],
                'stations' => [],
                'monitor'  => null,
            ]);
        }

        $activeElection = Election::where('status', 'active')->latest()->first();

        $stationIds         = $monitor->pollingStations->pluck('id');
        $observationCounts  = $stationIds->isNotEmpty()
            ? DB::table('monitor_observations')
                ->where('election_monitor_id', $monitor->id)
                ->whereIn('polling_station_id', $stationIds)
                ->when($activeElection, fn($q) => $q->where('election_id', $activeElection->id))
                ->selectRaw('polling_station_id, COUNT(*) as count, MAX(severity) as max_severity')
                ->groupBy('polling_station_id')
                ->get()
                ->keyBy('polling_station_id')
            : collect();

        $stations = $monitor->pollingStations->map(fn($s) => [
            'id'                  => $s->id,
            'name'                => $s->name,
            'code'                => $s->code,
            'ward_name'           => $s->ward?->name ?? '—',
            'registered_voters'   => $s->registered_voters,
            'latitude'            => $s->latitude,
            'longitude'           => $s->longitude,
            'observation_count'   => (int) ($observationCounts->get($s->id)?->count ?? 0),
            'max_severity'        => $observationCounts->get($s->id)?->max_severity ?? null,
        ])->values();

        return Inertia::render('Monitor/Stations', [
            'auth'     => ['user' => $user],
            'stations' => $stations,
            'monitor'  => [
                'id'           => $monitor->id,
                'organization' => $monitor->organization,
                'type'         => $monitor->type,
            ],
        ]);
    })->name('stations')->middleware('permission:submit-observation');

    // ── IEC Notifications feed ────────────────────────────────────────────────
    Route::get('/notifications', function () {
        $user      = Auth::user();
        $monitor   = ElectionMonitor::where('user_id', $user->id)->first();
        $elections = Election::whereIn('status', ['active', 'results_pending', 'certified'])
            ->latest()
            ->get(['id', 'name', 'status']);

        $notifications = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'data'       => json_decode($n->data, true),
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        return Inertia::render('Monitor/Notifications', [
            'auth'          => ['user' => $user],
            'notifications' => $notifications,
            'elections'     => $elections,
            'monitor'       => $monitor ? ['id' => $monitor->id, 'organization' => $monitor->organization] : null,
        ]);
    })->name('notifications')->middleware('permission:submit-observation');

    Route::post('/notifications/mark-read', function (Request $request) {
        $user = Auth::user();
        DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->when($request->filled('id'), fn($q) => $q->where('id', $request->id))
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    })->name('notifications.mark-read');
});

// ── Private helper (file-scope, not a route) ──────────────────────────────────
if (!function_exists('createCriticalIncident')) {
    function createCriticalIncident(
        int    $observationId,
        int    $electionId,
        array  $validated,
        ?PollingStation $station,
        ElectionMonitor $monitor
    ): void {
        try {
            $ward         = $station?->ward;
            $constituency = $ward?->parent;
            $adminArea    = $constituency?->parent;

            Incident::create([
                'election_id'              => $electionId,
                'type'                     => 'monitor_critical_observation',
                'administrative_area_id'   => $adminArea?->id,
                'administrative_area_name' => $adminArea?->name,
                'polling_station_id'       => $validated['polling_station_id'],
                'polling_station_name'     => $station?->name,
                'description'              => sprintf(
                    '[CRITICAL] Election Monitor (org: %s) submitted a critical %s observation: "%s". '
                    . 'Station: %s. Observation ID: %d.',
                    $monitor->organization ?? 'Unknown',
                    str_replace('_', ' ', $validated['type']),
                    $validated['title'],
                    $station?->code ?? 'Unknown',
                    $observationId
                ),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Monitor] Failed to auto-create critical incident: ' . $e->getMessage());
        }
    }
}