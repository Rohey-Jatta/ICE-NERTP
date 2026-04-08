<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\AuditLog;
use App\Models\Result;
use App\Models\ResultCertification;

Route::middleware(['auth', 'role:admin-area-approver'])
    ->prefix('admin-area')
    ->name('admin-area.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();

        $pending       = 0;
        $approved      = 0;
        $constituencies = 0;
        $awaitingBelow  = 0;

        if ($adminArea) {
            $areaScope = fn($q) => $q->whereHas('pollingStation.ward', fn($q2) =>
                $q2->whereHas('parent', fn($q3) => $q3->where('parent_id', $adminArea->id))
            );

            $pending = $areaScope(Result::query())
                ->where('certification_status', Result::STATUS_PENDING_ADMIN_AREA)
                ->count();

            $approved = $areaScope(Result::query())
                ->whereIn('certification_status', [
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ])->count();

            $awaitingBelow = $areaScope(Result::query())
                ->whereIn('certification_status', [
                    Result::STATUS_SUBMITTED,
                    Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                    Result::STATUS_PENDING_WARD,
                    Result::STATUS_WARD_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                ])->count();

            $constituencies = AdministrativeHierarchy::where('parent_id', $adminArea->id)
                ->where('level', 'constituency')->count();
        }

        $total    = $pending + $approved;
        $progress = $total > 0 ? round(($approved / $total) * 100) : 0;

        return Inertia::render('AdminArea/Dashboard', [
            'auth'           => ['user' => $user],
            'adminArea'      => $adminArea ? ['id' => $adminArea->id, 'name' => $adminArea->name] : null,
            'pendingResults' => $pending,
            'statistics'     => [
                'approved'       => $approved,
                'constituencies' => $constituencies,
                'progress'       => $progress,
                'awaitingBelow'  => $awaitingBelow,
            ],
        ]);
    })->name('dashboard');

    // ── Approval Queue ────────────────────────────────────────────────────────
    Route::get('/approval-queue', function (Request $request) {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();
        $filter    = $request->get('filter', 'pending');

        $results = collect();
        $counts  = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];

        if ($adminArea) {
            $areaScope = fn($q) => $q->whereHas('pollingStation.ward', fn($q2) =>
                $q2->whereHas('parent', fn($q3) => $q3->where('parent_id', $adminArea->id))
            );

            $baseQuery = Result::with([
                'pollingStation.ward.parent',
                'election',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances.politicalParty',
                'submittedBy',
                'certifications' => fn($q) => $q->where('certification_level', 'admin_area')->latest(),
            ]);
            $baseQuery = $areaScope($baseQuery);

            $counts['pending']  = (clone $baseQuery)
                ->where('certification_status', Result::STATUS_PENDING_ADMIN_AREA)->count();
            $counts['approved'] = (clone $baseQuery)
                ->whereIn('certification_status', [
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ])->count();
            $counts['rejected'] = (clone $baseQuery)
                ->where('certification_status', Result::STATUS_PENDING_CONSTITUENCY)
                ->where('rejection_count', '>', 0)->count();
            $counts['all'] = $counts['pending'] + $counts['approved'] + $counts['rejected'];

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
                    'candidate_votes'         => $r->candidateVotes->map(fn($cv) => [
                        'candidate'   => $cv->candidate->name ?? 'Unknown',
                        'party'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                        'party_color' => $cv->candidate->politicalParty->color ?? '#6b7280',
                        'votes'       => $cv->votes,
                    ]),
                    'area_comments'           => $r->certifications->first()?->comments,
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
    })->name('approval-queue');

    // ── Approve ───────────────────────────────────────────────────────────────
    Route::post('/approve/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'nullable|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_ADMIN_AREA) {
            return back()->withErrors(['error' => 'Result is not pending admin-area approval.']);
        }

        $approverId  = Auth::id();
        $adminArea   = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'admin_area')->first();
        // Derive admin area node from station's ward → constituency → admin area
        $wardParentId      = AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('parent_id');
        $adminAreaNodeId   = $adminArea?->id
            ?? AdministrativeHierarchy::where('id', $wardParentId)->value('parent_id')
            ?? AdministrativeHierarchy::where('level', 'admin_area')->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $adminAreaNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'admin_area',
                'hierarchy_node_id'   => $adminAreaNodeId,
                'approver_id'         => $approverId,
                'status'              => 'approved',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update(['certification_status' => Result::STATUS_ADMIN_AREA_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_NATIONAL]);
        });

        AuditLog::record(
            action: 'certification.admin_area.approved',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'success', 'comments' => $request->comments]
        );

        return back()->with('success', 'Result certified at admin-area level and promoted to IEC Chairman queue.');
    })->name('approve');

    // ── Approve with Reservation ──────────────────────────────────────────────
    Route::post('/approve-with-reservation/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_ADMIN_AREA) {
            return back()->withErrors(['error' => 'Result is not pending admin-area approval.']);
        }

        $approverId      = Auth::id();
        $adminArea       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'admin_area')->first();
        $wardParentId    = AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('parent_id');
        $adminAreaNodeId = $adminArea?->id
            ?? AdministrativeHierarchy::where('id', $wardParentId)->value('parent_id')
            ?? AdministrativeHierarchy::where('level', 'admin_area')->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $adminAreaNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'admin_area',
                'hierarchy_node_id'   => $adminAreaNodeId,
                'approver_id'         => $approverId,
                'status'              => 'approved',
                'comments'            => '[RESERVATION] ' . $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update(['certification_status' => Result::STATUS_ADMIN_AREA_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_NATIONAL]);
        });

        AuditLog::record(
            action: 'certification.admin_area.approved_with_reservation',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'success', 'reservation' => $request->comments]
        );

        return back()->with('success', 'Result certified with reservation and promoted to IEC Chairman queue.');
    })->name('approve-with-reservation');

    // ── Reject ────────────────────────────────────────────────────────────────
    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate(['comments' => 'required|string|max:5000']);

        if ($result->certification_status !== Result::STATUS_PENDING_ADMIN_AREA) {
            return back()->withErrors(['error' => 'Result is not pending admin-area approval.']);
        }

        $approverId      = Auth::id();
        $adminArea       = AdministrativeHierarchy::where('assigned_approver_id', $approverId)
            ->where('level', 'admin_area')->first();
        $wardParentId    = AdministrativeHierarchy::where('id', $result->pollingStation?->ward_id)->value('parent_id');
        $adminAreaNodeId = $adminArea?->id
            ?? AdministrativeHierarchy::where('id', $wardParentId)->value('parent_id')
            ?? AdministrativeHierarchy::where('level', 'admin_area')->value('id')
            ?? 1;

        DB::transaction(function () use ($result, $request, $approverId, $adminAreaNodeId) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'admin_area',
                'hierarchy_node_id'   => $adminAreaNodeId,
                'approver_id'         => $approverId,
                'status'              => 'rejected',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update([
                'certification_status'  => Result::STATUS_PENDING_CONSTITUENCY,
                'last_rejection_reason' => $request->comments,
                'last_rejected_by'      => $approverId,
                'last_rejected_at'      => now(),
                'rejection_count'       => $result->rejection_count + 1,
            ]);
        });

        AuditLog::record(
            action: 'certification.admin_area.rejected',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'rejected', 'reason' => $request->comments]
        );

        return back()->with('success', 'Result rejected and returned to constituency level.');
    })->name('reject');

    // ── Constituency Breakdowns ───────────────────────────────────────────────
    Route::get('/constituency-breakdowns', function () {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();

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
                &$totalVotesCounted, &$certifiedCount, &$pendingCount, &$awaitingCount
            ) {
                $allResults = Result::whereHas('pollingStation.ward', fn($q) =>
                    $q->where('parent_id', $constituency->id)
                )->get();

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
    })->name('constituency-breakdowns');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/analytics', function () {
        $user      = Auth::user();
        $adminArea = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'admin_area')->first();

        $stats          = [];
        $constituencies = collect();

        if ($adminArea) {
            $constituencyNodes = AdministrativeHierarchy::where('parent_id', $adminArea->id)
                ->where('level', 'constituency')->get();

            $totalWards = AdministrativeHierarchy::where('level', 'ward')
                ->whereIn('parent_id', $constituencyNodes->pluck('id'))->count();

            $allResults = Result::whereHas('pollingStation.ward', fn($q) =>
                $q->whereHas('parent', fn($q2) => $q2->where('parent_id', $adminArea->id))
            )->get();

            $totalVotes      = $allResults->sum('total_votes_cast');
            $totalRegistered = $allResults->sum('total_registered_voters');
            $certifiedCount  = $allResults->whereIn('certification_status', [
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])->count();

            $stats = [
                'totalConstituencies' => $constituencyNodes->count(),
                'certified'           => $certifiedCount,
                'totalWards'          => $totalWards,
                'totalVotes'          => $totalVotes,
                'avgTurnout'          => $totalRegistered > 0
                    ? round(($totalVotes / $totalRegistered) * 100, 1) : 0,
                'highestTurnout'      => 0,
                'lowestTurnout'       => 0,
            ];

            $turnoutValues  = [];
            $constituencies = $constituencyNodes->map(function ($constituency) use (&$turnoutValues) {
                $results    = Result::whereHas('pollingStation.ward', fn($q) =>
                    $q->where('parent_id', $constituency->id)
                )->get();

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
                $turnoutValues[] = $turnout;

                return [
                    'name'     => $constituency->name,
                    'votes'    => $votes,
                    'progress' => $progress,
                    'turnout'  => $turnout,
                ];
            });

            if (!empty($turnoutValues)) {
                $stats['highestTurnout'] = max($turnoutValues);
                $stats['lowestTurnout']  = min($turnoutValues);
            }
        }

        return Inertia::render('AdminArea/Analytics', [
            'auth'           => ['user' => $user],
            'adminArea'      => $adminArea ? ['id' => $adminArea->id, 'name' => $adminArea->name] : null,
            'stats'          => $stats,
            'constituencies' => $constituencies,
        ]);
    })->name('analytics');
});