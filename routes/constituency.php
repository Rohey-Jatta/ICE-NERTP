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

Route::middleware(['auth', 'role:constituency-approver'])
    ->prefix('constituency')
    ->name('constituency.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user           = Auth::user();
        $activeElection = Election::where('status', 'active')->latest()->first();
        $constituency   = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'constituency')->first();

        $stats = ['pending' => 0, 'certified' => 0, 'rejected' => 0, 'totalWards' => 0, 'progress' => 0];

        if ($constituency && $activeElection) {
            $wardIds = AdministrativeHierarchy::where('parent_id', $constituency->id)
                ->where('level', 'ward')->pluck('id');

            $stats['totalWards'] = $wardIds->count();

            $cacheKey = "constituency_dashboard_{$user->id}_{$constituency->id}_{$activeElection->id}";
            $stats = Cache::remember($cacheKey, 30, function () use ($activeElection, $wardIds) {
                $base = Result::where('election_id', $activeElection->id)
                    ->whereHas('pollingStation', fn($q) => $q->whereIn('ward_id', $wardIds));

                $statusCounts = $base->selectRaw(
                    'SUM(CASE WHEN certification_status = ? THEN 1 ELSE 0 END) as pending, '
                    . 'SUM(CASE WHEN certification_status IN (?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as certified, '
                    . 'SUM(CASE WHEN certification_status = ? AND rejection_count > 0 THEN 1 ELSE 0 END) as rejected, '
                    . 'COUNT(*) as total',
                    [
                        Result::STATUS_PENDING_CONSTITUENCY,
                        Result::STATUS_CONSTITUENCY_CERTIFIED,
                        Result::STATUS_PENDING_ADMIN_AREA,
                        Result::STATUS_ADMIN_AREA_CERTIFIED,
                        Result::STATUS_PENDING_NATIONAL,
                        Result::STATUS_NATIONALLY_CERTIFIED,
                        Result::STATUS_PENDING_WARD,
                    ]
                )->first();

                $certified = (int) $statusCounts->certified;
                $total     = (int) $statusCounts->total;

                return [
                    'pending'     => (int) $statusCounts->pending,
                    'certified'   => $certified,
                    'rejected'    => (int) $statusCounts->rejected,
                    'totalWards'  => $wardIds->count(),
                    'progress'    => $total > 0 ? round(($certified / $total) * 100) : 0,
                ];
            });
        }

        return Inertia::render('Constituency/Dashboard', [
            'auth'         => ['user' => $user],
            'constituency' => $constituency,
            'statistics'   => $stats,
        ]);
    })->name('dashboard');

    // ── Approval Queue ────────────────────────────────────────────────────────
    Route::get('/approval-queue', function (Request $request) {
        $user           = Auth::user();
        $activeElection = Election::where('status', 'active')->latest()->first();
        $constituency   = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'constituency')->first();

        $filter  = $request->get('filter', 'pending');
        $results = collect();
        $counts  = ['pending' => 0, 'certified' => 0, 'rejected' => 0, 'all' => 0];

        if ($constituency && $activeElection) {
            $wardIds = AdministrativeHierarchy::where('parent_id', $constituency->id)
                ->where('level', 'ward')->pluck('id');

            $base = fn() => Result::where('election_id', $activeElection->id)
                ->whereHas('pollingStation', fn($q) => $q->whereIn('ward_id', $wardIds));

            $counts = Cache::remember("constituency_queue_counts_{$user->id}_{$constituency->id}_{$activeElection->id}_{$filter}", 15, function () use ($base) {
                $countsRow = $base()
                    ->selectRaw(
                        'SUM(CASE WHEN certification_status = ? THEN 1 ELSE 0 END) as pending, '
                        . 'SUM(CASE WHEN certification_status IN (?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as certified, '
                        . 'SUM(CASE WHEN certification_status = ? AND rejection_count > 0 THEN 1 ELSE 0 END) as rejected, '
                        . 'COUNT(*) as all',
                        [
                            Result::STATUS_PENDING_CONSTITUENCY,
                            Result::STATUS_CONSTITUENCY_CERTIFIED,
                            Result::STATUS_PENDING_ADMIN_AREA,
                            Result::STATUS_ADMIN_AREA_CERTIFIED,
                            Result::STATUS_PENDING_NATIONAL,
                            Result::STATUS_NATIONALLY_CERTIFIED,
                            Result::STATUS_PENDING_WARD,
                        ]
                    )
                    ->first();

                return [
                    'pending'   => (int) $countsRow->pending,
                    'certified' => (int) $countsRow->certified,
                    'rejected'  => (int) $countsRow->rejected,
                    'all'       => (int) $countsRow->all,
                ];
            });

            $query = $base()->with([
                'pollingStation.ward',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances.politicalParty',
                'certifications' => fn($q) => $q->where('certification_level', 'ward')->latest(),
            ]);

            match ($filter) {
                'pending'   => $query->where('certification_status', Result::STATUS_PENDING_CONSTITUENCY),
                'certified' => $query->whereIn('certification_status', [
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ]),
                'rejected'  => $query->where('certification_status', Result::STATUS_PENDING_WARD)
                    ->where('rejection_count', '>', 0),
                default     => $query->whereIn('certification_status', [
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                    Result::STATUS_PENDING_WARD,
                ]),
            };

            $results = $query->latest('submitted_at')->get()->map(function ($r) {
                $totalValid = $r->candidateVotes->sum('votes');

                return [
                    'id'                      => $r->id,
                    'polling_station'         => $r->pollingStation->name ?? 'Unknown',
                    'polling_station_code'    => $r->pollingStation->code ?? '—',
                    'ward_name'               => $r->pollingStation->ward->name ?? '—',
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
                    'ward_cert_comments'      => $r->certifications->first()?->comments,
                    'party_acceptances'       => $r->partyAcceptances->map(fn($pa) => [
                        'party'    => $pa->politicalParty->name ?? 'Unknown',
                        'abbr'     => $pa->politicalParty->abbreviation ?? '?',
                        'color'    => $pa->politicalParty->color ?? '#6b7280',
                        'status'   => $pa->status,
                        'comments' => $pa->comments,
                    ]),
                    'candidate_votes' => $r->candidateVotes->map(fn($cv) => [
                        'candidate'   => $cv->candidate->name ?? 'Unknown',
                        'party'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                        'party_color' => $cv->candidate->politicalParty->color ?? '#6b7280',
                        'votes'       => $cv->votes,
                        'percentage'  => $totalValid > 0
                            ? round(($cv->votes / $totalValid) * 100, 1) : 0,
                    ])->sortByDesc('votes')->values(),
                ];
            });
        }

        return Inertia::render('Constituency/ApprovalQueue', [
            'auth'         => ['user' => $user],
            'constituency' => $constituency,
            'results'      => $results,
            'filter'       => $filter,
            'counts'       => $counts,
        ]);
    })->name('approval-queue')->middleware('permission:view-constituency-queue|view-constituency-results');

    // ── Approve ───────────────────────────────────────────────────────────────
    Route::post('/approve/{result}', function (Result $result, Request $request) {
        $request->validate(['comments' => 'nullable|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->approve($result, Auth::user(), 'constituency', $request->comments);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
        }

        return back()->with('success', 'Result certified at constituency level and promoted to Admin Area queue.');
    })->name('approve')->middleware('permission:approve-constituency-result');

    // ── Approve with Reservation ──────────────────────────────────────────────
    Route::post('/approve-with-reservation/{result}', function (Result $result, Request $request) {
        $request->validate(['comments' => 'required|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->approve($result, Auth::user(), 'constituency', $request->comments, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
        }

        return back()->with('success', 'Result certified with reservation and promoted to Admin Area queue.');
    })->name('approve-with-reservation')->middleware('permission:approve-constituency-result-with-reservation|approve-constituency-result');

    // ── Reject ────────────────────────────────────────────────────────────────
    Route::post('/reject/{result}', function (Result $result, Request $request) {
        $request->validate(['comments' => 'required|string|max:5000']);

        try {
            app(CertificationWorkflowService::class)->reject($result, Auth::user(), 'constituency', $request->comments);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
        }

        return back()->with('success', 'Result rejected and returned to Ward Approver.');
    })->name('reject')->middleware('permission:reject-constituency-result');

    // ── Ward Breakdowns ───────────────────────────────────────────────────────
    Route::get('/ward-breakdowns', function () {
        $user           = Auth::user();
        $activeElection = Election::where('status', 'active')->latest()->first();
        $constituency   = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'constituency')->first();

        $wards = [];
        if ($constituency) {
            $wardNodes = AdministrativeHierarchy::where('parent_id', $constituency->id)
                ->where('level', 'ward')
                ->with('pollingStations')
                ->get();

            $wards = $wardNodes->map(function ($ward) use ($activeElection) {
                $stations        = $ward->pollingStations;
                $totalVotes      = 0;
                $totalRegistered = 0;
                $certified       = 0;
                $stationCount    = $stations->count();

                foreach ($stations as $station) {
                    $latestResult = $activeElection
                        ? Result::where('polling_station_id', $station->id)
                            ->where('election_id', $activeElection->id)
                            ->latest('submitted_at')->first()
                        : null;

                    if ($latestResult) {
                        $totalVotes      += $latestResult->total_votes_cast;
                        $totalRegistered += $latestResult->total_registered_voters;
                        if (in_array($latestResult->certification_status, [
                            Result::STATUS_WARD_CERTIFIED,
                            Result::STATUS_PENDING_CONSTITUENCY,
                            Result::STATUS_CONSTITUENCY_CERTIFIED,
                            Result::STATUS_PENDING_ADMIN_AREA,
                            Result::STATUS_ADMIN_AREA_CERTIFIED,
                            Result::STATUS_PENDING_NATIONAL,
                            Result::STATUS_NATIONALLY_CERTIFIED,
                        ])) {
                            $certified++;
                        }
                    }
                }

                $turnout     = $totalRegistered > 0
                    ? round(($totalVotes / $totalRegistered) * 100, 1) : 0;
                $allCertified = $stationCount > 0 && $certified === $stationCount;

                return [
                    'id'       => $ward->id,
                    'name'     => $ward->name,
                    'stations' => $stationCount,
                    'certified'=> $certified,
                    'votes'    => $totalVotes,
                    'turnout'  => $turnout,
                    'status'   => $allCertified ? 'Fully Certified' : ($certified > 0 ? 'Partially Certified' : 'Pending'),
                    'progress' => $stationCount > 0 ? round(($certified / $stationCount) * 100) : 0,
                ];
            })->sortBy('name')->values();
        }

        return Inertia::render('Constituency/WardBreakdowns', [
            'auth'         => ['user' => $user],
            'constituency' => $constituency,
            'wards'        => $wards,
        ]);
    })->name('ward-breakdowns')->middleware('permission:view-ward-breakdowns');

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::get('/reports', function () {
        $user           = Auth::user();
        $activeElection = Election::where('status', 'active')->latest()->first();
        $constituency   = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'constituency')->first();

        $reportData = null;
        if ($constituency && $activeElection) {
            $wardIds = AdministrativeHierarchy::where('parent_id', $constituency->id)
                ->where('level', 'ward')->pluck('id');

            $results = Result::where('election_id', $activeElection->id)
                ->whereHas('pollingStation', fn($q) => $q->whereIn('ward_id', $wardIds))
                ->get();

            $reportData = [
                'total_stations'    => $results->count(),
                'total_cast'        => $results->sum('total_votes_cast'),
                'total_valid'       => $results->sum('valid_votes'),
                'total_rejected'    => $results->sum('rejected_votes'),
                'total_registered'  => $results->sum('total_registered_voters'),
                'turnout'           => $results->sum('total_registered_voters') > 0
                    ? round(($results->sum('total_votes_cast') / $results->sum('total_registered_voters')) * 100, 2) : 0,
                'certified_count'   => $results->whereIn('certification_status', [
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ])->count(),
            ];
        }

        return Inertia::render('Constituency/Reports', [
            'auth'         => ['user' => $user],
            'constituency' => $constituency,
            'reportData'   => $reportData,
        ]);
    })->name('reports')->middleware('permission:generate-constituency-report');

    // ── PDF Report Export ─────────────────────────────────────────────────────
    Route::get('/reports/export/{type}', function ($type) {
        $validTypes = ['full', 'ward-summary', 'turnout', 'certification'];
        if (!in_array($type, $validTypes)) {
            abort(404, 'Unknown report type.');
        }

        $user = Auth::user();
        $constituency = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'constituency')->first();

        if (!$constituency) {
            abort(403, 'No constituency assigned to your account.');
        }

        $activeElection = Election::where('status', 'active')->latest()->first()
            ?? Election::whereNotIn('status', ['archived'])->latest()->first();

        if (!$activeElection) {
            abort(404, 'No election found.');
        }

        $wardIds = AdministrativeHierarchy::where('parent_id', $constituency->id)
            ->where('level', 'ward')
            ->pluck('id');

        $results = Result::where('election_id', $activeElection->id)
            ->whereHas('pollingStation', fn($q) => $q->whereIn('ward_id', $wardIds))
            ->with([
                'pollingStation.ward',
                'candidateVotes.candidate.politicalParty',
                'certifications' => fn($q) => $q->latest(),
                'partyAcceptances.politicalParty',
            ])
            ->get();

        $wards = AdministrativeHierarchy::where('parent_id', $constituency->id)
            ->where('level', 'ward')
            ->get();

        $titles = [
            'full'         => 'Full Constituency Results',
            'ward-summary' => 'Ward Summary Report',
            'turnout'      => 'Turnout Analysis',
            'certification'=> 'Certification Status Report',
        ];
        $reportTitle = $titles[$type];

        // ── Shared summary stats ───────────────────────────────────────────
        $totalRegistered = $results->sum('total_registered_voters');
        $totalCast       = $results->sum('total_votes_cast');
        $totalValid      = $results->sum('valid_votes');
        $totalRejected   = $results->sum('rejected_votes');
        $turnout         = $totalRegistered > 0 ? round(($totalCast / $totalRegistered) * 100, 2) : 0;
        $generatedAt     = now()->format('d M Y, H:i');

        // ── Build report-specific table rows ──────────────────────────────
        $tableRows = '';

        if ($type === 'full') {
            $tableRows = '<thead><tr>
                <th>Station</th><th>Code</th><th>Ward</th>
                <th>Registered</th><th>Cast</th><th>Valid</th><th>Rejected</th>
                <th>Turnout</th><th>Status</th>
            </tr></thead><tbody>';
            foreach ($results as $r) {
                $t = $r->total_registered_voters > 0
                    ? round(($r->total_votes_cast / $r->total_registered_voters) * 100, 1) . '%'
                    : '—';
                $status = str_replace('_', ' ', $r->certification_status);
                $tableRows .= "<tr>
                    <td>{$r->pollingStation->name}</td>
                    <td>{$r->pollingStation->code}</td>
                    <td>{$r->pollingStation->ward->name}</td>
                    <td>{$r->total_registered_voters}</td>
                    <td>{$r->total_votes_cast}</td>
                    <td>{$r->valid_votes}</td>
                    <td>{$r->rejected_votes}</td>
                    <td>$t</td>
                    <td>" . ucwords($status) . "</td>
                </tr>";
            }
            $tableRows .= '</tbody>';
        }

        if ($type === 'ward-summary') {
            $tableRows = '<thead><tr>
                <th>Ward</th><th>Stations</th><th>Reporting</th>
                <th>Total Cast</th><th>Valid Votes</th><th>Turnout</th>
            </tr></thead><tbody>';
            foreach ($wards as $ward) {
                $wardResults = $results->filter(fn($r) => $r->pollingStation->ward_id === $ward->id);
                $wReg  = $wardResults->sum('total_registered_voters');
                $wCast = $wardResults->sum('total_votes_cast');
                $wValid= $wardResults->sum('valid_votes');
                $wTurn = $wReg > 0 ? round(($wCast / $wReg) * 100, 1) . '%' : '—';
                $stationTotal = \App\Models\PollingStation::where('ward_id', $ward->id)->count();
                $tableRows .= "<tr>
                    <td><strong>{$ward->name}</strong></td>
                    <td>{$stationTotal}</td>
                    <td>{$wardResults->count()}</td>
                    <td>" . number_format($wCast) . "</td>
                    <td>" . number_format($wValid) . "</td>
                    <td>$wTurn</td>
                </tr>";
            }
            $tableRows .= '</tbody>';
        }

        if ($type === 'turnout') {
            $tableRows = '<thead><tr>
                <th>Station</th><th>Ward</th><th>Registered</th>
                <th>Votes Cast</th><th>Valid</th><th>Rejected</th><th>Turnout %</th>
            </tr></thead><tbody>';
            $sortedByTurnout = $results->sortByDesc(fn($r) =>
                $r->total_registered_voters > 0
                    ? ($r->total_votes_cast / $r->total_registered_voters)
                    : 0
            );
            foreach ($sortedByTurnout as $r) {
                $t = $r->total_registered_voters > 0
                    ? round(($r->total_votes_cast / $r->total_registered_voters) * 100, 2) . '%'
                    : '—';
                $tableRows .= "<tr>
                    <td>{$r->pollingStation->name}</td>
                    <td>{$r->pollingStation->ward->name}</td>
                    <td>{$r->total_registered_voters}</td>
                    <td>{$r->total_votes_cast}</td>
                    <td>{$r->valid_votes}</td>
                    <td>{$r->rejected_votes}</td>
                    <td><strong>$t</strong></td>
                </tr>";
            }
            $tableRows .= '</tbody>';
        }

        if ($type === 'certification') {
            $tableRows = '<thead><tr>
                <th>Station</th><th>Ward</th><th>Submitted</th>
                <th>Certification Status</th><th>Rejections</th><th>Last Rejection Reason</th>
            </tr></thead><tbody>';
            foreach ($results as $r) {
                $status  = str_replace('_', ' ', ucwords($r->certification_status));
                $reason  = htmlspecialchars($r->last_rejection_reason ?? '—');
                $submitted = $r->submitted_at?->format('d/m/Y H:i') ?? '—';
                $tableRows .= "<tr>
                    <td>{$r->pollingStation->name}</td>
                    <td>{$r->pollingStation->ward->name}</td>
                    <td>$submitted</td>
                    <td>$status</td>
                    <td>{$r->rejection_count}</td>
                    <td style='font-size:11px;'>$reason</td>
                </tr>";
            }
            $tableRows .= '</tbody>';
        }

        // ── Assemble HTML ──────────────────────────────────────────────────
        $html = "<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>{$reportTitle}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1a1a2e; margin: 25px; }
  .header { border-bottom: 3px solid #E91E8C; padding-bottom: 12px; margin-bottom: 20px; }
  .header h1 { font-size: 20px; margin: 0 0 4px 0; color: #1a1a2e; }
  .header p  { margin: 2px 0; color: #555; font-size: 11px; }
  .summary { display: flex; gap: 10px; margin-bottom: 20px; }
  .stat-box { flex: 1; background: #f7f7fa; border: 1px solid #e0e0e8; border-radius: 6px; padding: 10px; text-align: center; }
  .stat-box .val { font-size: 20px; font-weight: bold; color: #E91E8C; }
  .stat-box .lbl { font-size: 10px; color: #666; margin-top: 3px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
  thead tr { background: #1a1a2e; color: white; }
  th { padding: 7px 8px; text-align: left; font-weight: 600; }
  td { padding: 6px 8px; border-bottom: 1px solid #eee; }
  tr:nth-child(even) td { background: #f9f9fb; }
  .footer { margin-top: 20px; font-size: 10px; color: #888; border-top: 1px solid #eee; padding-top: 8px; }
  .pink { color: #E91E8C; }
</style>
</head>
<body>
  <div class='header'>
    <h1>{$reportTitle}</h1>
    <p><strong>Constituency:</strong> {$constituency->name} &nbsp;|&nbsp; <strong>Election:</strong> {$activeElection->name}</p>
    <p><strong>Generated:</strong> {$generatedAt} &nbsp;|&nbsp; <strong>Generated by:</strong> {$user->name}</p>
  </div>

  <div class='summary'>
    <div class='stat-box'><div class='val'>" . number_format($totalRegistered) . "</div><div class='lbl'>Registered Voters</div></div>
    <div class='stat-box'><div class='val'>" . number_format($totalCast) . "</div><div class='lbl'>Votes Cast</div></div>
    <div class='stat-box'><div class='val'>" . number_format($totalValid) . "</div><div class='lbl'>Valid Votes</div></div>
    <div class='stat-box'><div class='val'>" . number_format($totalRejected) . "</div><div class='lbl'>Rejected Votes</div></div>
    <div class='stat-box'><div class='val'>{$turnout}%</div><div class='lbl'>Turnout</div></div>
    <div class='stat-box'><div class='val'>{$results->count()}</div><div class='lbl'>Stations Reporting</div></div>
  </div>

  <table>{$tableRows}</table>

  <div class='footer'>
    Independent Electoral Commission of The Gambia &nbsp;|&nbsp; NERTP System &nbsp;|&nbsp; Confidential
  </div>
</body>
</html>";

        $filename = strtolower(str_replace([' ', '/'], ['-', '-'], $reportTitle)) . '-' . now()->format('Y-m-d') . '.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', $type === 'full' || $type === 'certification' ? 'landscape' : 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->download($filename);

    })->name('reports.export')->middleware('permission:generate-constituency-report');
});