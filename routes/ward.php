<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Services\CertificationWorkflowService;

Route::middleware(['auth', 'role:ward-approver'])
    ->prefix('ward')
    ->name('ward.')
    ->group(function () {

    // ── Dashboard ────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user           = Auth::user();
        $activeElection = Election::current();

        $ward = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')
            ->first();

        if (!$ward) {
            return Inertia::render('Ward/Dashboard', [
                'auth'       => ['user' => $user],
                'ward'       => null,
                'statistics' => [
                    'pending'       => 0,
                    'approved'      => 0,
                    'rejected'      => 0,
                    'totalStations' => 0,
                    'progress'      => 0,
                ],
                'pendingResults' => 0,
            ]);
        }

        $cacheKey = "ward_dashboard_v3_{$user->id}_{$ward->id}_" . ($activeElection ? $activeElection->id : 'no_election');
        $data = Cache::remember($cacheKey, 60, function () use ($ward, $activeElection) {
            $stationIds    = PollingStation::where('ward_id', $ward->id)->pluck('id');
            $totalStations = $stationIds->count();

            if ($stationIds->isEmpty() || !$activeElection) {
                return [
                    'statistics' => [
                        'totalStations' => $totalStations,
                        'pending'       => 0,
                        'approved'      => 0,
                        'rejected'      => 0,
                        'progress'      => 0,
                    ],
                    'pendingResults' => 0,
                ];
            }

            // Get the latest result per polling station
            $latestResultIds = DB::table('results')
                ->selectRaw('MAX(id) as id')
                ->whereIn('polling_station_id', $stationIds)
                ->where('election_id', $activeElection->id)
                ->groupBy('polling_station_id')
                ->pluck('id');

            $latestResults = DB::table('results')
                ->select('certification_status', 'rejection_count')
                ->whereIn('id', $latestResultIds)
                ->get();

            // Pending: pending_ward OR legacy pending_party_acceptance
            $pendingCount = $latestResults->whereIn('certification_status', [
                Result::STATUS_PENDING_WARD,
                Result::STATUS_PENDING_PARTY_ACCEPTANCE,
            ])->count();

            // Certified: ward_certified or beyond
            $certifiedCount = $latestResults->whereIn('certification_status', [
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])->count();

            // FIXED: Rejected = results that are in SUBMITTED or PENDING_WARD status
            // AND have rejection_count > 0 (meaning they've been rejected at least once)
            // This correctly captures results returned from constituency/higher levels
            // back to the ward level (STATUS_PENDING_WARD with rejection_count > 0)
            $rejectedCount = $latestResults
                ->filter(function ($r) {
                    return $r->rejection_count > 0
                        && in_array($r->certification_status, [
                            Result::STATUS_SUBMITTED,
                            Result::STATUS_PENDING_WARD,
                        ]);
                })
                ->count();

            // Pending should exclude results that are "rejected/returned" (pending_ward with rejection)
            // A result pending_ward WITH rejection_count > 0 is actually "returned for review"
            // So subtract rejected from pending to avoid double counting
            $trulyPending = $latestResults
                ->filter(function ($r) {
                    return in_array($r->certification_status, [
                        Result::STATUS_PENDING_WARD,
                        Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                    ]) && $r->rejection_count === 0;
                })
                ->count();

            $total    = $trulyPending + $certifiedCount;
            $progress = $total > 0 ? round(($certifiedCount / $total) * 100) : 0;

            return [
                'statistics' => [
                    'totalStations' => $totalStations,
                    'pending'       => $trulyPending,
                    'approved'      => $certifiedCount,
                    'rejected'      => $rejectedCount,
                    'progress'      => $progress,
                ],
                'pendingResults' => $trulyPending,
            ];
        });

        return Inertia::render('Ward/Dashboard', [
            'auth' => ['user' => $user],
            'ward' => ['id' => $ward->id, 'name' => $ward->name, 'code' => $ward->code],
            ...$data,
        ]);
    })->name('dashboard');

    // ── Approval Queue ────────────────────────────────────────────────────────
    Route::get('/approval-queue', function (Request $request) {
        $user           = Auth::user();
        $activeElection = Election::current();
        $ward           = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();
        $filter         = $request->get('filter', 'pending');

        $results = collect();
        $counts  = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];

        if ($ward && $activeElection) {
            $stationIds = PollingStation::where('ward_id', $ward->id)->pluck('id');

            $base = fn() => Result::whereIn('polling_station_id', $stationIds)
                ->where('election_id', $activeElection->id);

            // Parallel workflow: pending_ward + legacy pending_party_acceptance = actionable by ward approver
            // BUT only those WITHOUT a rejection (truly new/pending)
            $counts['pending'] = $base()->whereIn('certification_status', [
                Result::STATUS_PENDING_WARD,
                Result::STATUS_PENDING_PARTY_ACCEPTANCE,
            ])->where('rejection_count', 0)->count();

            $counts['approved'] = $base()->whereIn('certification_status', [
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])->count();

            // FIXED: Rejected = any result with rejection_count > 0 that is back in ward/submitted
            $counts['rejected'] = $base()
                ->whereIn('certification_status', [
                    Result::STATUS_SUBMITTED,
                    Result::STATUS_PENDING_WARD,
                ])
                ->where('rejection_count', '>', 0)
                ->count();

            $counts['all'] = $base()->whereIn('certification_status', [
                Result::STATUS_PENDING_WARD,
                Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
                Result::STATUS_SUBMITTED,
            ])->count();

            $query = $base()->with([
                'pollingStation',
                'election',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances.politicalParty',
                'submittedBy',
                'certifications' => fn($q) => $q->where('certification_level', 'ward')->latest(),
            ]);

            match ($filter) {
                'pending'  => $query->whereIn('certification_status', [
                    Result::STATUS_PENDING_WARD,
                    Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                ])->where('rejection_count', 0),
                'approved' => $query->whereIn('certification_status', [
                    Result::STATUS_WARD_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ]),
                // FIXED: Rejected tab shows results with rejection_count > 0 that are back in ward/submitted
                'rejected' => $query->whereIn('certification_status', [
                    Result::STATUS_SUBMITTED,
                    Result::STATUS_PENDING_WARD,
                ])->where('rejection_count', '>', 0),
                default    => $query->whereIn('certification_status', [
                    Result::STATUS_PENDING_WARD,
                    Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                    Result::STATUS_WARD_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                    Result::STATUS_SUBMITTED,
                ]),
            };

            $results = $query->latest('submitted_at')->get()->map(function ($r) {
                $totalValid    = $r->candidateVotes->sum('votes');
                $partyAccepted = $r->partyAcceptances->whereIn('status', ['accepted', 'accepted_with_reservation'])->count();
                $partyTotal    = $r->partyAcceptances->count();

                return [
                    'id'                      => $r->id,
                    'polling_station'         => $r->pollingStation->name ?? 'Unknown',
                    'polling_station_code'    => $r->pollingStation->code ?? '—',
                    'officer'                 => $r->submittedBy->name ?? 'Unknown',
                    'submitted_at'            => $r->submitted_at?->format('Y-m-d H:i'),
                    'certification_status'    => $r->certification_status,
                    'total_registered_voters' => $r->total_registered_voters,
                    'total_votes_cast'        => $r->total_votes_cast,
                    'valid_votes'             => $r->valid_votes,
                    'rejected_votes'          => $r->rejected_votes,
                    'disputed_votes'          => $r->disputed_votes ?? 0,
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
                        'color'    => $pa->politicalParty->color ?? '#6b7280',
                        'status'   => $pa->status,
                        'comments' => $pa->comments,
                    ]),
                    'candidate_votes'         => $r->candidateVotes->map(fn($cv) => [
                        'candidate'   => $cv->candidate->name ?? 'Unknown',
                        'party'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                        'party_color' => $cv->candidate->politicalParty->color ?? '#6b7280',
                        'votes'       => $cv->votes,
                        'percentage'  => $totalValid > 0
                            ? round(($cv->votes / $totalValid) * 100, 1)
                            : 0,
                    ])->sortByDesc('votes')->values(),
                    'ward_comments'           => $r->certifications->first()?->comments,
                ];
            });
        }

        return Inertia::render('Ward/ApprovalQueue', [
            'auth'    => ['user' => $user],
            'ward'    => $ward ? ['id' => $ward->id, 'name' => $ward->name] : null,
            'results' => $results,
            'filter'  => $filter,
            'counts'  => $counts,
        ]);
    })->name('approval-queue')->middleware('permission:view-ward-queue|view-ward-results');

    // ── Approve ───────────────────────────────────────────────────────────────
    Route::post('/approve/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'nullable|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->approve($result, Auth::user(), 'ward', $request->comments);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        return redirect()->route('ward.approval-queue')->with('success', 'Result certified at ward level and promoted to Constituency queue.');
    })->name('approve')->middleware('permission:approve-ward-result');

    // ── Approve with Reservation ──────────────────────────────────────────────
    Route::post('/approve-with-reservation/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->approve($result, Auth::user(), 'ward', $request->comments, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        return redirect()->route('ward.approval-queue')->with('success', 'Result certified with reservation and promoted to Constituency queue.');
    })->name('approve-with-reservation')->middleware('permission:reject-ward-result-with-reservation|approve-ward-result');

    // ── Reject ────────────────────────────────────────────────────────────────
    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->reject($result, Auth::user(), 'ward', $request->comments);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        return redirect()->route('ward.approval-queue')->with('success', 'Result rejected and returned to the Polling Officer.');
    })->name('reject')->middleware('permission:reject-ward-result|approve-ward-result');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/analytics', function () {
        $user           = Auth::user();
        $activeElection = Election::current();
        $ward           = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();

        $stats            = ['totalStations' => 0, 'certified' => 0, 'pending' => 0, 'rejected' => 0, 'totalVotes' => 0, 'turnoutRate' => 0];
        $stationBreakdown = [];

        if ($ward) {
            $stations = PollingStation::where('ward_id', $ward->id)->get();

            $stationBreakdown = $stations->map(function ($station) use ($activeElection) {
                $result = $activeElection
                    ? Result::where('polling_station_id', $station->id)
                        ->where('election_id', $activeElection->id)
                        ->latest('submitted_at')->first()
                    : null;

                $statusLabel = 'Not Reported';
                if ($result) {
                    $statusLabel = match (true) {
                        in_array($result->certification_status, [
                            Result::STATUS_WARD_CERTIFIED,
                            Result::STATUS_PENDING_CONSTITUENCY,
                            Result::STATUS_CONSTITUENCY_CERTIFIED,
                            Result::STATUS_PENDING_ADMIN_AREA,
                            Result::STATUS_ADMIN_AREA_CERTIFIED,
                            Result::STATUS_PENDING_NATIONAL,
                            Result::STATUS_NATIONALLY_CERTIFIED,
                        ]) => 'Certified',

                        // FIXED: Pending_ward WITH rejection = returned/rejected, not pending
                        in_array($result->certification_status, [
                            Result::STATUS_PENDING_WARD,
                            Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                        ]) && $result->rejection_count > 0 => 'Rejected',

                        in_array($result->certification_status, [
                            Result::STATUS_PENDING_WARD,
                            Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                        ]) => 'Pending',

                        // Submitted with rejection = returned by ward approver, awaiting officer fix
                        $result->certification_status === Result::STATUS_SUBMITTED
                            && $result->rejection_count > 0 => 'Rejected',

                        $result->certification_status === Result::STATUS_SUBMITTED => 'Submitted',
                        default => 'Rejected',
                    };
                }

                return [
                    'id'      => $station->id,
                    'name'    => $station->name,
                    'code'    => $station->code,
                    'voters'  => $station->registered_voters,
                    'votes'   => $result?->total_votes_cast ?? 0,
                    'turnout' => ($result && $station->registered_voters > 0)
                        ? round(($result->total_votes_cast / $station->registered_voters) * 100, 1)
                        : 0,
                    'status'  => $statusLabel,
                ];
            })->values()->toArray();

            $totalVotes      = collect($stationBreakdown)->sum('votes');
            $totalRegistered = $stations->sum('registered_voters');
            $certifiedCount  = collect($stationBreakdown)->where('status', 'Certified')->count();
            $pendingCount    = collect($stationBreakdown)->whereIn('status', ['Pending', 'Submitted'])->count();
            $rejectedCount   = collect($stationBreakdown)->where('status', 'Rejected')->count();

            $stats = [
                'totalStations' => $stations->count(),
                'certified'     => $certifiedCount,
                'pending'       => $pendingCount,
                'rejected'      => $rejectedCount,
                'totalVotes'    => $totalVotes,
                'turnoutRate'   => $totalRegistered > 0
                    ? round(($totalVotes / $totalRegistered) * 100, 1)
                    : 0,
            ];
        }

        return Inertia::render('Ward/Analytics', [
            'auth'             => ['user' => $user],
            'ward'             => $ward ? ['id' => $ward->id, 'name' => $ward->name] : null,
            'stats'            => $stats,
            'stationBreakdown' => $stationBreakdown,
        ]);
    })->name('analytics')->middleware('permission:view-ward-analytics');
});