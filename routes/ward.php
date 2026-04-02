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

Route::middleware(['auth', 'role:ward-approver'])
    ->prefix('ward')
    ->name('ward.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user = Auth::user();
        $ward = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();

        $pending = 0;
        $approved = 0;
        $rejected = 0;
        $totalStations = 0;

        if ($ward) {
            $pending = Result::where('certification_status', Result::STATUS_PENDING_WARD)
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))
                ->count();

            $approved = Result::whereIn('certification_status', [
                    Result::STATUS_WARD_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ])
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))
                ->count();

            $rejected = Result::where('certification_status', Result::STATUS_SUBMITTED)
                ->where('rejection_count', '>', 0)
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))
                ->count();

            $totalStations = \App\Models\PollingStation::where('ward_id', $ward->id)->count();
        }

        return Inertia::render('Ward/Dashboard', [
            'auth'           => ['user' => $user],
            'ward'           => $ward ? ['id' => $ward->id, 'name' => $ward->name] : null,
            'pendingResults' => $pending,
            'statistics'     => [
                'approved'      => $approved,
                'rejected'      => $rejected,
                'totalStations' => $totalStations,
                'progress'      => $totalStations > 0
                    ? round((($approved) / $totalStations) * 100)
                    : 0,
            ],
        ]);
    })->name('dashboard');

    // ── Approval Queue ────────────────────────────────────────────────────────
    Route::get('/approval-queue', function (Request $request) {
        $user   = Auth::user();
        $ward   = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();
        $filter = $request->get('filter', 'pending');

        $results = collect();

        if ($ward) {
            $query = Result::with([
                    'pollingStation',
                    'candidateVotes.candidate.politicalParty',
                    'partyAcceptances.politicalParty',
                    'submittedBy',
                    'certifications' => fn($q) => $q->where('certification_level', 'ward')->latest(),
                ])
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id));

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
                $partyAccepted = $r->partyAcceptances->where('status', 'accepted')->count()
                               + $r->partyAcceptances->where('status', 'accepted_with_reservation')->count();
                $partyTotal    = $r->partyAcceptances->count();

                $photoUrl = null;
                if ($r->result_sheet_photo_path) {
                    $photoUrl = asset('storage/' . $r->result_sheet_photo_path);
                }

                return [
                    'id'                       => $r->id,
                    'polling_station'          => $r->pollingStation->name ?? 'Unknown',
                    'polling_station_code'     => $r->pollingStation->code ?? '-',
                    'officer'                  => $r->submittedBy->name ?? 'Unknown',
                    'officer_email'            => $r->submittedBy->email ?? '',
                    'submitted_at'             => $r->submitted_at?->format('Y-m-d H:i'),
                    'certification_status'     => $r->certification_status,
                    'total_registered_voters'  => $r->total_registered_voters,
                    'total_votes_cast'         => $r->total_votes_cast,
                    'valid_votes'              => $r->valid_votes,
                    'rejected_votes'           => $r->rejected_votes,
                    'disputed_votes'           => $r->disputed_votes,
                    'turnout'                  => $r->getTurnoutPercentage(),
                    'rejection_count'          => $r->rejection_count,
                    'last_rejection_reason'    => $r->last_rejection_reason,
                    'photo_url'                => $photoUrl,
                    'party_accepted'           => $partyAccepted,
                    'party_total'              => $partyTotal,
                    'party_acceptances'        => $r->partyAcceptances->map(fn($pa) => [
                        'party'    => $pa->politicalParty->name ?? 'Unknown',
                        'abbr'     => $pa->politicalParty->abbreviation ?? '?',
                        'status'   => $pa->status,
                        'comments' => $pa->comments,
                    ]),
                    'candidate_votes'          => $r->candidateVotes->map(fn($cv) => [
                        'candidate'   => $cv->candidate->name ?? 'Unknown',
                        'party'       => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                        'party_color' => $cv->candidate->politicalParty->color ?? '#6b7280',
                        'votes'       => $cv->votes,
                    ]),
                    'ward_comments'            => $r->certifications->first()?->comments,
                ];
            });
        }

        // Counts for filter badges
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
        if ($ward) {
            $counts['pending']  = Result::where('certification_status', Result::STATUS_PENDING_WARD)
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))->count();
            $counts['approved'] = Result::whereIn('certification_status', [
                    Result::STATUS_WARD_CERTIFIED, Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED, Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED, Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                ])
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))->count();
            $counts['rejected'] = Result::where('certification_status', Result::STATUS_SUBMITTED)
                ->where('rejection_count', '>', 0)
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))->count();
            $counts['all']      = $counts['pending'] + $counts['approved'] + $counts['rejected'];
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
        $request->validate([
            'comments' => 'nullable|string|max:5000',
        ]);

        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        DB::transaction(function () use ($result, $request) {
            // Record certification
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'ward',
                'hierarchy_node_id'   => \App\Models\AdministrativeHierarchy::where('assigned_approver_id', Auth::id())
                    ->where('level', 'ward')->value('id') ?? 1,
                'approver_id'         => Auth::id(),
                'status'              => 'approved',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            // Certify at ward level then auto-promote to constituency queue
            $result->update(['certification_status' => Result::STATUS_WARD_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_CONSTITUENCY]);
        });

        AuditLog::record(
            action: 'certification.ward.approved',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'success', 'comments' => $request->comments]
        );

        return back()->with('success', 'Result certified at ward level and promoted to constituency queue.');
    })->name('approve');

    // ── Approve with Reservation ──────────────────────────────────────────────
    Route::post('/approve-with-reservation/{result}', function (Request $request, Result $result) {
        $request->validate([
            'comments' => 'required|string|max:5000',
        ]);

        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        DB::transaction(function () use ($result, $request) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'ward',
                'hierarchy_node_id'   => \App\Models\AdministrativeHierarchy::where('assigned_approver_id', Auth::id())
                    ->where('level', 'ward')->value('id') ?? 1,
                'approver_id'         => Auth::id(),
                'status'              => 'approved',
                'comments'            => '[RESERVATION] ' . $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update(['certification_status' => Result::STATUS_WARD_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_CONSTITUENCY]);
        });

        AuditLog::record(
            action: 'certification.ward.approved_with_reservation',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'success', 'reservation' => $request->comments]
        );

        return back()->with('success', 'Result certified with reservation and promoted to constituency queue.');
    })->name('approve-with-reservation');

    // ── Reject ────────────────────────────────────────────────────────────────
    Route::post('/reject/{result}', function (Request $request, Result $result) {
        $request->validate([
            'comments' => 'required|string|max:5000',
        ]);

        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }

        DB::transaction(function () use ($result, $request) {
            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => 'ward',
                'hierarchy_node_id'   => \App\Models\AdministrativeHierarchy::where('assigned_approver_id', Auth::id())
                    ->where('level', 'ward')->value('id') ?? 1,
                'approver_id'         => Auth::id(),
                'status'              => 'rejected',
                'comments'            => $request->comments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update([
                'certification_status'  => Result::STATUS_SUBMITTED,
                'last_rejection_reason' => $request->comments,
                'last_rejected_by'      => Auth::id(),
                'last_rejected_at'      => now(),
                'rejection_count'       => $result->rejection_count + 1,
            ]);
        });

        AuditLog::record(
            action: 'certification.ward.rejected',
            event: 'updated',
            module: 'Certification',
            auditable: $result,
            extra: ['outcome' => 'rejected', 'reason' => $request->comments]
        );

        return back()->with('success', 'Result rejected and returned to polling officer.');
    })->name('reject');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('/analytics', function () {
        $user = Auth::user();
        $ward = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();

        $stats          = [];
        $stationBreakdown = [];

        if ($ward) {
            $stations = \App\Models\PollingStation::where('ward_id', $ward->id)
                ->with(['latestResult'])
                ->get();

            $certified = 0;
            $pending   = 0;
            $rejected  = 0;
            $totalVotes = 0;
            $totalRegistered = 0;

            foreach ($stations as $station) {
                $result = $station->latestResult;
                $statusLabel = 'Not Reported';

                if ($result) {
                    $totalVotes     += $result->total_votes_cast;
                    $totalRegistered += $result->total_registered_voters;

                    if (in_array($result->certification_status, [
                        Result::STATUS_WARD_CERTIFIED,
                        Result::STATUS_PENDING_CONSTITUENCY,
                        Result::STATUS_CONSTITUENCY_CERTIFIED,
                        Result::STATUS_PENDING_ADMIN_AREA,
                        Result::STATUS_ADMIN_AREA_CERTIFIED,
                        Result::STATUS_PENDING_NATIONAL,
                        Result::STATUS_NATIONALLY_CERTIFIED,
                    ])) {
                        $certified++;
                        $statusLabel = 'Certified';
                    } elseif ($result->certification_status === Result::STATUS_PENDING_WARD) {
                        $pending++;
                        $statusLabel = 'Pending';
                    } elseif ($result->certification_status === Result::STATUS_SUBMITTED && $result->rejection_count > 0) {
                        $rejected++;
                        $statusLabel = 'Rejected';
                    } else {
                        $pending++;
                        $statusLabel = 'Submitted';
                    }
                }

                $stationBreakdown[] = [
                    'name'    => $station->name,
                    'code'    => $station->code,
                    'voters'  => $station->registered_voters,
                    'votes'   => $result?->total_votes_cast ?? 0,
                    'turnout' => $result && $station->registered_voters > 0
                        ? round(($result->total_votes_cast / $station->registered_voters) * 100, 1)
                        : 0,
                    'status'  => $statusLabel,
                ];
            }

            $stats = [
                'totalStations' => $stations->count(),
                'certified'     => $certified,
                'pending'       => $pending,
                'rejected'      => $rejected,
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
