<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\PartyAcceptance;
use App\Models\PartyRepresentative;
use App\Models\PollingStation;
use App\Models\Result;

Route::middleware(['auth', 'role:party-representative'])
    ->prefix('party')
    ->name('party.')
    ->group(function () {

    $getActiveElection = fn() => Election::where('status', 'active')->latest()->first();

    $getRep = function () {
        return PartyRepresentative::where('user_id', Auth::id())
            ->with(['politicalParty', 'pollingStations'])
            ->first();
    };

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () use ($getRep, $getActiveElection) {
        $rep            = $getRep();
        $activeElection = $getActiveElection();

        if (!$rep || !$rep->politicalParty) {
            return Inertia::render('Party/Dashboard', [
                'auth'             => ['user' => Auth::user()],
                'party'            => null,
                'assignedStations' => [],
                'statistics'       => [
                    'pendingAcceptance'       => 0,
                    'accepted'                => 0,
                    'acceptedWithReservation' => 0,
                    'disputed'                => 0,
                    'totalStations'           => 0,
                ],
                'noAssignment' => true,
            ]);
        }

        $stationIds = $rep->pollingStations->pluck('id');

        // Always build the assigned stations list regardless of election status
        $assignedStationsData = $rep->pollingStations->map(fn($s) => [
            'id'   => $s->id,
            'name' => $s->name,
            'code' => $s->code,
        ]);

        // If no stations assigned OR no active election, return real station data
        // but zero out the election-dependent statistics
        if ($stationIds->isEmpty() || !$activeElection) {
            return Inertia::render('Party/Dashboard', [
                'auth'             => ['user' => Auth::user()],
                'party'            => [
                    'id'           => $rep->political_party_id,
                    'name'         => $rep->politicalParty->name,
                    'abbreviation' => $rep->politicalParty->abbreviation,
                    'color'        => $rep->politicalParty->color,
                ],
                'assignedStations' => $assignedStationsData,
                'statistics'       => [
                    'pendingAcceptance'       => 0,
                    'accepted'                => 0,
                    'acceptedWithReservation' => 0,
                    'disputed'                => 0,
                    'totalStations'           => $stationIds->count(),
                ],
                'noAssignment' => false,
            ]);
        }

        $cacheKey      = "party_dashboard_{$rep->id}_{$activeElection->id}";
        $dashboardData = Cache::remember($cacheKey, 30, function () use ($rep, $activeElection, $stationIds) {
            // Results available for party review (parallel workflow — all active stages)
            $resultIds = Result::whereIn('polling_station_id', $stationIds)
                ->where('election_id', $activeElection->id)
                ->whereIn('certification_status', Result::PARTY_ACCEPTABLE_STATUSES)
                ->pluck('id');

            $decidedResultIds = PartyAcceptance::where('political_party_id', $rep->political_party_id)
                ->where('is_final', true)
                ->whereIn('result_id', $resultIds)
                ->pluck('result_id');

            $pendingAcceptance = $resultIds->diff($decidedResultIds)->count();

            $acceptanceCounts = PartyAcceptance::where('political_party_id', $rep->political_party_id)
                ->where('is_final', true)
                ->whereIn('result_id', $resultIds)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            return [
                'pendingAcceptance' => $pendingAcceptance,
                'acceptanceCounts'  => $acceptanceCounts,
            ];
        });

        $pendingAcceptance = $dashboardData['pendingAcceptance'];
        $acceptanceCounts  = $dashboardData['acceptanceCounts'];

        return Inertia::render('Party/Dashboard', [
            'auth'             => ['user' => Auth::user()],
            'party'            => [
                'id'           => $rep->politicalParty->id,
                'name'         => $rep->politicalParty->name,
                'abbreviation' => $rep->politicalParty->abbreviation,
                'color'        => $rep->politicalParty->color,
            ],
            'assignedStations' => $assignedStationsData,
            'statistics' => [
                'pendingAcceptance'       => $pendingAcceptance,
                'accepted'                => $acceptanceCounts->get('accepted', 0),
                'acceptedWithReservation' => $acceptanceCounts->get('accepted_with_reservation', 0),
                'disputed'                => $acceptanceCounts->get('rejected', 0),
                'totalStations'           => $stationIds->count(),
            ],
            'noAssignment' => false,
        ]);
    })->name('dashboard')->middleware('permission:view-party-dashboard');

    // ── Stations overview ─────────────────────────────────────────────────────
    Route::get('/stations', function () use ($getRep, $getActiveElection) {
        $rep            = $getRep();
        $activeElection = $getActiveElection();

        if (!$rep || !$rep->politicalParty) {
            return Inertia::render('Party/Stations', [
                'auth'     => ['user' => Auth::user()],
                'stations' => [],
                'party'    => null,
            ]);
        }

        $stationIds = $rep->pollingStations->pluck('id');

        $results = $activeElection
            ? Result::whereIn('polling_station_id', $stationIds)
                ->where('election_id', $activeElection->id)
                ->whereNotIn('certification_status', [Result::STATUS_SUBMITTED, Result::STATUS_REJECTED])
                ->get()
                ->keyBy('polling_station_id')
            : collect();

        $resultIds   = $results->pluck('id');
        $acceptances = PartyAcceptance::where('political_party_id', $rep->political_party_id)
            ->whereIn('result_id', $resultIds)
            ->get()
            ->keyBy('result_id');

        $stations = $rep->pollingStations->map(function ($station) use ($results, $acceptances) {
            $result     = $results->get($station->id);
            $acceptance = $result ? $acceptances->get($result->id) : null;

            return [
                'id'                   => $station->id,
                'name'                 => $station->name,
                'code'                 => $station->code,
                'registered_voters'    => $station->registered_voters,
                'has_result'           => $result !== null,
                'result_id'            => $result?->id,
                'result_status'        => $result?->certification_status ?? 'no_result',
                'total_votes_cast'     => $result?->total_votes_cast ?? 0,
                'turnout_percentage'   => ($result && $station->registered_voters > 0)
                    ? round(($result->total_votes_cast / $station->registered_voters) * 100, 1) : 0,
                'acceptance_status'    => $acceptance?->status ?? 'pending',
                'acceptance_is_final'  => $acceptance?->is_final ?? false,
                'acceptance_comments'  => $acceptance?->comments,
            ];
        });

        return Inertia::render('Party/Stations', [
            'auth'     => ['user' => Auth::user()],
            'stations' => $stations,
            'party'    => [
                'name'         => $rep->politicalParty->name,
                'abbreviation' => $rep->politicalParty->abbreviation,
                'color'        => $rep->politicalParty->color,
            ],
        ]);
    })->name('stations')->middleware('permission:view-assigned-stations');

    // ── Pending acceptance ────────────────────────────────────────────────────
    Route::get('/pending-acceptance', function () use ($getRep, $getActiveElection) {
        $rep            = $getRep();
        $activeElection = $getActiveElection();

        if (!$rep || !$rep->politicalParty) {
            return Inertia::render('Party/PendingAcceptance', [
                'auth'           => ['user' => Auth::user()],
                'pendingResults' => [],
                'party'          => null,
            ]);
        }

        if (!$activeElection) {
            return Inertia::render('Party/PendingAcceptance', [
                'auth'           => ['user' => Auth::user()],
                'pendingResults' => [],
                'party'          => [
                    'id'           => $rep->political_party_id,
                    'name'         => $rep->politicalParty->name,
                    'abbreviation' => $rep->politicalParty->abbreviation,
                    'color'        => $rep->politicalParty->color,
                ],
            ]);
        }

        $stationIds = $rep->pollingStations->pluck('id');

        if ($stationIds->isEmpty()) {
            return Inertia::render('Party/PendingAcceptance', [
                'auth'           => ['user' => Auth::user()],
                'pendingResults' => [],
                'party'          => [
                    'id'           => $rep->political_party_id,
                    'name'         => $rep->politicalParty->name,
                    'abbreviation' => $rep->politicalParty->abbreviation,
                    'color'        => $rep->politicalParty->color,
                ],
            ]);
        }

        $decidedResultIds = PartyAcceptance::where('political_party_id', $rep->political_party_id)
            ->where('is_final', true)
            ->pluck('result_id');

        // Parallel workflow: show results in all active stages (not just pending_party_acceptance)
        $results = Result::whereIn('polling_station_id', $stationIds)
            ->where('election_id', $activeElection->id)
            ->whereIn('certification_status', Result::PARTY_ACCEPTABLE_STATUSES)
            ->whereNotIn('id', $decidedResultIds)
            ->with([
                'pollingStation',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances.politicalParty',
            ])
            ->latest('submitted_at')
            ->get()
            ->map(fn($r) => [
                'id'                      => $r->id,
                'polling_station_id'      => $r->polling_station_id,
                'polling_station_name'    => $r->pollingStation->name,
                'polling_station_code'    => $r->pollingStation->code,
                'certification_status'    => $r->certification_status,
                'total_registered_voters' => $r->total_registered_voters,
                'total_votes_cast'        => $r->total_votes_cast,
                'valid_votes'             => $r->valid_votes,
                'rejected_votes'          => $r->rejected_votes,
                'turnout_percentage'      => $r->total_registered_voters > 0
                    ? round(($r->total_votes_cast / $r->total_registered_voters) * 100, 1) : 0,
                'candidate_votes'         => $r->candidateVotes->map(fn($cv) => [
                    'candidate_id'   => $cv->candidate_id,
                    'candidate_name' => $cv->candidate->name ?? 'Unknown',
                    'party_name'     => $cv->candidate->politicalParty->name ?? 'Independent',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
                'photo_url'  => $r->result_sheet_photo_path
                    ? asset('storage/' . $r->result_sheet_photo_path) : null,
                'submitted_at' => $r->submitted_at?->format('Y-m-d H:i'),
                'other_party_acceptances' => $r->partyAcceptances->map(fn($pa) => [
                    'party_name' => $pa->politicalParty->name ?? 'Unknown',
                    'abbr'       => $pa->politicalParty->abbreviation ?? '?',
                    'status'     => $pa->status,
                ]),
            ]);

        return Inertia::render('Party/PendingAcceptance', [
            'auth'           => ['user' => Auth::user()],
            'pendingResults' => $results,
            'party'          => [
                'id'           => $rep->political_party_id,
                'name'         => $rep->politicalParty->name,
                'abbreviation' => $rep->politicalParty->abbreviation,
                'color'        => $rep->politicalParty->color,
            ],
        ]);
    })->name('pending-acceptance')->middleware('permission:view-assigned-stations');

    // ── View single result details ────────────────────────────────────────────
    Route::get('/result/{result}', function (Result $result) use ($getRep, $getActiveElection) {
        $rep            = $getRep();
        $activeElection = $getActiveElection();

        if (!$rep || !$rep->politicalParty) abort(403, 'No party representative record found.');

        if (!$activeElection || $result->election_id !== $activeElection->id) {
            return redirect()->route('party.pending-acceptance')
                ->with('error', 'This result is not part of the active election.');
        }

        $assignedStationIds = $rep->pollingStations->pluck('id');
        if (!$assignedStationIds->contains($result->polling_station_id)) {
            abort(403, 'You are not assigned to this polling station.');
        }

        // Parallel workflow: result must be in an acceptable stage
        if (!in_array($result->certification_status, Result::PARTY_ACCEPTABLE_STATUSES)) {
            return back()->with('error', 'Result is not yet available for party review.');
        }

        $result->load([
            'pollingStation',
            'candidateVotes.candidate.politicalParty',
            'partyAcceptances.politicalParty',
        ]);

        $myAcceptance = PartyAcceptance::where('result_id', $result->id)
            ->where('political_party_id', $rep->political_party_id)
            ->first();

        return Inertia::render('Party/ResultDetail', [
            'auth'  => ['user' => Auth::user()],
            'party' => [
                'id'           => $rep->political_party_id,
                'name'         => $rep->politicalParty->name,
                'abbreviation' => $rep->politicalParty->abbreviation,
                'color'        => $rep->politicalParty->color,
            ],
            'result' => [
                'id'                      => $result->id,
                'polling_station_id'      => $result->polling_station_id,
                'polling_station_name'    => $result->pollingStation->name,
                'polling_station_code'    => $result->pollingStation->code,
                'certification_status'    => $result->certification_status,
                'total_registered_voters' => $result->total_registered_voters,
                'total_votes_cast'        => $result->total_votes_cast,
                'valid_votes'             => $result->valid_votes,
                'rejected_votes'          => $result->rejected_votes,
                'disputed_votes'          => $result->disputed_votes,
                'turnout_percentage'      => $result->total_registered_voters > 0
                    ? round(($result->total_votes_cast / $result->total_registered_voters) * 100, 1) : 0,
                'submitted_at' => $result->submitted_at?->format('Y-m-d H:i'),
                'photo_url'    => $result->result_sheet_photo_path
                    ? asset('storage/' . $result->result_sheet_photo_path) : null,
                'candidate_votes' => $result->candidateVotes->map(fn($cv) => [
                    'candidate_id'   => $cv->candidate_id,
                    'candidate_name' => $cv->candidate->name ?? 'Unknown',
                    'party_name'     => $cv->candidate->politicalParty->name ?? 'Independent',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
                'other_party_acceptances' => $result->partyAcceptances
                    ->where('political_party_id', '!=', $rep->political_party_id)
                    ->map(fn($pa) => [
                        'party_name' => $pa->politicalParty->name ?? 'Unknown',
                        'abbr'       => $pa->politicalParty->abbreviation ?? '?',
                        'status'     => $pa->status,
                        'comments'   => $pa->comments,
                    ]),
            ],
            'myAcceptance' => $myAcceptance ? [
                'id'         => $myAcceptance->id,
                'status'     => $myAcceptance->status,
                'comments'   => $myAcceptance->comments,
                'is_final'   => $myAcceptance->is_final,
                'decided_at' => $myAcceptance->decided_at?->format('Y-m-d H:i'),
            ] : null,
        ]);
    })->name('result.show')->middleware('permission:view-assigned-stations');

    // ── Submit acceptance decision ────────────────────────────────────────────
    Route::post('/result/{result}/decide', function (Request $request, Result $result) use ($getRep, $getActiveElection) {
        $request->validate([
            'status'   => ['required', 'in:accepted,accepted_with_reservation,rejected'],
            'comments' => ['required_if:status,accepted_with_reservation,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        $rep            = $getRep();
        $activeElection = $getActiveElection();

        if (!$rep || !$rep->politicalParty) abort(403, 'No party representative record found.');

        if (!$activeElection || $result->election_id !== $activeElection->id) {
            return back()->withErrors(['error' => 'This result is not part of the active election.']);
        }

        $assignedStationIds = $rep->pollingStations->pluck('id');
        if (!$assignedStationIds->contains($result->polling_station_id)) {
            abort(403, 'You are not assigned to this polling station.');
        }

        // Parallel workflow: allow responses for results in any active stage
        if (!in_array($result->certification_status, Result::PARTY_ACCEPTABLE_STATUSES)) {
            return back()->withErrors(['error' => 'This result is not available for review at this stage.']);
        }

        $existing = PartyAcceptance::where('result_id', $result->id)
            ->where('political_party_id', $rep->political_party_id)->first();

        if ($existing && $existing->is_final) {
            return back()->withErrors(['error' => 'Your party has already submitted a final decision for this result.']);
        }

        $acceptance = PartyAcceptance::updateOrCreate(
            ['result_id' => $result->id, 'political_party_id' => $rep->political_party_id],
            [
                'party_representative_id' => $rep->id,
                'election_id'             => $result->election_id,
                'status'                  => $request->status,
                'comments'                => $request->comments,
                'decided_at'              => now(),
                'is_final'                => true,
            ]
        );

        AuditLog::record(
            action: 'party_acceptance.' . $request->status, event: 'created',
            module: 'PartyAcceptance', auditable: $acceptance,
            extra: ['election_id' => $result->election_id, 'result_id' => $result->id,
                    'status' => $request->status, 'outcome' => 'success']
        );

        $label = match($request->status) {
            'accepted'                  => 'accepted',
            'accepted_with_reservation' => 'accepted with reservation',
            'rejected'                  => 'rejected/disputed',
            default                     => $request->status,
        };

        return redirect()->route('party.pending-acceptance')
            ->with('success', "Result {$label} successfully. Your decision has been recorded.");
    })->name('result.decide')->middleware('permission:accept-result|reject-result');

    // ── Dashboard overview ────────────────────────────────────────────────────
    Route::get('/dashboard-overview', function () use ($getRep) {
        $rep = $getRep();
        return Inertia::render('Party/DashboardOverview', [
            'auth'  => ['user' => Auth::user()],
            'party' => ($rep && $rep->politicalParty) ? [
                'name'         => $rep->politicalParty->name,
                'abbreviation' => $rep->politicalParty->abbreviation,
                'color'        => $rep->politicalParty->color,
            ] : null,
        ]);
    })->name('dashboard-overview')->middleware('permission:view-party-dashboard');
});
