<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCertification;

Route::middleware(['auth', 'role:ward-approver'])
    ->prefix('ward')
    ->name('ward.')
    ->group(function () {

    // ── Dashboard ────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user           = Auth::user();
        $activeElection = Election::where('status', 'active')->latest()->first();

        $ward = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')
            ->first();

        if (!$ward) {
            return Inertia::render('Ward/Dashboard', [
                'auth'       => ['user' => $user],
                'ward'       => null,
                'statistics' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'totalStations' => 0, 'progress' => 0],
                'pendingResults' => 0,
            ]);
        }

        $stationIds = PollingStation::where('ward_id', $ward->id)->pluck('id');

        $baseQuery = fn() => Result::whereIn('polling_station_id', $stationIds)
            ->when($activeElection, fn($q) => $q->where('election_id', $activeElection->id));

        $pendingCount = $baseQuery()
            ->where('certification_status', Result::STATUS_PENDING_WARD)
            ->count();

        $certifiedCount = $baseQuery()
            ->whereIn('certification_status', [
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])->count();

        $rejectedCount = $baseQuery()
            ->where('certification_status', Result::STATUS_SUBMITTED)
            ->where('rejection_count', '>', 0)
            ->count();

        $totalStations = $stationIds->count();
        $total         = $pendingCount + $certifiedCount;
        $progress      = $total > 0 ? round(($certifiedCount / $total) * 100) : 0;

        return Inertia::render('Ward/Dashboard', [
            'auth' => ['user' => $user],
            'ward' => ['id' => $ward->id, 'name' => $ward->name, 'code' => $ward->code],
            'statistics' => [
                'totalStations' => $totalStations,
                'pending'       => $pendingCount,
                'approved'      => $certifiedCount,
                'rejected'      => $rejectedCount,
                'progress'      => $progress,
            ],
            'pendingResults' => $pendingCount,
        ]);
    })->name('dashboard');

    // ── Approval Queue ────────────────────────────────────────────────────────
    Route::get('/approval-queue', function (Request $request) {
        $user           = Auth::user();
        $activeElection = Election::where('status', 'active')->latest()->first();
        $ward           = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();
        $filter         = $request->get('filter', 'pending');

        $results = collect();
        $counts  = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];

        if ($ward) {
            $stationIds = PollingStation::where('ward_id', $ward->id)->pluck('id');

            $base = fn() => Result::whereIn('polling_station_id', $stationIds)
                ->when($activeElection, fn($q) => $q->where('election_id', $activeElection->id));

            $counts['pending']  = $base()->where('certification_status', Result::STATUS_PENDING_WARD)->count();
            $counts['approved'] = $base()->whereIn('certification_status', [
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])->count();
            $counts['rejected'] = $base()
                ->where('certification_status', Result::STATUS_SUBMITTED)
                ->where('rejection_count', '>', 0)->count();
            $counts['all']      = $counts['pending'] + $counts['approved'] + $counts['rejected'];

            $query = $base()->with([
                'pollingStation',
                'election',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances.politicalParty',
                'submittedBy',
                'certifications' => fn($q) => $q->where('certification_level', 'ward')->latest(),
            ]);

            match ($filter) {
                'pending'  => $query->where('certification_status', Result::STATUS_PENDING_WARD),
                'approved' => $query->whereIn('certification_status', [
                    Result::STATUS_WARD_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ]),
                'rejected' => $query->where('certification_status', Result::STATUS_SUBMITTED)
                    ->where('rejection_count', '>', 0),
                default    => $query->whereIn('certification_status', [
                    Result::STATUS_PENDING_WARD,
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
    })->name('approval-queue');

    // ── Approve ───────────────────────────────────────────────────────────────
    Route::post('/approve/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'nullable|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        $approverId = Auth::id();
        $ward       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'ward')->first();
        $wardNodeId = $ward?->id
            ?? AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $wardNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'ward',
                'hierarchy_node_id'   => $wardNodeId,
                'approver_id'         => $approverId,
                'status'              => 'approved',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update(['certification_status' => Result::STATUS_WARD_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_CONSTITUENCY]);
        });

        AuditLog::record(
            action:    'certification.ward.approved',
            event:     'updated',
            module:    'Certification',
            auditable: $result,
            extra:     ['outcome' => 'success', 'comments' => $request->comments]
        );

        return back()->with('success', 'Result certified at ward level and promoted to Constituency queue.');
    })->name('approve');

    // ── Approve with Reservation ──────────────────────────────────────────────
    Route::post('/approve-with-reservation/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        $approverId = Auth::id();
        $ward       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'ward')->first();
        $wardNodeId = $ward?->id
            ?? AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $wardNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'ward',
                'hierarchy_node_id'   => $wardNodeId,
                'approver_id'         => $approverId,
                'status'              => 'approved',
                'comments'            => '[RESERVATION] ' . $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update(['certification_status' => Result::STATUS_WARD_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_CONSTITUENCY]);
        });

        AuditLog::record(
            action:    'certification.ward.approved_with_reservation',
            event:     'updated',
            module:    'Certification',
            auditable: $result,
            extra:     ['outcome' => 'success', 'reservation' => $request->comments]
        );

        return back()->with('success', 'Result certified with reservation and promoted to Constituency queue.');
    })->name('approve-with-reservation');

    // ── Reject ────────────────────────────────────────────────────────────────
    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        $approverId = Auth::id();
        $ward       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'ward')->first();
        $wardNodeId = $ward?->id
            ?? AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $wardNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'ward',
                'hierarchy_node_id'   => $wardNodeId,
                'approver_id'         => $approverId,
                'status'              => 'rejected',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update([
                'certification_status'  => Result::STATUS_SUBMITTED,
                'last_rejection_reason' => $request->comments,
                'last_rejected_by'      => $approverId,
                'last_rejected_at'      => now(),
                'rejection_count'       => $result->rejection_count + 1,
            ]);
        });

        AuditLog::record(
            action:    'certification.ward.rejected',
            event:     'updated',
            module:    'Certification',
            auditable: $result,
            extra:     ['outcome' => 'rejected', 'reason' => $request->comments]
        );

        return back()->with('success', 'Result rejected and returned to the Polling Officer.');
    })->name('reject');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/analytics', function () {
        $user           = Auth::user();
        $activeElection = Election::where('status', 'active')->latest()->first();
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
                        $result->certification_status === Result::STATUS_PENDING_WARD => 'Pending',
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
    })->name('analytics');
});