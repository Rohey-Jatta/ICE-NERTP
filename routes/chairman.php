<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Models\AdministrativeHierarchy;
use App\Services\CertificationWorkflowService;

Route::middleware(['auth', 'role:iec-chairman'])
    ->prefix('chairman')
    ->name('chairman.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {

        $cacheKey = 'chairman_dashboard_stats';
        $statistics = Cache::remember($cacheKey, 30, function () use (&$statusCounts, &$pendingNational, &$nationallyCertified, &$stationAgg, &$totalStations, &$totalVoters, &$nationalProgress, &$pipelineCounts) {
            $statusCounts = Result::selectRaw('certification_status, COUNT(*) as cnt')
                ->groupBy('certification_status')
                ->pluck('cnt', 'certification_status');

            $pendingNational     = (int) ($statusCounts[Result::STATUS_PENDING_NATIONAL] ?? 0);
            $nationallyCertified = (int) ($statusCounts[Result::STATUS_NATIONALLY_CERTIFIED] ?? 0);

            $stationAgg  = PollingStation::selectRaw(
                'COUNT(*) as total, COALESCE(SUM(registered_voters), 0) as total_voters'
            )->first();
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
            'recentActivity' => $recentActivity,
        ]);
    })->name('dashboard');

    // ── National Certification Queue ──────────────────────────────────────────
    Route::get('/national-queue', function () {
        $results = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)
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

    // ── Certify nationally ────────────────────────────────────────────────────
    Route::post('/certify/{result}', function (Request $request, Result $result) {
        $request->validate([
            'comments' => 'nullable|string|max:2000',
        ]);

        try {
            app(CertificationWorkflowService::class)->approve($result, Auth::user(), 'national', $request->comments);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending national certification.']);
        }

        // Bust public results cache so the certified result appears immediately.
        Election::forgetPublicCaches($result->election_id, $result->election?->status);
        Cache::forget('chairman_dashboard_stats');

        return back()->with('success', 'Result has been nationally certified and is now publicly visible.');
    })->name('certify')->middleware('permission:national-certification');

    // ── Reject / Return to Admin Area ─────────────────────────────────────────
    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        try {
            app(CertificationWorkflowService::class)->reject($result, Auth::user(), 'national', $request->reason);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending national approval.']);
        }

        return back()->with('success', 'Result returned to Admin Area level for review.');
    })->name('reject')->middleware('permission:override-rejection');

    // ── All Results ───────────────────────────────────────────────────────────
    Route::get('/all-results', function (Request $request) {
        $filter = $request->get('filter', 'all');

        $query = Result::with(['pollingStation.ward', 'submittedBy'])
            ->latest('submitted_at');

        match ($filter) {
            'pending_national'     => $query->where('certification_status', Result::STATUS_PENDING_NATIONAL),
            'nationally_certified' => $query->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED),
            'in_pipeline'          => $query->whereNotIn('certification_status', [
                Result::STATUS_SUBMITTED,
                Result::STATUS_NATIONALLY_CERTIFIED,
                Result::STATUS_REJECTED,
            ]),
            default => $query,
        };

        $results = $query->paginate(30)->through(fn ($r) => [
            'id'                   => $r->id,
            'polling_station_name' => $r->pollingStation->name ?? '—',
            'polling_station_code' => $r->pollingStation->code ?? '—',
            'ward_name'            => $r->pollingStation->ward->name ?? '—',
            'certification_status' => $r->certification_status,
            'total_votes_cast'     => $r->total_votes_cast,
            'turnout_percentage'   => $r->getTurnoutPercentage(),
            'submitted_at'         => $r->submitted_at?->format('Y-m-d H:i'),
            'submitted_by'         => $r->submittedBy->name ?? '—',
            'rejection_count'      => $r->rejection_count,
        ]);

        $allCounts = Result::selectRaw('certification_status, COUNT(*) as cnt')
            ->groupBy('certification_status')
            ->pluck('cnt', 'certification_status');

        $counts = [
            'all'                  => (int) $allCounts->sum(),
            'pending_national'     => (int) ($allCounts[Result::STATUS_PENDING_NATIONAL] ?? 0),
            'nationally_certified' => (int) ($allCounts[Result::STATUS_NATIONALLY_CERTIFIED] ?? 0),
            'in_pipeline'          => (int) $allCounts->filter(function ($cnt, $status) {
                return !in_array($status, [
                    Result::STATUS_SUBMITTED,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                    Result::STATUS_REJECTED,
                ]);
            })->sum(),
        ];

        return Inertia::render('Chairman/AllResults', [
            'auth'    => ['user' => Auth::user()],
            'results' => $results,
            'filter'  => $filter,
            'counts'  => $counts,
        ]);
    })->name('all-results')->middleware('permission:view-all-results');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/analytics', function () {
        $election = Election::where('status', 'active')->orWhere('status', 'certifying')->latest()->first();

        if (!$election) {
            return Inertia::render('Chairman/Analytics', [
                'auth'              => ['user' => Auth::user()],
                'nationalStats'     => [],
                'regionalBreakdown' => [],
            ]);
        }

        $certified = Result::where('election_id', $election->id)
            ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED);

        $totalStations   = PollingStation::where('election_id', $election->id)->count();
        $totalRegistered = PollingStation::where('election_id', $election->id)->sum('registered_voters');
        $votesCast       = (clone $certified)->sum('total_votes_cast');
        $certifiedCount  = (clone $certified)->count();

        $certifiedIds    = (clone $certified)->pluck('id');
        $candidateTotals = ResultCandidateVote::whereIn('result_id', $certifiedIds)
            ->selectRaw('candidate_id, SUM(votes) as total')
            ->groupBy('candidate_id')
            ->with('candidate.politicalParty')
            ->get()
            ->map(fn ($cv) => [
                'name'       => $cv->candidate->politicalParty->name ?? 'Independent',
                'abbr'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                'color'      => $cv->candidate->politicalParty->color ?? '#6b7280',
                'votes'      => $cv->total,
                'percentage' => $votesCast > 0 ? round(($cv->total / $votesCast) * 100, 2) : 0,
            ])
            ->sortByDesc('votes')
            ->values();

        $adminAreas = AdministrativeHierarchy::where('election_id', $election->id)
            ->where('level', 'admin_area')
            ->get()
            ->map(function ($area) use ($election) {
                $stationIds = PollingStation::whereHas('ward', fn ($q) =>
                    $q->where('parent_id', $area->id)
                )->pluck('id');

                $total     = $stationIds->count();
                $certified = Result::whereIn('polling_station_id', $stationIds)
                    ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->count();
                $votes     = Result::whereIn('polling_station_id', $stationIds)
                    ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->sum('total_votes_cast');

                return [
                    'name'      => $area->name,
                    'total'     => $total,
                    'certified' => $certified,
                    'progress'  => $total > 0 ? round(($certified / $total) * 100) : 0,
                    'votes'     => $votes,
                ];
            });

        return Inertia::render('Chairman/Analytics', [
            'auth' => ['user' => Auth::user()],
            'nationalStats' => [
                'totalStations'       => $totalStations,
                'registeredVoters'    => $totalRegistered,
                'votesCast'           => $votesCast,
                'certifiedStations'   => $certifiedCount,
                'certifiedPercentage' => $totalStations > 0 ? round(($certifiedCount / $totalStations) * 100) : 0,
                'turnout'             => $totalRegistered > 0 ? round(($votesCast / $totalRegistered) * 100, 2) : 0,
                'partyPerformance'    => $candidateTotals,
            ],
            'regionalBreakdown' => $adminAreas,
        ]);
    })->name('analytics')->middleware('permission:access-full-analytics');

    // ── Publish page ──────────────────────────────────────────────────────────
    Route::get('/publish', function () {
        $election = Election::where('status', 'active')
            ->orWhere('status', 'certifying')
            ->orWhere('status', 'results_pending')
            ->latest()
            ->first();

        $totalStations     = $election ? PollingStation::where('election_id', $election->id)->count() : 0;
        $certifiedStations = $election
            ? Result::where('election_id', $election->id)
                ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)
                ->count()
            : 0;

        // Count pending in national queue (election-agnostic)
        $pendingNational = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)->count();

        $percentComplete = $totalStations > 0
            ? round(($certifiedStations / $totalStations) * 100)
            : 0;

        return Inertia::render('Chairman/Publish', [
            'auth'              => ['user' => Auth::user()],
            'hasActiveElection' => $election !== null,
            'electionName'      => $election?->name,
            'summary'           => [
                'total'           => $totalStations,
                'certified'       => $certifiedStations,
                'percentComplete' => $percentComplete,
                'pendingNational' => $pendingNational,
                'allCertified'    => $totalStations > 0 && $certifiedStations === $totalStations,
                'lastUpdated'     => now()->format('Y-m-d H:i'),
            ],
            'readinessCheck'   => [
                'canPublish'        => $certifiedStations > 0,
                'anyCertified'      => $certifiedStations > 0,
                'partyAcceptances'  => true,
                'auditComplete'     => true,
            ],
        ]);
    })->name('publish')->middleware('permission:publish-results');

    // ── Finalize election (publish-results POST) ───────────────────────────────
    // This just marks the election as officially closed.
    // Results are already publicly visible as they are nationally certified —
    // this action does NOT gate what is shown to the public.
    Route::post('/publish-results', function () {
        $election = Election::where('status', 'active')
            ->orWhere('status', 'certifying')
            ->orWhere('status', 'results_pending')
            ->latest()
            ->first();

        if (!$election) {
            return back()->withErrors(['error' => 'No active election found.']);
        }

        $election->update(['status' => 'certified']);

        // Bust all public caches so status change reflects immediately
        Election::forgetPublicCaches($election->id, $election->status);
        Cache::forget('public_results_data');

        AuditLog::record(
            action: 'election.results_published',
            event: 'updated',
            module: 'ElectionManagement',
            auditable: $election,
            extra: ['outcome' => 'success']
        );

        return redirect('/results')->with('success', 'Election has been officially closed. All certified results remain publicly visible.');
    })->name('publish-results')->middleware('permission:publish-results');
});
