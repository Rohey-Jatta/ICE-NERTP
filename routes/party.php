<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    // ── Helper: get the current rep's record ──────────────────────────────────
    $getRep = function () {
        return PartyRepresentative::where('user_id', Auth::id())
            ->with(['politicalParty', 'pollingStations'])
            ->first();
    };

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () use ($getRep) {
        $rep = $getRep();

        if (!$rep) {
            return Inertia::render('Party/Dashboard', [
                'auth'             => ['user' => Auth::user()],
                'party'            => null,
                'assignedStations' => [],
                'statistics'       => ['pendingAcceptance' => 0, 'accepted' => 0, 'disputed' => 0, 'totalStations' => 0],
                'noAssignment'     => true,
            ]);
        }

        $stationIds = $rep->pollingStations->pluck('id');

        // Count results by acceptance status for THIS party
        $pendingAcceptance = Result::whereIn('polling_station_id', $stationIds)
            ->whereIn('certification_status', [
                Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                Result::STATUS_PENDING_WARD,
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])
            ->whereDoesntHave('partyAcceptances', fn($q) =>
                $q->where('political_party_id', $rep->political_party_id)->where('is_final', true)
            )
            ->count();

        $accepted = PartyAcceptance::where('political_party_id', $rep->political_party_id)
            ->where('status', 'accepted')
            ->where('is_final', true)
            ->count();

        $acceptedWithReservation = PartyAcceptance::where('political_party_id', $rep->political_party_id)
            ->where('status', 'accepted_with_reservation')
            ->where('is_final', true)
            ->count();

        $disputed = PartyAcceptance::where('political_party_id', $rep->political_party_id)
            ->where('status', 'rejected')
            ->where('is_final', true)
            ->count();

        return Inertia::render('Party/Dashboard', [
            'auth'             => ['user' => Auth::user()],
            'party'            => [
                'id'           => $rep->politicalParty->id,
                'name'         => $rep->politicalParty->name,
                'abbreviation' => $rep->politicalParty->abbreviation,
                'color'        => $rep->politicalParty->color,
            ],
            'assignedStations' => $rep->pollingStations->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ]),
            'statistics' => [
                'pendingAcceptance'       => $pendingAcceptance,
                'accepted'                => $accepted,
                'acceptedWithReservation' => $acceptedWithReservation,
                'disputed'                => $disputed,
                'totalStations'           => $stationIds->count(),
            ],
            'noAssignment' => false,
        ]);
    })->name('dashboard');

    // ── Stations overview ─────────────────────────────────────────────────────
    Route::get('/stations', function () use ($getRep) {
        $rep = $getRep();

        if (!$rep) {
            return Inertia::render('Party/Stations', [
                'auth'     => ['user' => Auth::user()],
                'stations' => [],
                'party'    => null,
            ]);
        }

        $stationIds = $rep->pollingStations->pluck('id');

        // For each station, find the latest result and this party's acceptance
        $stations = $rep->pollingStations->map(function ($station) use ($rep) {
            $result = Result::where('polling_station_id', $station->id)
                ->whereNotIn('certification_status', [Result::STATUS_SUBMITTED, Result::STATUS_REJECTED])
                ->latest('submitted_at')
                ->first();

            $acceptance = null;
            if ($result) {
                $acceptance = PartyAcceptance::where('result_id', $result->id)
                    ->where('political_party_id', $rep->political_party_id)
                    ->first();
            }

            return [
                'id'                   => $station->id,
                'name'                 => $station->name,
                'code'                 => $station->code,
                'registered_voters'    => $station->registered_voters,
                'has_result'           => $result !== null,
                'result_id'            => $result?->id,
                'result_status'        => $result?->certification_status ?? 'no_result',
                'total_votes_cast'     => $result?->total_votes_cast ?? 0,
                'turnout_percentage'   => $result && $station->registered_voters > 0
                    ? round(($result->total_votes_cast / $station->registered_voters) * 100, 1)
                    : 0,
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
    })->name('stations');

    // ── Pending acceptance (results this party hasn't decided on yet) ──────────
    Route::get('/pending-acceptance', function () use ($getRep) {
        $rep = $getRep();

        if (!$rep) {
            return Inertia::render('Party/PendingAcceptance', [
                'auth'           => ['user' => Auth::user()],
                'pendingResults' => [],
                'party'          => null,
            ]);
        }

        $stationIds = $rep->pollingStations->pluck('id');

        // Results that exist for our stations AND this party hasn't given a final decision on yet
        $results = Result::whereIn('polling_station_id', $stationIds)
            ->whereNotIn('certification_status', [Result::STATUS_SUBMITTED, Result::STATUS_REJECTED])
            ->whereDoesntHave('partyAcceptances', fn($q) =>
                $q->where('political_party_id', $rep->political_party_id)->where('is_final', true)
            )
            ->with([
                'pollingStation',
                'candidateVotes.candidate.politicalParty',
                'partyAcceptances' => fn($q) => $q->with('politicalParty'),
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
                    ? round(($r->total_votes_cast / $r->total_registered_voters) * 100, 1)
                    : 0,
                'candidate_votes'         => $r->candidateVotes->map(fn($cv) => [
                    'candidate_id'   => $cv->candidate_id,
                    'candidate_name' => $cv->candidate->name ?? 'Unknown',
                    'party_name'     => $cv->candidate->politicalParty->name ?? 'Independent',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
                'photo_url'               => $r->result_sheet_photo_path
                    ? asset('storage/' . $r->result_sheet_photo_path)
                    : null,
                'submitted_at'            => $r->submitted_at?->format('Y-m-d H:i'),
                // Other parties' decisions for context
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
    })->name('pending-acceptance');

    // ── View single result details ─────────────────────────────────────────────
    Route::get('/result/{result}', function (Result $result) use ($getRep) {
        $rep = $getRep();

        if (!$rep) {
            abort(403, 'No party representative record found.');
        }

        // Permission check: result must be for an assigned station
        $assignedStationIds = $rep->pollingStations->pluck('id');
        if (!$assignedStationIds->contains($result->polling_station_id)) {
            abort(403, 'You are not assigned to this polling station.');
        }

        // Result must not be in submitted/raw state
        if (in_array($result->certification_status, [Result::STATUS_SUBMITTED])) {
            return back()->with('error', 'Result is not yet available for review.');
        }

        $result->load([
            'pollingStation',
            'candidateVotes.candidate.politicalParty',
            'partyAcceptances.politicalParty',
        ]);

        // This party's existing acceptance, if any
        $myAcceptance = PartyAcceptance::where('result_id', $result->id)
            ->where('political_party_id', $rep->political_party_id)
            ->first();

        return Inertia::render('Party/ResultDetail', [
            'auth'   => ['user' => Auth::user()],
            'party'  => [
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
                    ? round(($result->total_votes_cast / $result->total_registered_voters) * 100, 1)
                    : 0,
                'submitted_at'            => $result->submitted_at?->format('Y-m-d H:i'),
                'photo_url'               => $result->result_sheet_photo_path
                    ? asset('storage/' . $result->result_sheet_photo_path)
                    : null,
                'candidate_votes'         => $result->candidateVotes->map(fn($cv) => [
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
    })->name('result.show');

    // ── Submit acceptance decision ─────────────────────────────────────────────
    Route::post('/result/{result}/decide', function (Request $request, Result $result) use ($getRep) {
        $request->validate([
            'status'   => ['required', 'in:accepted,accepted_with_reservation,rejected'],
            'comments' => ['required_if:status,accepted_with_reservation,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        $rep = $getRep();

        if (!$rep) {
            abort(403, 'No party representative record found.');
        }

        // Permission: must be assigned to this station
        $assignedStationIds = $rep->pollingStations->pluck('id');
        if (!$assignedStationIds->contains($result->polling_station_id)) {
            abort(403, 'You are not assigned to this polling station.');
        }

        // Check if already submitted a final decision
        $existing = PartyAcceptance::where('result_id', $result->id)
            ->where('political_party_id', $rep->political_party_id)
            ->first();

        if ($existing && $existing->is_final) {
            return back()->withErrors(['error' => 'Your party has already submitted a final decision for this result.']);
        }

        $acceptance = PartyAcceptance::updateOrCreate(
            [
                'result_id'          => $result->id,
                'political_party_id' => $rep->political_party_id,
            ],
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
            action: 'party_acceptance.' . $request->status,
            event: 'created',
            module: 'PartyAcceptance',
            auditable: $acceptance,
            extra: [
                'election_id' => $result->election_id,
                'result_id'   => $result->id,
                'status'      => $request->status,
                'outcome'     => 'success',
            ]
        );

        // Check if all parties for this election have responded → advance to pending_ward
        $this_closure_result = $result; // capture for closure
        $totalParties     = \App\Models\PoliticalParty::where('election_id', $result->election_id)->count();
        $respondedParties = PartyAcceptance::where('result_id', $result->id)->where('is_final', true)->count();

        if ($respondedParties >= $totalParties) {
            if ($result->certification_status === Result::STATUS_PENDING_PARTY_ACCEPTANCE) {
                $result->update(['certification_status' => Result::STATUS_PENDING_WARD]);
            }
        }

        $label = match($request->status) {
            'accepted'                  => 'accepted',
            'accepted_with_reservation' => 'accepted with reservation',
            'rejected'                  => 'rejected/disputed',
            default                     => $request->status,
        };

        return redirect()->route('party.pending-acceptance')
            ->with('success', "Result {$label} successfully. Your decision has been recorded.");
    })->name('result.decide');

    // ── Dashboard overview (alias of dashboard) ────────────────────────────────
    Route::get('/dashboard-overview', function () use ($getRep) {
        $rep = $getRep();
        return Inertia::render('Party/DashboardOverview', [
            'auth'  => ['user' => Auth::user()],
            'party' => $rep ? [
                'name'         => $rep->politicalParty->name,
                'abbreviation' => $rep->politicalParty->abbreviation,
                'color'        => $rep->politicalParty->color,
            ] : null,
        ]);
    })->name('dashboard-overview');
});