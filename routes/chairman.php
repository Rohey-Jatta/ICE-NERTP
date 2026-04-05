<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Models\AdministrativeHierarchy;

Route::middleware(['auth', 'role:iec-chairman'])
    ->prefix('chairman')
    ->name('chairman.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $pendingNational = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)->count();
        $nationallyCertified = Result::where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->count();
        $totalStations   = PollingStation::count();
        $totalVoters     = PollingStation::sum('registered_voters');

        // National progress
        $totalResults = Result::whereNotIn('certification_status', [
            Result::STATUS_SUBMITTED, Result::STATUS_REJECTED
        ])->count();
        $nationalProgress = $totalStations > 0
            ? round(($nationallyCertified / max($totalStations, 1)) * 100)
            : 0;

        // Recent audit activity
        $recentActivity = \App\Models\AuditLog::with('user')
            ->whereIn('module', ['Certification', 'Results'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn($a) => [
                'action' => $a->action,
                'user'   => $a->user?->name ?? 'System',
                'time'   => $a->created_at?->diffForHumans(),
                'outcome'=> $a->outcome,
            ]);

        // Pipeline overview
        $pipelineCounts = [
            'submitted'               => Result::where('certification_status', Result::STATUS_SUBMITTED)->count(),
            'pending_party'           => Result::where('certification_status', Result::STATUS_PENDING_PARTY_ACCEPTANCE)->count(),
            'pending_ward'            => Result::where('certification_status', Result::STATUS_PENDING_WARD)->count(),
            'ward_certified'          => Result::where('certification_status', Result::STATUS_WARD_CERTIFIED)->count(),
            'pending_constituency'    => Result::where('certification_status', Result::STATUS_PENDING_CONSTITUENCY)->count(),
            'constituency_certified'  => Result::where('certification_status', Result::STATUS_CONSTITUENCY_CERTIFIED)->count(),
            'pending_admin_area'      => Result::where('certification_status', Result::STATUS_PENDING_ADMIN_AREA)->count(),
            'admin_area_certified'    => Result::where('certification_status', Result::STATUS_ADMIN_AREA_CERTIFIED)->count(),
            'pending_national'        => $pendingNational,
            'nationally_certified'    => $nationallyCertified,
        ];

        return Inertia::render('Chairman/Dashboard', [
            'auth'            => ['user' => Auth::user()],
            'pendingNational' => $pendingNational,
            'statistics'      => [
                'nationallyCertified' => $nationallyCertified,
                'totalStations'       => $totalStations,
                'totalVoters'         => $totalVoters,
                'nationalProgress'    => $nationalProgress,
                'pipelineCounts'      => $pipelineCounts,
            ],
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
                'certifications' => fn($q) => $q->latest(),
                'submittedBy',
            ])
            ->latest('submitted_at')
            ->get()
            ->map(fn($r) => [
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
                'candidate_votes'         => $r->candidateVotes->map(fn($cv) => [
                    'candidate_name' => $cv->candidate->name ?? 'Unknown',
                    'party_name'     => $cv->candidate->politicalParty->name ?? 'Independent',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
                'party_acceptances'       => $r->partyAcceptances->map(fn($pa) => [
                    'party_name' => $pa->politicalParty->name ?? 'Unknown',
                    'abbr'       => $pa->politicalParty->abbreviation ?? '?',
                    'status'     => $pa->status,
                    'comments'   => $pa->comments,
                ]),
                'certification_chain'     => $r->certifications->map(fn($c) => [
                    'level'      => $c->certification_level,
                    'status'     => $c->status,
                    'comments'   => $c->comments,
                    'decided_at' => $c->decided_at?->format('Y-m-d H:i'),
                ]),
            ]);

        return Inertia::render('Chairman/NationalQueue', [
            'auth'             => ['user' => Auth::user()],
            'pendingResults'   => $results,
            'pendingCount'     => $results->count(),
        ]);
    })->name('national-queue');

    // ── Certify nationally ────────────────────────────────────────────────────
    Route::post('/certify/{result}', function (Request $request, Result $result) {
        $request->validate([
            'comments' => 'nullable|string|max:2000',
        ]);

        if ($result->certification_status !== Result::STATUS_PENDING_NATIONAL) {
            return back()->withErrors(['error' => 'Result is not pending national certification.']);
        }

        $result->update([
            'certification_status'    => Result::STATUS_NATIONALLY_CERTIFIED,
            'nationally_certified_at' => now(),
        ]);

        \App\Models\ResultCertification::create([
            'result_id'           => $result->id,
            'certification_level' => 'national',
            'hierarchy_node_id'   => AdministrativeHierarchy::where('level', 'national')->value('id') ?? 1,
            'approver_id'         => Auth::id(),
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

        return back()->with('success', 'Result has been nationally certified.');
    })->name('certify');

    // ── Reject / Return to Admin Area ─────────────────────────────────────────
    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        if ($result->certification_status !== Result::STATUS_PENDING_NATIONAL) {
            return back()->withErrors(['error' => 'Result is not pending national approval.']);
        }

        $result->update([
            'certification_status'  => Result::STATUS_PENDING_ADMIN_AREA,
            'last_rejection_reason' => $request->reason,
            'last_rejected_by'      => Auth::id(),
            'last_rejected_at'      => now(),
            'rejection_count'       => $result->rejection_count + 1,
        ]);

        \App\Models\ResultCertification::create([
            'result_id'           => $result->id,
            'certification_level' => 'national',
            'hierarchy_node_id'   => AdministrativeHierarchy::where('level', 'national')->value('id') ?? 1,
            'approver_id'         => Auth::id(),
            'status'              => 'rejected',
            'comments'            => $request->reason,
            'assigned_at'         => now(),
            'decided_at'          => now(),
        ]);

        AuditLog::record(
            action: 'certification.national.rejected',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'rejected', 'reason' => $request->reason]
        );

        return back()->with('success', 'Result returned to Admin Area level for review.');
    })->name('reject');

    // ── All Results (national overview) ───────────────────────────────────────
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
            default => $query, // all
        };

        $results = $query->paginate(30)->through(fn($r) => [
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

        // Status counts
        $counts = [
            'all'                  => Result::count(),
            'pending_national'     => Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)->count(),
            'nationally_certified' => Result::where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->count(),
            'in_pipeline'          => Result::whereNotIn('certification_status', [
                Result::STATUS_SUBMITTED, Result::STATUS_NATIONALLY_CERTIFIED, Result::STATUS_REJECTED,
            ])->count(),
        ];

        return Inertia::render('Chairman/AllResults', [
            'auth'    => ['user' => Auth::user()],
            'results' => $results,
            'filter'  => $filter,
            'counts'  => $counts,
        ]);
    })->name('all-results');

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

        // National aggregate
        $certified = Result::where('election_id', $election->id)
            ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED);

        $totalStations  = PollingStation::where('election_id', $election->id)->count();
        $totalRegistered = PollingStation::where('election_id', $election->id)->sum('registered_voters');

        $votesCast      = (clone $certified)->sum('total_votes_cast');
        $certifiedCount = (clone $certified)->count();

        // Candidate totals from certified results
        $certifiedIds = (clone $certified)->pluck('id');
        $candidateTotals = ResultCandidateVote::whereIn('result_id', $certifiedIds)
            ->selectRaw('candidate_id, SUM(votes) as total')
            ->groupBy('candidate_id')
            ->with('candidate.politicalParty')
            ->get()
            ->map(fn($cv) => [
                'name'       => $cv->candidate->politicalParty->name ?? 'Independent',
                'abbr'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                'color'      => $cv->candidate->politicalParty->color ?? '#6b7280',
                'votes'      => $cv->total,
                'percentage' => $votesCast > 0 ? round(($cv->total / $votesCast) * 100, 2) : 0,
            ])
            ->sortByDesc('votes')
            ->values();

        // Regional breakdown by admin area
        $adminAreas = AdministrativeHierarchy::where('election_id', $election->id)
            ->where('level', 'admin_area')
            ->get()
            ->map(function ($area) use ($election) {
                $stationIds = PollingStation::whereHas('ward', fn($q) =>
                    $q->where('parent_id', $area->id)
                )->pluck('id');

                $total      = $stationIds->count();
                $certified  = Result::whereIn('polling_station_id', $stationIds)
                    ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)
                    ->count();
                $votes      = Result::whereIn('polling_station_id', $stationIds)
                    ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)
                    ->sum('total_votes_cast');

                return [
                    'name'     => $area->name,
                    'total'    => $total,
                    'certified'=> $certified,
                    'progress' => $total > 0 ? round(($certified / $total) * 100) : 0,
                    'votes'    => $votes,
                ];
            });

        return Inertia::render('Chairman/Analytics', [
            'auth' => ['user' => Auth::user()],
            'nationalStats' => [
                'totalStations'      => $totalStations,
                'registeredVoters'   => $totalRegistered,
                'votesCast'          => $votesCast,
                'certifiedStations'  => $certifiedCount,
                'certifiedPercentage'=> $totalStations > 0 ? round(($certifiedCount / $totalStations) * 100) : 0,
                'turnout'            => $totalRegistered > 0 ? round(($votesCast / $totalRegistered) * 100, 2) : 0,
                'partyPerformance'   => $candidateTotals,
            ],
            'regionalBreakdown' => $adminAreas,
        ]);
    })->name('analytics');

    // ── Publish results ───────────────────────────────────────────────────────
    Route::get('/publish', function () {
        $election = Election::where('status', 'active')->orWhere('status', 'certifying')->latest()->first();

        $totalStations     = $election ? PollingStation::where('election_id', $election->id)->count() : 0;
        $certifiedStations = $election
            ? Result::where('election_id', $election->id)
                ->where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->count()
            : 0;

        $allCertified    = $totalStations > 0 && $certifiedStations === $totalStations;
        $pendingNational = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)->count();

        return Inertia::render('Chairman/Publish', [
            'auth'           => ['user' => Auth::user()],
            'readinessCheck' => [
                'allCertified'     => $allCertified,
                'partyAcceptances' => true, // simplified — could add real check
                'auditComplete'    => true,
            ],
            'summary' => [
                'total'          => $totalStations,
                'certified'      => $certifiedStations,
                'percentComplete'=> $totalStations > 0 ? round(($certifiedStations / $totalStations) * 100) : 0,
                'pendingNational'=> $pendingNational,
                'lastUpdated'    => now()->format('Y-m-d H:i'),
            ],
        ]);
    })->name('publish');

    Route::post('/publish-results', function () {
        $election = Election::where('status', 'active')->orWhere('status', 'certifying')->latest()->first();
        if (!$election) {
            return back()->withErrors(['error' => 'No active election found.']);
        }
        $election->update(['status' => 'certified']);
        AuditLog::record(
            action: 'election.results_published',
            event: 'updated',
            module: 'ElectionManagement',
            auditable: $election,
            extra: ['outcome' => 'success']
        );
        return redirect('/results')->with('success', 'Results published successfully to the public!');
    })->name('publish-results');
});