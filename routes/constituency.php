<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Result;
use App\Models\ResultCertification;

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

            $base = fn() => Result::where('election_id', $activeElection->id)
                ->whereHas('pollingStation', fn($q) => $q->whereIn('ward_id', $wardIds));

            $stats['pending'] = $base()
                ->where('certification_status', Result::STATUS_PENDING_CONSTITUENCY)->count();

            $stats['certified'] = $base()
                ->whereIn('certification_status', [
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ])->count();

            $stats['rejected'] = $base()
                ->where('certification_status', Result::STATUS_PENDING_WARD)
                ->where('rejection_count', '>', 0)->count();

            $totalResults      = $base()->count();
            $stats['progress'] = $totalResults > 0
                ? round(($stats['certified'] / $totalResults) * 100) : 0;
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

            $counts['pending']   = $base()->where('certification_status', Result::STATUS_PENDING_CONSTITUENCY)->count();
            $counts['certified'] = $base()->whereIn('certification_status', [
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])->count();
            $counts['rejected'] = $base()
                ->where('certification_status', Result::STATUS_PENDING_WARD)
                ->where('rejection_count', '>', 0)->count();
            $counts['all'] = $counts['pending'] + $counts['certified'] + $counts['rejected'];

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
    })->name('approval-queue');

    // ── Approve ───────────────────────────────────────────────────────────────
    Route::post('/approve/{result}', function (Result $result, Request $request) {
        $request->validate(['comments' => 'nullable|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_CONSTITUENCY) {
            return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
        }

        $approverId         = Auth::id();
        $constituency       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'constituency')->first();
        $constituencyNodeId = $constituency?->id
            ?? AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('parent_id')
            ?? AdministrativeHierarchy::where('level', 'constituency')->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $constituencyNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'constituency',
                'hierarchy_node_id'   => $constituencyNodeId,
                'approver_id'         => $approverId,
                'status'              => 'approved',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);
            $result->update(['certification_status' => Result::STATUS_CONSTITUENCY_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_ADMIN_AREA]);
        });

        AuditLog::record(
            action: 'certification.constituency.approved', event: 'updated',
            module: 'Certification', auditable: $result,
            extra: ['outcome' => 'success', 'comments' => $request->comments]
        );

        return back()->with('success', 'Result certified at constituency level and promoted to Admin Area queue.');
    })->name('approve');

    // ── Approve with Reservation ──────────────────────────────────────────────
    Route::post('/approve-with-reservation/{result}', function (Result $result, Request $request) {
        $request->validate(['comments' => 'required|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_CONSTITUENCY) {
            return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
        }

        $approverId         = Auth::id();
        $constituency       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'constituency')->first();
        $constituencyNodeId = $constituency?->id
            ?? AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('parent_id')
            ?? AdministrativeHierarchy::where('level', 'constituency')->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $constituencyNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'constituency',
                'hierarchy_node_id'   => $constituencyNodeId,
                'approver_id'         => $approverId,
                'status'              => 'approved',
                'comments'            => '[RESERVATION] ' . $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);
            $result->update(['certification_status' => Result::STATUS_CONSTITUENCY_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_ADMIN_AREA]);
        });

        AuditLog::record(
            action: 'certification.constituency.approved_with_reservation', event: 'updated',
            module: 'Certification', auditable: $result,
            extra: ['outcome' => 'success', 'reservation' => $request->comments]
        );

        return back()->with('success', 'Result certified with reservation and promoted to Admin Area queue.');
    })->name('approve-with-reservation');

    // ── Reject ────────────────────────────────────────────────────────────────
    Route::post('/reject/{result}', function (Result $result, Request $request) {
        $request->validate(['comments' => 'required|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_CONSTITUENCY) {
            return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
        }

        $approverId         = Auth::id();
        $constituency       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'constituency')->first();
        $constituencyNodeId = $constituency?->id
            ?? AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('parent_id')
            ?? AdministrativeHierarchy::where('level', 'constituency')->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $constituencyNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'constituency',
                'hierarchy_node_id'   => $constituencyNodeId,
                'approver_id'         => $approverId,
                'status'              => 'rejected',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);
            $result->update([
                'certification_status'  => Result::STATUS_PENDING_WARD,
                'last_rejection_reason' => $request->comments,
                'last_rejected_by'      => $approverId,
                'last_rejected_at'      => now(),
                'rejection_count'       => $result->rejection_count + 1,
            ]);
        });

        AuditLog::record(
            action: 'certification.constituency.rejected', event: 'updated',
            module: 'Certification', auditable: $result,
            extra: ['outcome' => 'rejected', 'reason' => $request->comments]
        );

        return back()->with('success', 'Result rejected and returned to Ward Approver.');
    })->name('reject');

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
    })->name('ward-breakdowns');

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
    })->name('reports');
});