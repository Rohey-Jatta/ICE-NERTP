<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PartyAcceptance;
use App\Models\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AcceptanceController - Party representatives accept/reject results.
 *
 * From architecture: app/Http/Controllers/AcceptanceController.php
 *
 * Party reps can:
 * - View results from their assigned polling stations
 * - Accept, accept with reservation, or reject
 * - Add comments (required for reservation/rejection)
 */
class AcceptanceController extends Controller
{
    /**
     * Submit acceptance decision.
     * Route: POST /api/acceptance
     * Middleware: auth:sanctum, role:party-representative
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result_id' => ['required', 'exists:results,id'],
            'status' => ['required', 'in:accepted,accepted_with_reservation,rejected'],
            'comments' => ['required_if:status,accepted_with_reservation,rejected', 'nullable', 'string', 'max:1000'],
        ]);

        $result = Result::findOrFail($validated['result_id']);

        // Get party rep record
        $partyRep = $request->user()->partyRepresentatives()
            ->where('election_id', $result->election_id)
            ->first();

        if (!$partyRep) {
            return response()->json([
                'message' => 'You are not registered as a party representative for this election.',
            ], 403);
        }

        // Verify party rep is assigned to this station
        $isAssigned = $partyRep->pollingStations()
            ->where('polling_station_id', $result->polling_station_id)
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'message' => 'You are not assigned to this polling station.',
            ], 403);
        }

        // Check if already submitted
        $existing = PartyAcceptance::where('result_id', $result->id)
            ->where('political_party_id', $partyRep->political_party_id)
            ->first();

        if ($existing && $existing->is_final) {
            return response()->json([
                'message' => 'Your party has already submitted a final decision for this result.',
            ], 422);
        }

        // Create or update acceptance
        $acceptance = PartyAcceptance::updateOrCreate(
            [
                'result_id' => $result->id,
                'political_party_id' => $partyRep->political_party_id,
            ],
            [
                'party_representative_id' => $partyRep->id,
                'election_id' => $result->election_id,
                'status' => $validated['status'],
                'comments' => $validated['comments'],
                'decided_at' => now(),
                'is_final' => true,
            ]
        );

        // Audit log
        AuditLog::record(
            action: 'party_acceptance.submitted',
            event: 'created',
            module: 'PartyAcceptance',
            auditable: $acceptance,
            extra: [
                'election_id' => $result->election_id,
                'result_id' => $result->id,
                'status' => $validated['status'],
                'outcome' => 'success',
            ]
        );

        // Check if all parties have responded
        $this->checkIfAllPartiesResponded($result);

        return response()->json([
            'message' => 'Acceptance recorded successfully.',
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

        // Get results from assigned stations that are pending acceptance
        $results = Result::with(['pollingStation', 'candidateVotes.candidate', 'partyAcceptances'])
            ->whereIn('polling_station_id', $partyRep->pollingStations()->pluck('polling_station_id'))
            ->where('certification_status', Result::STATUS_PENDING_PARTY_ACCEPTANCE)
            ->get();

        return response()->json([
            'results' => $results,
        ]);
    }

    private function checkIfAllPartiesResponded(Result $result): void
    {
        $totalParties = $result->election->politicalParties()->count();
        $acceptedParties = $result->partyAcceptances()->where('is_final', true)->count();

        if ($acceptedParties >= $totalParties) {
            // All parties responded - transition to pending ward
            $result->update([
                'certification_status' => Result::STATUS_PENDING_WARD,
            ]);
        }
    }
}
