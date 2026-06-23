<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\Result;
use App\Services\CertificationWorkflowService;

Route::middleware(['auth', 'role:admin-area-approver'])
    ->prefix('admin-area')
    ->name('admin-area.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();
        $election  = Election::current();

        $pendingResults = 0;
        $statistics    = [
            'approved'       => 0,
            'constituencies' => $adminArea
                ? AdministrativeHierarchy::where('parent_id', $adminArea->id)->where('level', 'constituency')->count()
                : 0,
            'progress'       => 0,
            'awaitingBelow'  => 0,
        ];

        // NOTE: stats are intentionally NOT computed unless a current
        // (in-progress) election exists. Without this guard, the cached
        // block below would otherwise tally Result rows from EVERY
        // election ever created (including closed/old ones), which is
        // exactly the "stale data from a previous election" bug.
        if ($adminArea && $election) {
            $cacheKey = "admin_area_dashboard_v2_{$user->id}_{$adminArea->id}_{$election->id}";

            $dashboardData = Cache::remember($cacheKey, 30, function () use ($adminArea, $election) {
                $areaScope = fn($q) => $q->where('election_id', $election->id)
                    ->whereHas('pollingStation.ward', fn($q2) =>
                        $q2->whereHas('parent', fn($q3) => $q3->where('parent_id', $adminArea->id))
                    );

                $statusCounts = $areaScope(Result::query())
                    ->selectRaw('certification_status, COUNT(*) as count')
                    ->groupBy('certification_status')
                    ->pluck('count', 'certification_status');

                $pending = (int) ($statusCounts[Result::STATUS_PENDING_ADMIN_AREA] ?? 0);
                $approved = (int) ($statusCounts[Result::STATUS_ADMIN_AREA_CERTIFIED] ?? 0)
                    + (int) ($statusCounts[Result::STATUS_PENDING_NATIONAL] ?? 0)
                    + (int) ($statusCounts[Result::STATUS_NATIONALLY_CERTIFIED] ?? 0);
                $awaitingBelow = (int) ($statusCounts[Result::STATUS_SUBMITTED] ?? 0)
                    + (int) ($statusCounts[Result::STATUS_PENDING_PARTY_ACCEPTANCE] ?? 0)
                    + (int) ($statusCounts[Result::STATUS_PENDING_WARD] ?? 0)
                    + (int) ($statusCounts[Result::STATUS_WARD_CERTIFIED] ?? 0)
                    + (int) ($statusCounts[Result::STATUS_PENDING_CONSTITUENCY] ?? 0)
                    + (int) ($statusCounts[Result::STATUS_CONSTITUENCY_CERTIFIED] ?? 0);

                $constituencies = AdministrativeHierarchy::where('parent_id', $adminArea->id)
                    ->where('level', 'constituency')->count();

                $total = $pending + $approved;
                $progress = $total > 0 ? round(($approved / $total) * 100) : 0;

                return [
                    'pendingResults' => $pending,
                    'statistics'     => [
                        'approved'       => $approved,
                        'constituencies' => $constituencies,
                        'progress'       => $progress,
                        'awaitingBelow'  => $awaitingBelow,
                    ],
                ];
            });

            $pendingResults = $dashboardData['pendingResults'];
            $statistics    = $dashboardData['statistics'];
        }

        return Inertia::render('AdminArea/Dashboard', [
            'auth'           => ['user' => $user],
            'adminArea'      => $adminArea ? ['id' => $adminArea->id, 'name' => $adminArea->name] : null,
            'pendingResults' => $pendingResults,
            'statistics'     => $statistics,
        ]);
    })->name('dashboard');

    // ── Approval Queue ────────────────────────────────────────────────────────
    Route::get('/approval-queue', function (Request $request) {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();
        $election  = Election::current();
        $filter    = $request->get('filter', 'pending');

        $results = collect();
        $counts  = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];

        if ($adminArea && $election) {
            $areaScope = fn($q) => $q->where('election_id', $election->id)
                ->whereHas('pollingStation.ward', fn($q2) =>
                    $q2->whereHas('parent', fn($q3) => $q3->where('parent_id', $adminArea->id))
                );

            $countsCacheKey = "admin_area_queue_counts_v2_{$user->id}_{$adminArea->id}_{$election->id}_{$filter}";
            $counts = Cache::remember($countsCacheKey, 15, function () use ($areaScope) {
                $baseCounts = $areaScope(Result::query())
                    ->selectRaw(
                        'SUM(CASE WHEN certification_status = ? THEN 1 ELSE 0 END) as pending, '
                        . 'SUM(CASE WHEN certification_status IN (?, ?, ?) THEN 1 ELSE 0 END) as approved, '
                        . 'SUM(CASE WHEN certification_status = ? AND rejection_count > 0 THEN 1 ELSE 0 END) as rejected, '
                        . 'COUNT(*) as all',
                        [
                            Result::STATUS_PENDING_ADMIN_AREA,
                            Result::STATUS_ADMIN_AREA_CERTIFIED,
                            Result::STATUS_PENDING_NATIONAL,
                            Result::STATUS_NATIONALLY_CERTIFIED,
                            Result::STATUS_PENDING_CONSTITUENCY,
                        ]
                    )
                    ->first();

                return [
                    'pending'  => (int) $baseCounts->pending,
                    'approved' => (int) $baseCounts->approved,
                    'rejected' => (int) $baseCounts->rejected,
                    'all'      => (int) $baseCounts->all,
                ];
            });

            $baseQuery = Result::with([
                'pollingStation.ward.parent',
                'election',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances.politicalParty',
                'submittedBy',
                'certifications' => fn($q) => $q->latest(),
            ]);
            $baseQuery = $areaScope($baseQuery);

            $activeQuery = match ($filter) {
                'pending'  => (clone $baseQuery)->where('certification_status', Result::STATUS_PENDING_ADMIN_AREA),
                'approved' => (clone $baseQuery)->whereIn('certification_status', [
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ]),
                'rejected' => (clone $baseQuery)
                    ->where('certification_status', Result::STATUS_PENDING_CONSTITUENCY)
                    ->where('rejection_count', '>', 0),
                default    => (clone $baseQuery)->whereIn('certification_status', [
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                ]),
            };

            $results = $activeQuery->latest('submitted_at')->get()->map(function ($r) {
                $partyAccepted = $r->partyAcceptances
                    ->whereIn('status', ['accepted', 'accepted_with_reservation'])->count();
                $partyTotal    = $r->partyAcceptances->count();
                $constituency  = $r->pollingStation?->ward?->parent;

                $certs       = $r->certifications->sortByDesc('created_at');
                $wardNote    = $certs->where('certification_level', 'ward')
                                     ->where('status', 'approved')
                                     ->first()?->comments;
                $constNote   = $certs->where('certification_level', 'constituency')
                                     ->where('status', 'approved')
                                     ->first()?->comments;
                $areaNote    = $certs->where('certification_level', 'admin_area')
                                     ->first()?->comments;

                $totalValidVotes = $r->valid_votes ?: 0;

                return [
                    'id'                      => $r->id,
                    'polling_station'         => $r->pollingStation->name ?? 'Unknown',
                    'polling_station_code'    => $r->pollingStation->code ?? '-',
                    'constituency'            => $constituency?->name ?? 'Unknown',
                    'ward'                    => $r->pollingStation?->ward?->name ?? 'Unknown',
                    'officer'                 => $r->submittedBy->name ?? 'Unknown',
                    'submitted_at'            => $r->submitted_at?->format('Y-m-d H:i'),
                    'certification_status'    => $r->certification_status,
                    'total_registered_voters' => $r->total_registered_voters,
                    'total_votes_cast'        => $r->total_votes_cast,
                    'valid_votes'             => $r->valid_votes,
                    'rejected_votes'          => $r->rejected_votes,
                    'disputed_votes'          => $r->disputed_votes,
                    'turnout'                 => $r->getTurnoutPercentage(),
                    'rejection_count'         => $r->rejection_count,
                    'last_rejection_reason'   => $r->last_rejection_reason,
                    'photo_url'               => $r->result_sheet_photo_path
                        ? asset('storage/' . $r->result_sheet_photo_path)
                        : null,
                    'party_accepted'          => $partyAccepted,
                    'party_total'             => $partyTotal,
                    'party_acceptances'       => $r->partyAcceptances->map(fn($pa) => [
                        'party'    => $pa->politicalParty->name ?? 'Unknown',
                        'abbr'     => $pa->politicalParty->abbreviation ?? '?',
                        'status'   => $pa->status,
                        'comments' => $pa->comments,
                    ]),
                    'candidate_votes' => $r->candidateVotes->map(fn($cv) => [
                        'candidate'   => $cv->candidate->name ?? 'Unknown',
                        'party'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                        'party_color' => $cv->candidate->politicalParty->color ?? '#6b7280',
                        'votes'       => $cv->votes,
                        'percentage'  => $totalValidVotes > 0
                            ? round(($cv->votes / $totalValidVotes) * 100, 1) : 0,
                    ]),
                    'ward_comments'         => $wardNote,
                    'constituency_comments' => $constNote,
                    'area_comments'         => $areaNote,
                ];
            });
        }

        return Inertia::render('AdminArea/ApprovalQueue', [
            'auth'      => ['user' => $user],
            'adminArea' => $adminArea ? ['id' => $adminArea->id, 'name' => $adminArea->name] : null,
            'results'   => $results,
            'filter'    => $filter,
            'counts'    => $counts,
        ]);
    })->name('approval-queue')->middleware('permission:view-admin-area-queue|view-admin-area-results');

    // ── Approve ───────────────────────────────────────────────────────────────
    Route::post('/approve/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'nullable|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->approve($result, Auth::user(), 'admin_area', $request->comments);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending admin-area approval.']);
        }

        return back()->with('success', 'Result certified at admin-area level and promoted to IEC Chairman queue.');
    })->name('approve')->middleware('permission:approve-admin-area-result');

    // ── Approve with Reservation ──────────────────────────────────────────────
    Route::post('/approve-with-reservation/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->approve($result, Auth::user(), 'admin_area', $request->comments, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending admin-area approval.']);
        }

        return back()->with('success', 'Result certified with reservation and promoted to IEC Chairman queue.');
    })->name('approve-with-reservation')->middleware('permission:approve-admin-area-result-with-reservation|approve-admin-area-result');

    // ── Reject ────────────────────────────────────────────────────────────────
    // FIX: Accept either the specific reject permission OR the approve permission.
    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->reject($result, Auth::user(), 'admin_area', $request->comments);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending admin-area approval.']);
        }

        return back()->with('success', 'Result rejected and returned to constituency level.');
    })->name('reject')->middleware('permission:reject-admin-area-result|approve-admin-area-result');

    // ── Constituency Breakdowns ───────────────────────────────────────────────
    Route::get('/constituency-breakdowns', function () {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();
        $election  = Election::current();

        $constituencies = collect();
        $stats          = [];

        if ($adminArea) {
            $constituencyNodes = AdministrativeHierarchy::where('parent_id', $adminArea->id)
                ->where('level', 'constituency')
                ->get();

            $totalVotesCounted = 0;
            $certifiedCount    = 0;
            $pendingCount      = 0;
            $awaitingCount     = 0;

            $constituencies = $constituencyNodes->map(function ($constituency) use (
                $election, &$totalVotesCounted, &$certifiedCount, &$pendingCount, &$awaitingCount
            ) {
                // No current election in progress => no result data to show
                // for this constituency (never fall back to an older
                // election's results).
                $allResults = $election
                    ? Result::where('election_id', $election->id)
                        ->whereHas('pollingStation.ward', fn($q) => $q->where('parent_id', $constituency->id))
                        ->get()
                    : collect();

                $atAdminLevel = $allResults->whereIn('certification_status', [
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ]);

                $adminCertified = $allResults->whereIn('certification_status', [
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ]);

                $awaiting = $allResults->whereIn('certification_status', [
                    Result::STATUS_SUBMITTED,
                    Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                    Result::STATUS_PENDING_WARD,
                    Result::STATUS_WARD_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                ]);

                $totalStations = $allResults->count();
                $atAdminCount  = $atAdminLevel->count();
                $certifiedNow  = $adminCertified->count();
                $pendingNow    = $atAdminLevel->where('certification_status', Result::STATUS_PENDING_ADMIN_AREA)->count();
                $awaitingNow   = $awaiting->count();
                $votes         = $allResults->sum('total_votes_cast');
                $registered    = $allResults->sum('total_registered_voters');

                $totalVotesCounted += $votes;
                $certifiedCount    += ($certifiedNow === $totalStations && $totalStations > 0) ? 1 : 0;
                $pendingCount      += $pendingNow > 0 ? 1 : 0;
                $awaitingCount     += $awaitingNow > 0 ? 1 : 0;

                if ($totalStations === 0) {
                    $status      = 'No Results';
                    $statusColor = 'gray';
                } elseif ($certifiedNow === $totalStations) {
                    $status      = 'Certified';
                    $statusColor = 'teal';
                } elseif ($pendingNow > 0) {
                    $status      = 'Pending Review';
                    $statusColor = 'orange';
                } elseif ($awaitingNow > 0) {
                    $status      = 'In Pipeline';
                    $statusColor = 'blue';
                } else {
                    $status      = 'In Progress';
                    $statusColor = 'amber';
                }

                $wards    = AdministrativeHierarchy::where('parent_id', $constituency->id)->where('level', 'ward')->count();
                $stations = \App\Models\PollingStation::whereHas('ward', fn($q) =>
                    $q->where('parent_id', $constituency->id)
                )->count();

                return [
                    'id'              => $constituency->id,
                    'name'            => $constituency->name,
                    'wards'           => $wards,
                    'stations'        => $stations,
                    'votes'           => $votes,
                    'turnout'         => $registered > 0
                        ? round(($votes / $registered) * 100, 1) : 0,
                    'certified_count' => $certifiedNow,
                    'admin_level'     => $atAdminCount,
                    'pending_review'  => $pendingNow,
                    'in_pipeline'     => $awaitingNow,
                    'total_count'     => $totalStations,
                    'status'          => $status,
                    'status_color'    => $statusColor,
                ];
            });

            $stats = [
                'total'      => $constituencyNodes->count(),
                'certified'  => $certifiedCount,
                'pending'    => $pendingCount,
                'awaiting'   => $awaitingCount,
                'totalVotes' => $totalVotesCounted,
            ];
        }

        return Inertia::render('AdminArea/ConstituencyBreakdowns', [
            'auth'           => ['user' => $user],
            'adminArea'      => $adminArea ? ['id' => $adminArea->id, 'name' => $adminArea->name] : null,
            'constituencies' => $constituencies,
            'stats'          => $stats,
        ]);
    })->name('constituency-breakdowns')->middleware('permission:view-constituency-breakdowns');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/analytics', function () {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();
        $election  = Election::current();

        $stats = [
            'totalConstituencies' => 0,
            'certified'           => 0,
            'totalWards'          => 0,
            'totalVotes'          => 0,
            'avgTurnout'          => 0,
            'highestTurnout'      => 0,
            'lowestTurnout'       => 0,
        ];
        $constituencies = collect();

        if ($adminArea) {
            $constituencyNodes = AdministrativeHierarchy::where('parent_id', $adminArea->id)
                ->where('level', 'constituency')->get();

            $totalWards = AdministrativeHierarchy::where('level', 'ward')
                ->whereIn('parent_id', $constituencyNodes->pluck('id'))->count();

            $stats['totalConstituencies'] = $constituencyNodes->count();
            $stats['totalWards']          = $totalWards;

            if ($election) {
                $totalVotesAll              = 0;
                $totalRegisteredAll         = 0;
                // FIX: "certified" must be counted in the SAME unit as
                // totalConstituencies (i.e. "how many constituencies are
                // fully certified"), not the raw number of certified
                // polling-station result rows. Counting raw result rows
                // here against a denominator of "number of constituencies"
                // is what produced nonsensical rates like "1267%".
                $certifiedConstituencyCount = 0;
                $turnoutValues              = [];

                $constituencies = $constituencyNodes->map(function ($constituency) use (
                    $election, &$totalVotesAll, &$totalRegisteredAll,
                    &$certifiedConstituencyCount, &$turnoutValues
                ) {
                    $results = Result::where('election_id', $election->id)
                        ->whereHas('pollingStation.ward', fn($q) => $q->where('parent_id', $constituency->id))
                        ->get();

                    $votes      = $results->sum('total_votes_cast');
                    $registered = $results->sum('total_registered_voters');
                    $total      = $results->count();
                    $certified  = $results->whereIn('certification_status', [
                        Result::STATUS_ADMIN_AREA_CERTIFIED,
                        Result::STATUS_PENDING_NATIONAL,
                        Result::STATUS_NATIONALLY_CERTIFIED,
                    ])->count();

                    $progress = $total > 0 ? round(($certified / $total) * 100) : 0;
                    $turnout  = $registered > 0 ? round(($votes / $registered) * 100, 1) : 0;

                    $totalVotesAll      += $votes;
                    $totalRegisteredAll += $registered;
                    if ($total > 0 && $certified === $total) {
                        $certifiedConstituencyCount++;
                    }
                    if ($total > 0) {
                        $turnoutValues[] = $turnout;
                    }

                    return [
                        'name'     => $constituency->name,
                        'votes'    => $votes,
                        'progress' => $progress,
                        'turnout'  => $turnout,
                    ];
                });

                $stats['certified']  = $certifiedConstituencyCount;
                $stats['totalVotes'] = $totalVotesAll;
                $stats['avgTurnout'] = $totalRegisteredAll > 0
                    ? round(($totalVotesAll / $totalRegisteredAll) * 100, 1) : 0;

                if (!empty($turnoutValues)) {
                    $stats['highestTurnout'] = max($turnoutValues);
                    $stats['lowestTurnout']  = min($turnoutValues);
                }
            } else {
                // No election currently in progress — keep structural
                // counts (constituencies/wards) but never show stale
                // result data from a closed/previous election.
                $constituencies = $constituencyNodes->map(fn($c) => [
                    'name'     => $c->name,
                    'votes'    => 0,
                    'progress' => 0,
                    'turnout'  => 0,
                ]);
            }
        }

        return Inertia::render('AdminArea/Analytics', [
            'auth'           => ['user' => $user],
            'adminArea'      => $adminArea ? ['id' => $adminArea->id, 'name' => $adminArea->name] : null,
            'stats'          => $stats,
            'constituencies' => $constituencies,
        ]);
    })->name('analytics')->middleware('permission:access-analytics');
});