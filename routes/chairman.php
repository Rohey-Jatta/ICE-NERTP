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

// ── Shared helper: bust every public-facing cache for a given election ────────
// Call this whenever a result's certification_status changes so that all public
// pages (summary, map, stations) immediately show updated data.
if (! function_exists('bustPublicCachesForElection')) {
    function bustPublicCachesForElection(int $electionId): void
    {
        foreach (['draft', 'active', 'certifying', 'results_pending', 'certified', 'archived'] as $status) {
            Cache::forget("results_summary_v3_{$electionId}_{$status}");
        }
        Cache::forget("results_map_{$electionId}");
        Cache::forget("results_stations_{$electionId}_pub");
        Cache::forget("results_stations_{$electionId}_prov");
        Cache::forget("stations_filters_{$electionId}");
        Cache::forget('public_results_data');
        Cache::forget('chairman_dashboard_stats');
    }
}

Route::middleware(['auth', 'role:iec-chairman'])
    ->prefix('chairman')
    ->name('chairman.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {

        $cacheKey = 'chairman_dashboard_stats';
        $pendingNational     = 0;
        $nationallyCertified = 0;
        $totalStations       = 0;
        $totalVoters         = 0;
        $nationalProgress    = 0;
        $pipelineCounts      = [];

        $statistics = Cache::remember($cacheKey, 30, function () use (
            &$pendingNational, &$nationallyCertified, &$totalStations,
            &$totalVoters, &$nationalProgress, &$pipelineCounts
        ) {
            $statusCounts = Result::selectRaw('certification_status, COUNT(*) as cnt')
                ->groupBy('certification_status')
                ->pluck('cnt', 'certification_status');

            $pendingNational     = (int) ($statusCounts[Result::STATUS_PENDING_NATIONAL] ?? 0);
            $nationallyCertified = (int) ($statusCounts[Result::STATUS_NATIONALLY_CERTIFIED] ?? 0);

            $stationAgg    = PollingStation::selectRaw('COUNT(*) as total, COALESCE(SUM(registered_voters), 0) as total_voters')->first();
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

        // Re-read from statistics since the closure may have cached previous values
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

        $approverId = Auth::id();

        $nationalNodeId = AdministrativeHierarchy::where('level', 'national')->value('id');
        if (!$nationalNodeId) {
            $wardParentId    = AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('parent_id');
            $adminAreaNodeId = AdministrativeHierarchy::where('id', $wardParentId)->value('parent_id');
            $nationalNodeId  = $adminAreaNodeId
                ?? AdministrativeHierarchy::where('level', 'admin_area')->value('id')
                ?? AdministrativeHierarchy::orderBy('id')->value('id')
                ?? 1;
        }

        // Update the result — nationally_certified_at is now in $fillable
        $result->update([
            'certification_status'    => Result::STATUS_NATIONALLY_CERTIFIED,
            'nationally_certified_at' => now(),
        ]);

        \App\Models\ResultCertification::create([
            'result_id'           => $result->id,
            'certification_level' => 'national',
            'hierarchy_node_id'   => $nationalNodeId,
            'approver_id'         => $approverId,
            'status'              => 'approved',
            'comments'            => $request->comments,
            'assigned_at'         => now(),
            'decided_at'          => now(),
        ]);

        AuditLog::record(
            action: 'certification.national.approved',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'success', 'comments' => $request->comments]
        );

        // ── Bust ALL public caches immediately so the certified result ─────────
        // appears on /results, /results/map, and /results/stations without
        // requiring any manual refresh or waiting for TTL expiry.
        bustPublicCachesForElection($result->election_id);

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

        // Bust ALL caches so the new status reflects everywhere immediately
        bustPublicCachesForElection($election->id);

        AuditLog::record(
            action: 'election.results_published',
            event: 'updated',
            module: 'ElectionManagement',
            auditable: $election,
            extra: ['outcome' => 'success']
        );

        return redirect('/results')->with('success', 'Certified station results are now published provisionally while the election remains open.');
    })->name('publish-results')->middleware('permission:publish-results');

    // ── Close Election ─────────────────────────────────────────────────────────
    Route::post('/close-election', function () {
        $election = Election::whereIn('status', ['active', 'certifying', 'results_pending'])
            ->latest()
            ->first();

        if (!$election) {
            return back()->withErrors(['error' => 'No active election found.']);
        }

        $election->update(['status' => 'certified']);
        bustPublicCachesForElection($election->id);

        AuditLog::record(
            action: 'election.closed',
            event: 'updated',
            module: 'ElectionManagement',
            auditable: $election,
            extra: ['outcome' => 'success']
        );

        return redirect('/results')->with('success', 'Election has been closed and certified for final publication.');
    })->name('close-election')->middleware('permission:publish-results');
});
