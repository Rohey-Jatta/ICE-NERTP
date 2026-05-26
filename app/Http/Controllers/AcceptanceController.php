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
 *
 * In the parallel workflow, party reps review results simultaneously with
 * ward approvers. Their responses are informational and do NOT block
 * the ward certification pipeline.
 */
class AcceptanceController extends Controller
{
    /**
     * Statuses where party reps can submit their acceptance.
     * Covers the full parallel window through national certification.
     */
    const ACCEPTABLE_STATUSES = [
        Result::STATUS_PENDING_WARD,
        Result::STATUS_WARD_CERTIFIED,
        Result::STATUS_PENDING_CONSTITUENCY,
        Result::STATUS_CONSTITUENCY_CERTIFIED,
        Result::STATUS_PENDING_ADMIN_AREA,
        Result::STATUS_ADMIN_AREA_CERTIFIED,
        Result::STATUS_PENDING_NATIONAL,
        Result::STATUS_NATIONALLY_CERTIFIED,
        // Legacy: keep for any results still in old state
        Result::STATUS_PENDING_PARTY_ACCEPTANCE,
    ];

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

        // Parallel workflow: party reps can respond during any active stage
        if (!in_array($result->certification_status, self::ACCEPTABLE_STATUSES)) {
            return response()->json([
                'message' => 'This result is not available for party review at this stage.',
            ], 403);
        }

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

        try {
            $acceptance = DB::transaction(function () use ($result, $partyRep, $validated) {
                $existing = PartyAcceptance::where('result_id', $result->id)
                    ->where('political_party_id', $partyRep->political_party_id)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->is_final) {
                    throw new \RuntimeException('FINAL_DECISION_EXISTS');
                }

                return PartyAcceptance::updateOrCreate(
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
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'FINAL_DECISION_EXISTS') {
                return response()->json([
                    'message' => 'Your party has already submitted a final decision for this result.',
                ], 422);
            }
            throw $e;
        }

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

        return response()->json([
            'message'    => 'Party response recorded successfully.',
            'acceptance' => $acceptance,
        ], 201);
    }

    /**
     * Get pending acceptances for party rep.
     * Shows results in parallel workflow stages where the party hasn't responded yet.
     */
    public function pending(Request $request): JsonResponse
    {
        $partyRep = $request->user()->partyRepresentatives()->first();

        if (!$partyRep) {
            return response()->json(['results' => []]);
        }

        $results = Result::with(['pollingStation', 'candidateVotes.candidate', 'partyAcceptances'])
            ->whereIn('polling_station_id', $partyRep->pollingStations()->pluck('polling_station_id'))
            ->whereIn('certification_status', self::ACCEPTABLE_STATUSES)
            ->get();

        return response()->json(['results' => $results]);
    }
}
