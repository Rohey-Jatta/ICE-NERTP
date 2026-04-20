<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PartyAcceptance;
use App\Models\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AcceptanceController - Party representatives accept/reject results.
 */
class AcceptanceController extends Controller
{
    /**
     * Submit acceptance decision.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result_id' => ['required', 'exists:results,id'],
            'status'    => ['required', 'in:accepted,accepted_with_reservation,rejected'],
            'comments'  => ['required_if:status,accepted_with_reservation,rejected', 'nullable', 'string', 'max:1000'],
        ]);

        $result = Result::findOrFail($validated['result_id']);

        $partyRep = $request->user()->partyRepresentatives()
            ->where('election_id', $result->election_id)
            ->first();

        if (!$partyRep) {
            return response()->json([
                'message' => 'You are not registered as a party representative for this election.',
            ], 403);
        }

        $isAssigned = $partyRep->pollingStations()
            ->where('polling_station_id', $result->polling_station_id)
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'message' => 'You are not assigned to this polling station.',
            ], 403);
        }

        $existing = PartyAcceptance::where('result_id', $result->id)
            ->where('political_party_id', $partyRep->political_party_id)
            ->first();

        if ($existing && $existing->is_final) {
            return response()->json([
                'message' => 'Your party has already submitted a final decision for this result.',
            ], 422);
        }

        $acceptance = PartyAcceptance::updateOrCreate(
            [
                'result_id'          => $result->id,
                'political_party_id' => $partyRep->political_party_id,
            ],
            [
                'party_representative_id' => $partyRep->id,
                'election_id'             => $result->election_id,
                'status'                  => $validated['status'],
                'comments'                => $validated['comments'],
                'decided_at'              => now(),
                'is_final'                => true,
            ]
        );

        AuditLog::record(
            action:    'party_acceptance.submitted',
            event:     'created',
            module:    'PartyAcceptance',
            auditable: $acceptance,
            extra:     [
                'election_id' => $result->election_id,
                'result_id'   => $result->id,
                'status'      => $validated['status'],
                'outcome'     => 'success',
            ]
        );

        $this->checkIfAllPartiesResponded($result);

        return response()->json([
            'message'    => 'Acceptance recorded successfully.',
            'acceptance' => $acceptance,
        ], 201);
    }

    /**
     * Get pending acceptances for party rep.
     */
    public function pending(Request $request): JsonResponse
    {
        $partyRep = $request->user()->partyRepresentatives()->first();

        if (!$partyRep) {
            return response()->json(['results' => []]);
        }

        $results = Result::with(['pollingStation', 'candidateVotes.candidate', 'partyAcceptances'])
            ->whereIn('polling_station_id', $partyRep->pollingStations()->pluck('polling_station_id'))
            ->where('certification_status', Result::STATUS_PENDING_PARTY_ACCEPTANCE)
            ->get();

        return response()->json(['results' => $results]);
    }

    /**
     * Advance result to PENDING_WARD when all station-assigned party reps have responded.
     *
     * Uses the same station-specific check as ProcessResultSubmission and party.php routes
     * to ensure consistency in the certification pipeline.
     */
    private function checkIfAllPartiesResponded(Result $result): void
    {
        // ── Get the parties that actually have reps assigned to THIS station ──
        // This matches the logic in ProcessResultSubmission::handle() and party.php
        $assignedPartyIds = DB::table('party_representative_polling_station')
            ->join('party_representatives', 'party_representatives.id', '=',
                   'party_representative_polling_station.party_representative_id')
            ->where('party_representative_polling_station.polling_station_id', $result->polling_station_id)
            ->where('party_representatives.is_active', true)
            ->pluck('party_representatives.political_party_id')
            ->unique();

        $totalAssignedParties = $assignedPartyIds->count();

        // No party reps for this station — advance immediately
        if ($totalAssignedParties === 0) {
            if ($result->certification_status === Result::STATUS_PENDING_PARTY_ACCEPTANCE) {
                $result->update(['certification_status' => Result::STATUS_PENDING_WARD]);
            }
            return;
        }

        // Check how many of the assigned parties have submitted a final decision
        $respondedParties = PartyAcceptance::where('result_id', $result->id)
            ->where('is_final', true)
            ->whereIn('political_party_id', $assignedPartyIds)
            ->count();

        if ($respondedParties >= $totalAssignedParties) {
            if ($result->certification_status === Result::STATUS_PENDING_PARTY_ACCEPTANCE) {
                $result->update(['certification_status' => Result::STATUS_PENDING_WARD]);
            }
        }
    }
}
