<?php

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Services\AuditTrailService;
use App\Services\CertificationWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Shared helper: bust every public-facing cache for a given election ─────────
if (!function_exists('bustPublicCachesForElection')) {
    function bustPublicCachesForElection(int $electionId): void
    {
        foreach (['draft', 'active', 'certifying', 'results_pending', 'certified', 'archived'] as $status) {
            Cache::forget("results_summary_v7_{$electionId}_{$status}");
            Cache::forget("results_summary_v3_{$electionId}_{$status}");
        }
        Cache::forget("results_summary_v2_{$electionId}");
        Cache::forget("results_map_{$electionId}");
        Cache::forget("results_map_agg_v3_{$electionId}");
        Cache::forget("results_stations_{$electionId}_pub");
        Cache::forget("results_stations_{$electionId}_prov");
        Cache::forget("stations_filters_{$electionId}");
        Cache::forget('public_results_data');
        Cache::forget('chairman_dashboard_stats');
    }
}

if (!function_exists('chairmanStationsQueryForElection')) {
    function chairmanStationsQueryForElection(int $electionId)
    {
        return PollingStation::where(function ($query) use ($electionId) {
            $query->where('election_id', $electionId)
                  ->orWhereIn('id', function ($sub) use ($electionId) {
                      $sub->select('polling_station_id')
                          ->from('results')
                          ->where('election_id', $electionId);
                  });
        });
    }
}

