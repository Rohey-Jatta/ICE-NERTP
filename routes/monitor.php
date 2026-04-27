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
use App\Models\PollingStation;
use App\Models\Result;

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
            $cacheKey = "monitor_dashboard_{$monitor->id}_" . ($monitor->election->id ?? 'none');
            $dashboardData = Cache::remember($cacheKey, 30, function () use ($monitor) {
                $stats = [
                    'assigned_stations' => $monitor->pollingStations->count(),
                    'observations'      => 0,
                    'flagged'           => 0,
                    'visited'           => 0,
                ];

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

            $latestResults = Result::whereIn('polling_station_id', $stationIds)
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
    })->name('stations');

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

        // Pre-select a station if passed as query param
        $preselectedStation = $request->query('station_id');

        return Inertia::render('Monitor/SubmitObservation', [
            'auth'               => ['user' => $user],
            'monitor'            => $monitor,
            'stations'           => $stations,
            'preselectedStation' => $preselectedStation,
        ]);
    })->name('submit-observation');

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
            'photos.*'           => 'image|max:5120', // 5MB per photo
        ]);

        $user    = Auth::user();
        $monitor = ElectionMonitor::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$monitor) {
            return back()->withErrors(['error' => 'You are not registered as an active election monitor.']);
        }

        // Verify the station is assigned to this monitor
        $isAssigned = DB::table('election_monitor_polling_station')
            ->where('election_monitor_id', $monitor->id)
            ->where('polling_station_id', $request->polling_station_id)
            ->exists();

        if (!$isAssigned) {
            return back()->withErrors(['error' => 'You are not assigned to this polling station.']);
        }

        try {
            // Handle photo uploads
            $photoPaths = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store(
                        "monitor-observations/{$monitor->election_id}/{$monitor->id}",
                        'public'
                    );
                    $photoPaths[] = $path;
                }
            }

            $observationId = DB::table('monitor_observations')->insertGetId([
                'election_monitor_id' => $monitor->id,
                'polling_station_id'  => $request->polling_station_id,
                'election_id'         => $monitor->election_id,
                'observation_type'    => $request->observation_type,
                'title'               => $request->title,
                'observation'         => $request->observation,
                'severity'            => $request->severity,
                'photo_paths'         => !empty($photoPaths) ? json_encode($photoPaths) : null,
                'latitude'            => $request->latitude,
                'longitude'           => $request->longitude,
                'is_public'           => $request->boolean('is_public', true),
                'observed_at'         => $request->observed_at,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

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
                    'latitude'           => $request->latitude,
                    'longitude'          => $request->longitude,
                ]
            );

            return redirect()->route('monitor.observations')
                ->with('success', 'Observation submitted successfully!');

        } catch (\Exception $e) {
            Log::error('Monitor observation submission failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to submit observation: ' . $e->getMessage()]);
        }
    })->name('observations.store');

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
                        'photo_paths'      => $obs->photo_paths
                            ? collect(json_decode($obs->photo_paths, true))
                                ->map(fn($p) => asset('storage/' . $p))
                                ->toArray()
                            : [],
                    ];
                });
        }

        // Count stats for display
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
    })->name('observations');

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

        // Build CSV content
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
    })->name('observations.export');

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
            $results    = Result::whereIn('polling_station_id', $stationIds)
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
    })->name('results');
});