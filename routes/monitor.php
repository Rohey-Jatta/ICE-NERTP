<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\ElectionMonitor;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\Result;
use App\Services\ObservationPDFService;

Route::middleware(['auth', 'role:election-monitor'])
    ->prefix('monitor')
    ->name('monitor.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['pollingStations', 'election'])
            ->first();

        $stats = [
            'assigned_stations' => 0,
            'observations'      => 0,
            'flagged'           => 0,
            'visited'           => 0,
        ];

        $recentObservations = collect();

        if ($monitor) {
            // Scope cache key to the monitor's election to prevent cross-election pollution
            $cacheKey = "monitor_dashboard_{$monitor->id}_{$monitor->election_id}";
            $dashboardData = Cache::remember($cacheKey, 30, function () use ($monitor) {
                $stats = [
                    'assigned_stations' => $monitor->pollingStations->count(),
                    'observations'      => 0,
                    'flagged'           => 0,
                    'visited'           => 0,
                ];

                // FIXED: Scope ALL observation queries to this monitor's election
                // via election_monitor_id (which is already election-scoped)
                $baseQuery = DB::table('monitor_observations')
                    ->where('election_monitor_id', $monitor->id);

                $stats['observations'] = (clone $baseQuery)->count();
                $stats['flagged'] = (clone $baseQuery)
                    ->whereIn('observation_type', ['irregularity', 'process_concern', 'incident'])
                    ->count();
                $stats['visited'] = (clone $baseQuery)
                    ->distinct('polling_station_id')
                    ->count('polling_station_id');

                $recentObservations = DB::table('monitor_observations')
                    ->where('election_monitor_id', $monitor->id)
                    ->join('polling_stations', 'monitor_observations.polling_station_id', '=', 'polling_stations.id')
                    ->select(
                        'monitor_observations.id',
                        'monitor_observations.title',
                        'monitor_observations.observation_type',
                        'monitor_observations.severity',
                        'monitor_observations.observed_at',
                        'polling_stations.name as station_name',
                        'polling_stations.code as station_code'
                    )
                    ->orderByDesc('monitor_observations.observed_at')
                    ->limit(5)
                    ->get();

                return [
                    'stats'              => $stats,
                    'recentObservations' => $recentObservations,
                ];
            });

            $stats = $dashboardData['stats'];
            $recentObservations = $dashboardData['recentObservations'];
        }

        return Inertia::render('Monitor/Dashboard', [
            'auth'               => ['user' => $user],
            'monitor'            => $monitor,
            'stats'              => $stats,
            'recentObservations' => $recentObservations,
        ]);
    })->name('dashboard');

    // ── Assigned Stations ─────────────────────────────────────────────────────
    Route::get('/stations', function () {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('pollingStations.ward')
            ->first();

        $stations = [];
        if ($monitor) {
            $stationIds = $monitor->pollingStations->pluck('id')->all();

            // Scope results to the monitor's election
            $latestResults = Result::whereIn('polling_station_id', $stationIds)
                ->where('election_id', $monitor->election_id)
                ->orderByDesc('submitted_at')
                ->get()
                ->groupBy('polling_station_id')
                ->map(fn($group) => $group->first());

            $observationCounts = DB::table('monitor_observations')
                ->where('election_monitor_id', $monitor->id)
                ->whereIn('polling_station_id', $stationIds)
                ->select('polling_station_id', DB::raw('COUNT(*) as count'))
                ->groupBy('polling_station_id')
                ->pluck('count', 'polling_station_id');

            $stations = $monitor->pollingStations->map(function ($station) use ($latestResults, $observationCounts) {
                $result = $latestResults->get($station->id);
                $observationCount = $observationCounts->get($station->id, 0);

                return [
                    'id'               => $station->id,
                    'code'             => $station->code,
                    'name'             => $station->name,
                    'address'          => $station->address,
                    'ward'             => $station->ward->name ?? '—',
                    'latitude'         => $station->latitude,
                    'longitude'        => $station->longitude,
                    'registered_voters'=> $station->registered_voters,
                    'result_status'    => $result?->certification_status ?? 'not_reported',
                    'total_votes_cast' => $result?->total_votes_cast,
                    'turnout'          => ($result && $station->registered_voters > 0)
                        ? round(($result->total_votes_cast / $station->registered_voters) * 100, 1)
                        : null,
                    'observations_count' => $observationCount,
                ];
            })->sortBy('code')->values();
        }

        return Inertia::render('Monitor/Stations', [
            'auth'     => ['user' => $user],
            'monitor'  => $monitor,
            'stations' => $stations,
        ]);
    })->name('stations')->middleware('permission:view-assigned-stations');

    // ── Submit Observation ────────────────────────────────────────────────────
    Route::get('/submit-observation', function (Request $request) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('pollingStations')
            ->first();

        $stations = $monitor
            ? $monitor->pollingStations->map(fn($s) => [
                'id'   => $s->id,
                'code' => $s->code,
                'name' => $s->name,
            ])->sortBy('code')->values()
            : collect();

        $preselectedStation = $request->query('station_id');

        return Inertia::render('Monitor/SubmitObservation', [
            'auth'               => ['user' => $user],
            'monitor'            => $monitor,
            'stations'           => $stations,
            'preselectedStation' => $preselectedStation,
        ]);
    })->name('submit-observation')->middleware('permission:submit-observation');

    Route::post('/observations', function (Request $request) {
        $request->validate([
            'polling_station_id' => 'required|exists:polling_stations,id',
            'observation_type'   => 'required|in:general,irregularity,process_concern,positive,incident',
            'title'              => 'required|string|max:255',
            'observation'        => 'required|string|max:5000',
            'severity'           => 'required|in:low,medium,high,critical',
            'observed_at'        => 'required|date',
            'is_public'          => 'boolean',
            'latitude'           => 'nullable|numeric',
            'longitude'          => 'nullable|numeric',
            'photos'             => 'nullable|array|max:5',
            'photos.*'           => 'image|max:5120',
            'documents'          => 'nullable|array|max:10',
            'documents.*'        => 'file|mimes:pdf,doc,docx,xls,xlsx,csv,txt|max:10240',
        ]);

        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$monitor) {
            Log::warning('[Monitor] Observation submission blocked — no active monitor record.', [
                'user_id' => $user->id,
            ]);
            return back()->withErrors(['error' => 'You are not registered as an active election monitor.']);
        }

        // Verify the station is assigned to this monitor
        $isAssigned = DB::table('election_monitor_polling_station')
            ->where('election_monitor_id', $monitor->id)
            ->where('polling_station_id', $request->polling_station_id)
            ->exists();

        if (!$isAssigned) {
            Log::warning('[Monitor] Observation submission blocked — station not assigned to monitor.', [
                'user_id'    => $user->id,
                'monitor_id' => $monitor->id,
                'station_id' => $request->polling_station_id,
            ]);
            return back()->withErrors(['error' => 'You are not assigned to this polling station.']);
        }

        try {
            // Handle photo uploads
            $photoPaths = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store(
                        "monitor-observations/{$monitor->election_id}/{$monitor->id}/photos",
                        'public'
                    );
                    $photoPaths[] = $path;
                }
            }

            // Handle document uploads
            $documentPaths = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $document) {
                    $path = $document->store(
                        "monitor-observations/{$monitor->election_id}/{$monitor->id}/documents",
                        'public'
                    );
                    $documentPaths[] = [
                        'path' => $path,
                        'name' => $document->getClientOriginalName(),
                        'size' => $document->getSize(),
                        'mime' => $document->getMimeType(),
                    ];
                }
            }

            $observationId = DB::table('monitor_observations')->insertGetId([
                'election_monitor_id' => $monitor->id,
                'polling_station_id'  => $request->polling_station_id,
                'election_id'         => $monitor->election_id,  // Always use monitor's election
                'observation_type'    => $request->observation_type,
                'title'               => $request->title,
                'observation'         => $request->observation,
                'severity'            => $request->severity,
                'photo_paths'         => !empty($photoPaths) ? json_encode($photoPaths) : null,
                'documents_paths'     => !empty($documentPaths) ? json_encode($documentPaths) : null,
                'latitude'            => $request->latitude,
                'longitude'           => $request->longitude,
                'is_public'           => $request->boolean('is_public', true),
                'observed_at'         => $request->observed_at,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // ── Auto-create Incident from Observation ─────────────────────
            $incidentType = match ($request->observation_type) {
                'irregularity', 'incident', 'process_concern' => 'dispute',
                default => null,
            };

            if ($incidentType) {
                try {
                    $stationHierarchy = DB::table('polling_stations as ps')
                        ->leftJoin('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
                        ->leftJoin('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
                        ->leftJoin('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
                        ->where('ps.id', $request->polling_station_id)
                        ->select('ps.name as station_name', 'aa.id as area_id', 'aa.name as area_name')
                        ->first();

                    Incident::create([
                        'election_id'              => $monitor->election_id,
                        'observation_id'           => $observationId,
                        'type'                     => $incidentType,
                        'administrative_area_id'   => $stationHierarchy?->area_id,
                        'administrative_area_name' => $stationHierarchy?->area_name,
                        'polling_station_id'       => $request->polling_station_id,
                        'polling_station_name'     => $stationHierarchy?->station_name,
                        'description'              => $request->title,
                    ]);

                    // Bust operations dashboard cache so it reflects immediately
                    Cache::forget('election_operations_dashboard');
                } catch (\Throwable $e) {
                    Log::warning('[Incident] Failed to create dispute incident: ' . $e->getMessage());
                }
            }

            // Always bust the monitor dashboard cache on new observation
            Cache::forget("monitor_dashboard_{$monitor->id}_{$monitor->election_id}");

            AuditLog::record(
                action:    'monitor.observation.submitted',
                event:     'created',
                module:    'ElectionMonitor',
                extra:     [
                    'outcome'            => 'success',
                    'observation_id'     => $observationId,
                    'polling_station_id' => $request->polling_station_id,
                    'observation_type'   => $request->observation_type,
                    'severity'           => $request->severity,
                    'election_id'        => $monitor->election_id,
                    'photo_count'        => count($photoPaths),
                    'document_count'     => count($documentPaths),
                    'latitude'           => $request->latitude,
                    'longitude'          => $request->longitude,
                ]
            );

            $successMessage = 'Observation submitted successfully!';
            if (count($documentPaths) > 0) {
                $successMessage .= ' (' . count($documentPaths) . ' document' . (count($documentPaths) > 1 ? 's' : '') . ' uploaded)';
            }

            return redirect()->route('monitor.observations')->with('success', $successMessage);

        } catch (\Exception $e) {
            Log::error('Monitor observation submission failed', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'user_id'    => $user->id,
                'monitor_id' => $monitor->id,
            ]);
            return back()->withErrors(['error' => 'Failed to submit observation: ' . $e->getMessage()]);
        }
    })->name('observations.store')->middleware('permission:submit-observation');


    // ── View Observations History ─────────────────────────────────────────────
    Route::get('/observations', function (Request $request) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $typeFilter     = $request->get('type', 'all');
        $severityFilter = $request->get('severity', 'all');

        $observations = collect();
        if ($monitor) {
            $query = DB::table('monitor_observations')
                ->where('election_monitor_id', $monitor->id)
                ->join('polling_stations', 'monitor_observations.polling_station_id', '=', 'polling_stations.id')
                ->select(
                    'monitor_observations.*',
                    'polling_stations.name as station_name',
                    'polling_stations.code as station_code'
                );

            if ($typeFilter !== 'all') {
                $query->where('monitor_observations.observation_type', $typeFilter);
            }
            if ($severityFilter !== 'all') {
                $query->where('monitor_observations.severity', $severityFilter);
            }

            $observations = $query->orderByDesc('monitor_observations.observed_at')->get()
                ->map(function ($obs) {
                    $photoPaths = [];
                    if ($obs->photo_paths) {
                        $photoPaths = collect(json_decode($obs->photo_paths, true))
                            ->map(fn($p) => asset('storage/' . $p))
                            ->toArray();
                    }

                    $documents = [];
                    if ($obs->documents_paths) {
                        $documents = collect(json_decode($obs->documents_paths, true))
                            ->map(fn($d) => [
                                'name' => $d['name'] ?? basename($d['path']),
                                'path' => asset('storage/' . $d['path']),
                                'size' => $d['size'] ?? 0,
                                'mime' => $d['mime'] ?? 'application/octet-stream',
                            ])
                            ->toArray();
                    }

                    return [
                        'id'               => $obs->id,
                        'title'            => $obs->title,
                        'observation'      => $obs->observation,
                        'observation_type' => $obs->observation_type,
                        'severity'         => $obs->severity,
                        'station_name'     => $obs->station_name,
                        'station_code'     => $obs->station_code,
                        'observed_at'      => $obs->observed_at,
                        'is_public'        => $obs->is_public,
                        'latitude'         => $obs->latitude,
                        'longitude'        => $obs->longitude,
                        'photo_paths'      => $photoPaths,
                        'documents'        => $documents,
                    ];
                });
        }

        $typeCounts = $monitor
            ? DB::table('monitor_observations')
                ->where('election_monitor_id', $monitor->id)
                ->select('observation_type', DB::raw('count(*) as total'))
                ->groupBy('observation_type')
                ->pluck('total', 'observation_type')
                ->toArray()
            : [];

        return Inertia::render('Monitor/Observations', [
            'auth'           => ['user' => $user],
            'monitor'        => $monitor,
            'observations'   => $observations,
            'typeFilter'     => $typeFilter,
            'severityFilter' => $severityFilter,
            'typeCounts'     => $typeCounts,
        ]);
    })->name('observations')->middleware('permission:view-observation-history');

    // ── Export Observations (CSV) ─────────────────────────────────────────────
    Route::get('/observations/export', function (Request $request) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$monitor) {
            abort(403, 'Not an active monitor.');
        }

        $observations = DB::table('monitor_observations')
            ->where('election_monitor_id', $monitor->id)
            ->join('polling_stations', 'monitor_observations.polling_station_id', '=', 'polling_stations.id')
            ->select(
                'monitor_observations.id',
                'polling_stations.code as station_code',
                'polling_stations.name as station_name',
                'monitor_observations.observation_type',
                'monitor_observations.title',
                'monitor_observations.observation',
                'monitor_observations.severity',
                'monitor_observations.observed_at',
                'monitor_observations.is_public',
                'monitor_observations.latitude',
                'monitor_observations.longitude'
            )
            ->orderByDesc('monitor_observations.observed_at')
            ->get();

        $csvRows   = [];
        $csvRows[] = ['ID', 'Station Code', 'Station Name', 'Type', 'Title', 'Observation', 'Severity', 'Observed At', 'Public', 'Latitude', 'Longitude'];

        foreach ($observations as $obs) {
            $csvRows[] = [
                $obs->id,
                $obs->station_code,
                $obs->station_name,
                $obs->observation_type,
                $obs->title,
                str_replace(["\r", "\n"], ' ', $obs->observation),
                $obs->severity,
                $obs->observed_at,
                $obs->is_public ? 'Yes' : 'No',
                $obs->latitude ?? '',
                $obs->longitude ?? '',
            ];
        }

        $filename = 'monitor-observations-' . now()->format('Y-m-d') . '.csv';
        $handle   = fopen('php://temp', 'r+');
        foreach ($csvRows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        AuditLog::record(
            action:  'monitor.observations.exported',
            event:   'exported',
            module:  'ElectionMonitor',
            extra:   ['outcome' => 'success', 'count' => $observations->count()]
        );

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    })->name('observations.export')->middleware('permission:export-observations');

    // ── Download Single Observation as PDF ────────────────────────────────────
    Route::get('/observations/{id}/pdf', function ($id) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$monitor) {
            abort(403, 'Not an active monitor.');
        }

        $observation = DB::table('monitor_observations')
            ->where('id', $id)
            ->where('election_monitor_id', $monitor->id)
            ->first();

        if (!$observation) {
            abort(404, 'Observation not found.');
        }

        try {
            $pdf = ObservationPDFService::generate($id, $monitor);

            AuditLog::record(
                action:    'monitor.observation.pdf-downloaded',
                event:     'exported',
                module:    'ElectionMonitor',
                extra:     [
                    'outcome'        => 'success',
                    'observation_id' => $id,
                ]
            );

            return $pdf->download("observation-{$id}-" . now()->format('Y-m-d-His') . '.pdf');
        } catch (\Exception $e) {
            Log::error('PDF generation failed', ['error' => $e->getMessage(), 'observation_id' => $id]);
            abort(500, 'Failed to generate PDF: ' . $e->getMessage());
        }
    })->name('observations.pdf')->middleware('permission:export-observations');

    // ── Download Batch Observations as PDF ────────────────────────────────────
    Route::get('/observations/pdf/batch', function (Request $request) {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$monitor) {
            abort(403, 'Not an active monitor.');
        }

        $typeFilter     = $request->get('type', 'all');
        $severityFilter = $request->get('severity', 'all');

        $query = DB::table('monitor_observations')
            ->where('election_monitor_id', $monitor->id);

        if ($typeFilter !== 'all') {
            $query->where('observation_type', $typeFilter);
        }
        if ($severityFilter !== 'all') {
            $query->where('severity', $severityFilter);
        }

        $observationIds = $query->pluck('id')->toArray();

        if (empty($observationIds)) {
            return back()->with('error', 'No observations to export.');
        }

        try {
            $pdf = ObservationPDFService::generateBatch($observationIds, $monitor);

            AuditLog::record(
                action:    'monitor.observations.pdf-batch-downloaded',
                event:     'exported',
                module:    'ElectionMonitor',
                extra:     [
                    'outcome' => 'success',
                    'count'   => count($observationIds),
                    'type'    => $typeFilter,
                    'severity'=> $severityFilter,
                ]
            );

            return $pdf->download('observations-batch-' . now()->format('Y-m-d-His') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Batch PDF generation failed', ['error' => $e->getMessage()]);
            abort(500, 'Failed to generate PDF: ' . $e->getMessage());
        }
    })->name('observations.pdf-batch')->middleware('permission:export-observations');

    // ── View Results (read-only, assigned stations only) ─────────────────────
    Route::get('/results', function () {
        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('pollingStations')
            ->first();

        $results = collect();
        if ($monitor) {
            $stationIds = $monitor->pollingStations->pluck('id');
            // Scope to monitor's election
            $results    = Result::whereIn('polling_station_id', $stationIds)
                ->where('election_id', $monitor->election_id)
                ->with(['pollingStation.ward', 'candidateVotes.candidate.politicalParty', 'partyAcceptances'])
                ->get()
                ->map(function ($r) {
                    return [
                        'id'               => $r->id,
                        'station_name'     => $r->pollingStation->name ?? '—',
                        'station_code'     => $r->pollingStation->code ?? '—',
                        'ward'             => $r->pollingStation->ward->name ?? '—',
                        'submitted_at'     => $r->submitted_at?->format('Y-m-d H:i'),
                        'status'           => $r->certification_status,
                        'total_votes_cast' => $r->total_votes_cast,
                        'valid_votes'      => $r->valid_votes,
                        'rejected_votes'   => $r->rejected_votes,
                        'turnout'          => $r->getTurnoutPercentage(),
                        'candidate_votes'  => $r->candidateVotes->map(fn($cv) => [
                            'candidate'   => $cv->candidate->name ?? '—',
                            'party'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                            'party_color' => $cv->candidate->politicalParty->color ?? '#6b7280',
                            'votes'       => $cv->votes,
                        ])->sortByDesc('votes')->values(),
                    ];
                });
        }

        return Inertia::render('Monitor/Results', [
            'auth'    => ['user' => $user],
            'monitor' => $monitor,
            'results' => $results,
        ]);
    })->name('results')->middleware('permission:view-assigned-stations');

    // ── Download Document ────────────────────────────────────────────────────
    Route::post('/observations/{observationId}/download-document', function ($observationId, Request $request) {
        $user = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$monitor) {
            abort(403, 'Not an active monitor.');
        }

        $observation = DB::table('monitor_observations')
            ->where('id', $observationId)
            ->where('election_monitor_id', $monitor->id)
            ->first();

        if (!$observation || !$observation->documents_paths) {
            abort(404, 'Document not found or access denied.');
        }

        $documentPath = $request->get('path');
        if (!$documentPath) {
            abort(400, 'Document path is required.');
        }

        $documents = json_decode($observation->documents_paths, true) ?? [];
        $docExists = collect($documents)->firstWhere('path', $documentPath);

        if (!$docExists) {
            abort(403, 'Document access denied.');
        }

        $fullPath = storage_path("app/public/{$documentPath}");
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            abort(404, 'File not found on disk.');
        }

        AuditLog::record(
            action:    'monitor.observation.document-downloaded',
            event:     'downloaded',
            module:    'ElectionMonitor',
            extra:     [
                'outcome'         => 'success',
                'observation_id'  => $observationId,
                'document_name'   => $docExists['name'] ?? 'unknown',
                'document_size'   => $docExists['size'] ?? 0,
            ]
        );

        return response()->download($fullPath, $docExists['name'] ?? basename($documentPath));
    })->name('download-document')->middleware('permission:view-observations');
});