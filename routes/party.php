<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Result;
use App\Models\PartyRepresentative;
use App\Models\PollingStation;
use App\Models\PartyResultAcceptance;

Route::middleware(['auth', 'role:party-representative'])
    ->prefix('party')
    ->name('party.')
    ->group(function () {

    // ── Dashboard ────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user = Auth::user();
        $rep  = PartyRepresentative::where('user_id', $user->id)
            ->with(['politicalParty', 'pollingStations'])
            ->first();

        if (!$rep) {
            return Inertia::render('Party/Dashboard', [
                'auth'       => ['user' => $user],
                'rep'        => null,
                'statistics' => [],
                'stations'   => [],
            ]);
        }

        $stationIds = $rep->pollingStations->pluck('id');

        // Results at this rep's stations that need a decision
        $pendingResults = Result::whereIn('polling_station_id', $stationIds)
            ->where('certification_status', Result::STATUS_PENDING_PARTY_ACCEPTANCE)
            ->with('pollingStation')
            ->get();

        // Results this rep has already acted on
        $myAcceptances = PartyResultAcceptance::where('party_representative_id', $rep->id)
            ->with('result.pollingStation')
            ->latest()
            ->get();

        $accepted             = $myAcceptances->where('decision', 'accepted')->count();
        $acceptedReservation  = $myAcceptances->where('decision', 'accepted_with_reservation')->count();
        $disputed             = $myAcceptances->where('decision', 'disputed')->count();

        return Inertia::render('Party/Dashboard', [
            'auth' => ['user' => $user],
            'rep'  => [
                'id'           => $rep->id,
                'party_name'   => $rep->politicalParty->name ?? 'Unknown',
                'party_abbr'   => $rep->politicalParty->abbreviation ?? '—',
                'designation'  => $rep->designation,
                'stations'     => $rep->pollingStations->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                    'code' => $s->code,
                ]),
            ],
            'statistics' => [
                'assignedStations'         => $stationIds->count(),
                'awaitingDecision'         => $pendingResults->count(),
                'accepted'                 => $accepted,
                'acceptedWithReservation'  => $acceptedReservation,
                'disputed'                 => $disputed,
            ],
            'pendingResults' => $pendingResults->map(fn($r) => [
                'id'                   => $r->id,
                'polling_station_name' => $r->pollingStation->name ?? '—',
                'polling_station_code' => $r->pollingStation->code ?? '—',
                'total_votes_cast'     => $r->total_votes_cast,
                'valid_votes'          => $r->valid_votes,
                'rejected_votes'       => $r->rejected_votes,
                'submitted_at'         => $r->submitted_at?->format('Y-m-d H:i'),
            ]),
            'myAcceptances' => $myAcceptances->map(fn($a) => [
                'result_id'            => $a->result_id,
                'polling_station_name' => $a->result->pollingStation->name ?? '—',
                'decision'             => $a->decision,
                'notes'                => $a->notes,
                'decided_at'           => $a->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    })->name('dashboard');

    // ── Review a result ──────────────────────────────────────────────────────
    Route::get('/results/{id}/review', function ($id) {
        $user   = Auth::user();
        $rep    = PartyRepresentative::where('user_id', $user->id)->firstOrFail();
        $result = Result::with([
            'pollingStation',
            'candidateVotes.candidate.politicalParty',
            'submittedBy',
        ])->findOrFail($id);

        return Inertia::render('Party/ReviewResult', [
            'auth'   => ['user' => $user],
            'result' => [
                'id'                      => $result->id,
                'polling_station_name'    => $result->pollingStation->name ?? '—',
                'polling_station_code'    => $result->pollingStation->code ?? '—',
                'total_registered_voters' => $result->total_registered_voters,
                'total_votes_cast'        => $result->total_votes_cast,
                'valid_votes'             => $result->valid_votes,
                'rejected_votes'          => $result->rejected_votes,
                'submitted_at'            => $result->submitted_at?->format('Y-m-d H:i'),
                'photo_url'               => $result->result_sheet_photo_path
                    ? asset('storage/' . $result->result_sheet_photo_path)
                    : null,
                'certification_status'    => $result->certification_status,
                'candidate_votes'         => $result->candidateVotes->map(fn($cv) => [
                    'candidate_name' => $cv->candidate->name ?? $cv->candidate->full_name ?? 'Unknown',
                    'party_name'     => $cv->candidate->politicalParty->name ?? 'Independent',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
            ],
        ]);
    })->name('results.review');

    // ── Accept / Accept w/ Reservation / Dispute ─────────────────────────────
    Route::post('/results/{id}/decide', function (Request $request, $id) {
        $user = Auth::user();
        $rep  = PartyRepresentative::where('user_id', $user->id)->firstOrFail();

        $request->validate([
            'decision' => 'required|in:accepted,accepted_with_reservation,disputed',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $result = Result::findOrFail($id);

        // Make sure this rep is assigned to this station
        $stationIds = $rep->pollingStations->pluck('id');
        if (!$stationIds->contains($result->polling_station_id)) {
            return back()->withErrors(['error' => 'You are not assigned to this polling station.']);
        }

        DB::beginTransaction();
        try {
            // Record or update this rep's decision
            PartyResultAcceptance::updateOrCreate(
                [
                    'result_id'               => $result->id,
                    'party_representative_id' => $rep->id,
                ],
                [
                    'political_party_id' => $rep->political_party_id,
                    'decision'           => $request->decision,
                    'notes'              => $request->notes,
                    'decided_at'         => now(),
                ]
            );

            // Log the action
            AuditLog::record(
                action: "party_acceptance.{$request->decision}",
                event:  'updated',
                module: 'Results',
                auditable: $result,
                extra: [
                    'outcome'     => 'success',
                    'decision'    => $request->decision,
                    'rep_id'      => $rep->id,
                    'party'       => $rep->politicalParty->abbreviation ?? '?',
                    'station_id'  => $result->polling_station_id,
                ]
            );

            // ── ADVANCE THE PIPELINE ───────────────────────────────────────
            // A result advances to ward level when:
            //   (a) ALL party reps assigned to this station have decided, OR
            //   (b) ANY rep accepted_with_reservation (move immediately), OR
            //   (c) The decision is 'disputed' → roll back to officer
            //
            $advanceToWard   = false;
            $rollBackToOfficer = false;

            if ($request->decision === 'disputed') {
                // Rejected — send back to officer for correction
                $rollBackToOfficer = true;
            } elseif ($request->decision === 'accepted_with_reservation') {
                // Move immediately — reservation noted but pipeline continues
                $advanceToWard = true;
            } else {
                // 'accepted' — check if ALL reps assigned to this station have now decided
                // Get all party reps assigned to this polling station
                $totalRepsAtStation = DB::table('party_representative_polling_station')
                    ->where('polling_station_id', $result->polling_station_id)
                    ->count();

                $decisionsRecorded = PartyResultAcceptance::where('result_id', $result->id)
                    ->whereIn('decision', ['accepted', 'accepted_with_reservation'])
                    ->count();

                // If all reps have accepted → advance
                if ($decisionsRecorded >= $totalRepsAtStation) {
                    $advanceToWard = true;
                }
                // If fewer than all have accepted yet, just wait — don't change status
            }

            if ($rollBackToOfficer) {
                $result->update([
                    'certification_status'   => Result::STATUS_SUBMITTED,
                    'last_rejection_reason'  => $request->notes ?? 'Disputed by party representative.',
                    'rejection_count'        => $result->rejection_count + 1,
                ]);

                AuditLog::record(
                    action: 'result.rejected_by_party',
                    event:  'updated',
                    module: 'Results',
                    auditable: $result,
                    extra: ['reason' => $request->notes, 'rep_id' => $rep->id]
                );
            } elseif ($advanceToWard) {
                $result->update([
                    'certification_status' => Result::STATUS_PENDING_WARD,
                    'party_reviewed_at'    => now(),
                ]);

                AuditLog::record(
                    action: 'result.advanced_to_ward',
                    event:  'updated',
                    module: 'Results',
                    auditable: $result,
                    extra: ['triggered_by_rep' => $rep->id, 'decision' => $request->decision]
                );
            }

            DB::commit();

            $message = match($request->decision) {
                'accepted'                 => 'Result accepted. It has been forwarded to the Ward Approver.',
                'accepted_with_reservation'=> 'Result accepted with reservation. Forwarded to Ward Approver.',
                'disputed'                 => 'Result disputed. It has been returned to the Polling Officer for correction.',
            };

            return redirect()->route('party.dashboard')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Party decision failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to record decision: ' . $e->getMessage()]);
        }
    })->name('results.decide');

    // ── My assigned stations ──────────────────────────────────────────────────
    Route::get('/stations', function () {
        $user = Auth::user();
        $rep  = PartyRepresentative::where('user_id', $user->id)
            ->with(['pollingStations.results' => function ($q) {
                $q->latest('submitted_at')->limit(1);
            }])
            ->firstOrFail();

        return Inertia::render('Party/Stations', [
            'auth'     => ['user' => $user],
            'stations' => $rep->pollingStations->map(fn($s) => [
                'id'     => $s->id,
                'name'   => $s->name,
                'code'   => $s->code,
                'voters' => $s->registered_voters,
                'latest_result_status' => $s->results->first()?->certification_status ?? 'no_result',
            ]),
        ]);
    })->name('stations');
});