Route::middleware(['auth', 'role:iec-chairman'])
    ->prefix('chairman')
    ->name('chairman.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $activeElection = Election::currentOrLatestCertified();
        $electionId     = $activeElection?->id;
        $cacheKey       = 'chairman_dashboard_stats_v2_' . ($electionId ?? 'none');

        $statistics = Cache::remember($cacheKey, 30, function () use ($electionId) {
            $resultBase  = Result::when($electionId, fn($q) => $q->where('election_id', $electionId));
            $statusCounts = (clone $resultBase)
                ->selectRaw('certification_status, COUNT(*) as cnt')
                ->groupBy('certification_status')
                ->pluck('cnt', 'certification_status');

            $nationallyCertified = (int) ($statusCounts[Result::STATUS_NATIONALLY_CERTIFIED] ?? 0);
            $pendingNational     = (int) ($statusCounts[Result::STATUS_PENDING_NATIONAL] ?? 0);

            $stationAgg = $electionId
                ? chairmanStationsQueryForElection($electionId)
                    ->selectRaw('COUNT(*) as total, COALESCE(SUM(registered_voters), 0) as total_voters')
                    ->first()
                : (object) ['total' => 0, 'total_voters' => 0];

            $totalStations = (int) ($stationAgg->total ?? 0);
            $totalVoters   = (int) ($stationAgg->total_voters ?? 0);
            $nationalProgress = $totalStations > 0
                ? round(($nationallyCertified / max($totalStations, 1)) * 100)
                : 0;

            $pipelineCounts = [
                'submitted'              => (int) ($statusCounts[Result::STATUS_SUBMITTED] ?? 0),
                'pending_ward'           => (int) ($statusCounts[Result::STATUS_PENDING_WARD] ?? 0),
                'ward_certified'         => (int) ($statusCounts[Result::STATUS_WARD_CERTIFIED] ?? 0),
                'pending_constituency'   => (int) ($statusCounts[Result::STATUS_PENDING_CONSTITUENCY] ?? 0),
                'constituency_certified' => (int) ($statusCounts[Result::STATUS_CONSTITUENCY_CERTIFIED] ?? 0),
                'pending_admin_area'     => (int) ($statusCounts[Result::STATUS_PENDING_ADMIN_AREA] ?? 0),
                'admin_area_certified'   => (int) ($statusCounts[Result::STATUS_ADMIN_AREA_CERTIFIED] ?? 0),
                'pending_national'       => $pendingNational,
                'nationally_certified'   => $nationallyCertified,
                'legacy_party_gate'      => (int) ($statusCounts[Result::STATUS_PENDING_PARTY_ACCEPTANCE] ?? 0),
            ];

            return [
                'nationallyCertified' => $nationallyCertified,
                'totalStations'       => $totalStations,
                'totalVoters'         => $totalVoters,
                'nationalProgress'    => $nationalProgress,
                'pipelineCounts'      => $pipelineCounts,
            ];
        });

        $pendingNational = $statistics['pipelineCounts']['pending_national'] ?? 0;

        $recentActivity = \App\Models\AuditLog::with('user')
            ->whereIn('module', ['Certification', 'Results'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($a) => [
                'action'  => $a->action,
                'user'    => $a->user?->name ?? 'System',
                'time'    => $a->created_at?->diffForHumans(),
                'outcome' => $a->outcome,
            ]);

        return Inertia::render('Chairman/Dashboard', [
            'auth'            => ['user' => Auth::user()],
            'pendingNational' => $pendingNational,
            'statistics'      => $statistics,
            'recentActivity'  => $recentActivity,
        ]);
    })->name('dashboard');

    // ── National Certification Queue ──────────────────────────────────────────
    Route::get('/national-queue', function () {
        $activeElection = Election::currentOrLatestCertified();

        $results = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)
            ->when($activeElection, fn($q) => $q->where('election_id', $activeElection->id))
            ->with([
                'pollingStation.ward',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances.politicalParty',
                'certifications' => fn ($q) => $q->latest(),
                'submittedBy',
            ])
            ->latest('submitted_at')
            ->get()
            ->map(function ($r) {
                $certs     = $r->certifications->sortByDesc('created_at');
                $wardNote  = $certs->where('certification_level', 'ward')
                                   ->where('status', 'approved')
                                   ->first()?->comments;
                $constNote = $certs->where('certification_level', 'constituency')
                                   ->where('status', 'approved')
                                   ->first()?->comments;
                $areaNote  = $certs->where('certification_level', 'admin_area')
                                   ->where('status', 'approved')
                                   ->first()?->comments;

                return [
                    'id'                      => $r->id,
                    'polling_station_name'    => $r->pollingStation->name ?? 'Unknown',
                    'polling_station_code'    => $r->pollingStation->code ?? '—',
                    'ward_name'               => $r->pollingStation->ward->name ?? '—',
                    'submitted_at'            => $r->submitted_at?->format('Y-m-d H:i'),
                    'submitted_by'            => $r->submittedBy->name ?? 'Unknown',
                    'total_registered_voters' => $r->total_registered_voters,
                    'total_votes_cast'        => $r->total_votes_cast,
                    'valid_votes'             => $r->valid_votes,
                    'rejected_votes'          => $r->rejected_votes,
                    'turnout_percentage'      => $r->getTurnoutPercentage(),
                    'rejection_count'         => $r->rejection_count,
                    'photo_url'               => $r->result_sheet_photo_path
                        ? asset('storage/' . $r->result_sheet_photo_path)
                        : null,
                    'candidate_votes'         => $r->candidateVotes->map(fn ($cv) => [
                        'candidate_name' => $cv->candidate->name ?? 'Unknown',
                        'party_name'     => $cv->candidate->politicalParty->name ?? 'Independent',
                        'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                        'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                        'votes'          => $cv->votes,
                    ]),
                    'party_acceptances'       => $r->partyAcceptances->map(fn ($pa) => [
                        'party_name' => $pa->politicalParty->name ?? 'Unknown',
                        'abbr'       => $pa->politicalParty->abbreviation ?? '?',
                        'status'     => $pa->status,
                        'comments'   => $pa->comments,
                    ]),
                    'certification_chain'     => $r->certifications->sortByDesc('created_at')->map(fn ($c) => [
                        'level'      => $c->certification_level,
                        'status'     => $c->status,
                        'comments'   => $c->comments,
                        'decided_at' => $c->decided_at?->format('Y-m-d H:i'),
                    ])->values(),
                    'ward_comments'         => $wardNote,
                    'constituency_comments' => $constNote,
                    'admin_area_comments'   => $areaNote,
                ];
            });

        return Inertia::render('Chairman/NationalQueue', [
            'auth'           => ['user' => Auth::user()],
            'pendingResults' => $results,
            'pendingCount'   => $results->count(),
        ]);
    })->name('national-queue')->middleware('permission:view-national-queue');

    // ── All Results ───────────────────────────────────────────────────────────
    Route::get('/all-results', function (Request $request) {
        $user   = Auth::user();
        $filter = $request->get('filter', 'all');
        $activeElection = Election::currentOrLatestCertified();

        $counts  = ['all' => 0, 'pending_national' => 0, 'in_pipeline' => 0, 'nationally_certified' => 0];
        $results = collect();

        if ($activeElection) {
            $statusCounts = Result::where('election_id', $activeElection->id)
                ->selectRaw('certification_status, COUNT(*) as cnt')
                ->groupBy('certification_status')
                ->pluck('cnt', 'certification_status');

            $pipelineStatuses = [
                Result::STATUS_SUBMITTED,
                Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                Result::STATUS_PENDING_WARD,
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
            ];

            $counts = [
                'nationally_certified' => (int) ($statusCounts[Result::STATUS_NATIONALLY_CERTIFIED] ?? 0),
                'pending_national'     => (int) ($statusCounts[Result::STATUS_PENDING_NATIONAL] ?? 0),
                'in_pipeline'          => (int) $statusCounts->filter(fn ($v, $k) => in_array($k, $pipelineStatuses))->sum(),
                'all'                  => (int) $statusCounts->sum(),
            ];

            $query = Result::with(['pollingStation.ward'])
                ->where('election_id', $activeElection->id);

            match ($filter) {
                'pending_national'     => $query->where('certification_status', Result::STATUS_PENDING_NATIONAL),
                'in_pipeline'          => $query->whereIn('certification_status', $pipelineStatuses),
                'nationally_certified' => $query->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED),
                default                => null,
            };

            $results = $query->latest('submitted_at')
                ->paginate(50)
                ->through(fn ($r) => [
                    'id'                   => $r->id,
                    'polling_station_name' => $r->pollingStation->name ?? 'Unknown',
                    'polling_station_code' => $r->pollingStation->code ?? '—',
                    'ward_name'            => $r->pollingStation->ward->name ?? '—',
                    'total_votes_cast'     => $r->total_votes_cast,
                    'turnout_percentage'   => $r->getTurnoutPercentage(),
                    'certification_status' => $r->certification_status,
                ]);
        }

        return Inertia::render('Chairman/AllResults', [
            'auth'    => ['user' => $user],
            'results' => $results,
            'filter'  => $filter,
            'counts'  => $counts,
        ]);
    })->name('all-results')->middleware('permission:view-all-results');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/analytics', function () {
        $user           = Auth::user();
        $activeElection = Election::currentOrLatestCertified();

        $nationalStats     = [
            'totalStations' => 0, 'registeredVoters' => 0, 'votesCast' => 0,
            'turnout' => 0, 'certifiedPercentage' => 0, 'partyPerformance' => [],
        ];
        $regionalBreakdown = [];

        if ($activeElection) {
            $stationAgg = chairmanStationsQueryForElection($activeElection->id)
                ->selectRaw('COUNT(*) as total, COALESCE(SUM(registered_voters), 0) as total_voters')
                ->first();

            $resultAgg = Result::where('election_id', $activeElection->id)
                ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)
                ->selectRaw('COUNT(*) as certified_count, COALESCE(SUM(total_votes_cast), 0) as total_cast')
                ->first();

            $totalStations  = (int) ($stationAgg->total ?? 0);
            $totalVoters    = (int) ($stationAgg->total_voters ?? 0);
            $votesCast      = (int) ($resultAgg->total_cast ?? 0);
            $certifiedCount = (int) ($resultAgg->certified_count ?? 0);

            $turnout      = $totalVoters > 0 ? round(($votesCast / $totalVoters) * 100, 1) : 0;
            $certifiedPct = $totalStations > 0 ? round(($certifiedCount / $totalStations) * 100, 1) : 0;

            $partyVotes = DB::table('result_candidate_votes as rcv')
                ->join('results as r', 'r.id', '=', 'rcv.result_id')
                ->join('candidates as c', 'c.id', '=', 'rcv.candidate_id')
                ->leftJoin('political_parties as pp', 'pp.id', '=', 'c.political_party_id')
                ->where('r.election_id', $activeElection->id)
                ->where('r.certification_status', Result::STATUS_NATIONALLY_CERTIFIED)
                ->groupBy('pp.id', 'pp.name', 'pp.abbreviation', 'pp.color')
                ->selectRaw("COALESCE(pp.name, 'Independent') as name, COALESCE(pp.abbreviation, 'IND') as abbreviation, pp.color, SUM(rcv.votes) as votes")
                ->orderByDesc('votes')
                ->get();

            $totalValidVotes  = $partyVotes->sum('votes');
            $partyPerformance = $partyVotes->map(fn ($p) => [
                'name'       => $p->name,
                'percentage' => $totalValidVotes > 0 ? round(($p->votes / $totalValidVotes) * 100, 1) : 0,
                'votes'      => (int) $p->votes,
                'color'      => $p->color ?? '#6b7280',
            ])->values()->toArray();

            $nationalStats = [
                'totalStations'       => $totalStations,
                'registeredVoters'    => $totalVoters,
                'votesCast'           => $votesCast,
                'turnout'             => $turnout,
                'certifiedPercentage' => $certifiedPct,
                'partyPerformance'    => $partyPerformance,
            ];

            $regionalBreakdown = DB::table('polling_stations as ps')
                ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
                ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
                ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
                ->leftJoin('results as r', function ($join) use ($activeElection) {
                    $join->on('r.polling_station_id', '=', 'ps.id')
                         ->where('r.election_id', $activeElection->id)
                         ->where('r.certification_status', Result::STATUS_NATIONALLY_CERTIFIED);
                })
                ->where(function ($query) use ($activeElection) {
                    $query->where('ps.election_id', $activeElection->id)
                          ->orWhereIn('ps.id', function ($sub) use ($activeElection) {
                              $sub->select('polling_station_id')
                                  ->from('results')
                                  ->where('election_id', $activeElection->id);
                          });
                })
                ->groupBy('aa.id', 'aa.name')
                ->selectRaw("aa.name, COUNT(DISTINCT ps.id) AS total, COUNT(DISTINCT r.id) AS certified, COALESCE(SUM(r.total_votes_cast), 0) AS votes")
                ->get()
                ->map(fn ($r) => [
                    'name'      => $r->name,
                    'total'     => (int) $r->total,
                    'certified' => (int) $r->certified,
                    'votes'     => (int) $r->votes,
                    'progress'  => $r->total > 0 ? round(($r->certified / $r->total) * 100) : 0,
                    'turnout'   => 0,
                ])
                ->sortByDesc('votes')
                ->values()
                ->toArray();
        }

        return Inertia::render('Chairman/Analytics', [
            'auth'              => ['user' => $user],
            'nationalStats'     => $nationalStats,
            'regionalBreakdown' => $regionalBreakdown,
        ]);
    })->name('analytics')->middleware('permission:access-full-analytics');

    // ── Publish Page (GET) ────────────────────────────────────────────────────
    Route::get('/publish', function () {
        $user     = Auth::user();
        $election = Election::currentOrLatestCertified();

        $summary        = ['total' => 0, 'certified' => 0, 'pendingNational' => 0, 'percentComplete' => 0, 'lastUpdated' => now()->format('Y-m-d H:i')];
        $readinessCheck = ['canPublish' => false, 'canClose' => false];

        if ($election) {
            $totalStations   = chairmanStationsQueryForElection($election->id)->count();
            $certifiedCount  = Result::where('election_id', $election->id)->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->count();
            $pendingNational = Result::where('election_id', $election->id)->where('certification_status', Result::STATUS_PENDING_NATIONAL)->count();

            $summary = [
                'total'           => $totalStations,
                'certified'       => $certifiedCount,
                'pendingNational' => $pendingNational,
                'percentComplete' => $totalStations > 0 ? round(($certifiedCount / $totalStations) * 100) : 0,
                'lastUpdated'     => now()->format('Y-m-d H:i'),
            ];

            $readinessCheck = [
                'canPublish' => $certifiedCount > 0 && in_array($election->status, ['active', 'certifying']),
                'canClose'   => in_array($election->status, ['active', 'certifying', 'results_pending']),
            ];
        }

        return Inertia::render('Chairman/Publish', [
            'auth'           => ['user' => $user],
            'election'       => $election ? ['id' => $election->id, 'name' => $election->name, 'status' => $election->status] : null,
            'readinessCheck' => $readinessCheck,
            'summary'        => $summary,
        ]);
    })->name('publish')->middleware('permission:publish-results');

    // ─────────────────────────────────────────────────────────────────────────
    // POST ACTIONS
    // ─────────────────────────────────────────────────────────────────────────

    Route::post('/certify/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'nullable|string|max:2000']);

        $chairman   = Auth::user();
        $auditTrail = app(AuditTrailService::class);

        try {
            app(CertificationWorkflowService::class)->approve(
                $result, $chairman, 'national', $request->comments
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Could not certify this result: ' . $e->getMessage()]);
        }

        // Explicit national certification audit (beyond what CertificationWorkflowService records)
        $auditTrail->nationalCertification($result, $request->comments);

        bustPublicCachesForElection($result->election_id);

        return back()->with('success', 'Result has been nationally certified and is now publicly visible.');
    })->name('certify')->middleware('permission:national-certification');

    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate(['reason' => 'required|string|max:2000']);

        $chairman = Auth::user();

        try {
            app(CertificationWorkflowService::class)->reject(
                $result, $chairman, 'national', $request->reason
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending national approval.']);
        }

        bustPublicCachesForElection($result->election_id);

        return back()->with('success', 'Result has been returned to the Admin Area for further review.');
    })->name('reject')->middleware('permission:national-certification');

    Route::post('/publish-results', function () {
        $chairman   = Auth::user();
        $auditTrail = app(AuditTrailService::class);

        $election = Election::whereIn('status', ['active', 'certifying'])
            ->latest('start_date')->first();

        if (!$election) {
            return back()->withErrors(['error' => 'No active election found to publish.']);
        }

        $election->update(['status' => 'results_pending']);
        bustPublicCachesForElection($election->id);

        $auditTrail->electionEvent(
            action:       'election.results_published',
            electionId:   $election->id,
            electionName: $election->name,
            description:  sprintf(
                'IEC Chairman %s (ID:%d) published results for election "%s" (ID:%d) at %s. '
                . 'Status changed from "active/certifying" to "results_pending". '
                . 'Results are now publicly visible; polling officers may still submit.',
                $chairman->name, $chairman->id,
                $election->name, $election->id,
                now()->toDateTimeString()
            ),
            extra: ['event' => 'updated', 'election_id' => $election->id]
        );

        return redirect()->route('chairman.publish')
            ->with('success', 'Results are now published.');
    })->name('publish-results')->middleware('permission:publish-results');

    Route::post('/close-election', function () {
        $chairman   = Auth::user();
        $auditTrail = app(AuditTrailService::class);

        $election = Election::whereIn('status', ['active', 'certifying', 'results_pending'])
            ->latest('start_date')->first();

        if (!$election) {
            return back()->withErrors(['error' => 'No open election found to close.']);
        }

        $previousStatus = $election->status;
        $election->update(['status' => 'certified']);
        bustPublicCachesForElection($election->id);

        $auditTrail->electionEvent(
            action:       'election.closed',
            electionId:   $election->id,
            electionName: $election->name,
            description:  sprintf(
                'IEC Chairman %s (ID:%d) OFFICIALLY CLOSED election "%s" (ID:%d) at %s. '
                . 'Status changed from "%s" to "certified". '
                . 'Polling officers can no longer submit results. '
                . 'Election is now permanently archived.',
                $chairman->name, $chairman->id,
                $election->name, $election->id,
                now()->toDateTimeString(),
                $previousStatus
            ),
            extra: [
                'event'          => 'updated',
                'election_id'    => $election->id,
                'previous_status'=> $previousStatus,
                'new_status'     => 'certified',
            ]
        );

        return redirect()->route('chairman.publish')
            ->with('success', 'The election has been officially closed.');
    })->name('close-election')->middleware('permission:publish-results');
});