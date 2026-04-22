<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\PartyAcceptance;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PublicResultsController extends Controller
{
    public function index()
    {
        try {
            // Increased cache TTL from 30 s to 120 s — heavy queries should not
            // re-run more than once every two minutes on the public home page.
            $data = Cache::remember('public_results_data', 120, function () {
                return $this->getResultsData();
            });

            return Inertia::render('Public/Results', $data);
        } catch (\Exception $e) {
            Log::error('Public results error: ' . $e->getMessage());

            return Inertia::render('Public/Results', [
                'election' => null,
                'aggregation' => null,
                'pollingStations' => [],
            ]);
        }
    }

    private function getResultsData(): array
    {
        $election = Election::where('allow_provisional_public_display', true)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$election) {
            return [
                'election' => null,
                'aggregation' => null,
                'pollingStations' => [],
            ];
        }

        // ── Aggregate totals (single query) ───────────────────────────────────
        $certificationStatuses = [
            'submitted', 'ward_certified', 'constituency_certified',
            'admin_area_certified', 'nationally_certified',
        ];

        $aggregateData = Result::where('election_id', $election->id)
            ->whereIn('certification_status', $certificationStatuses)
            ->selectRaw('
                COUNT(*) as stations_reported,
                COALESCE(SUM(total_votes_cast), 0) as total_votes_cast,
                COALESCE(SUM(valid_votes), 0) as valid_votes,
                COALESCE(SUM(rejected_votes), 0) as rejected_votes
            ')
            ->first();

        $stationStats = PollingStation::where('election_id', $election->id)
            ->selectRaw('COUNT(*) as total_stations, COALESCE(SUM(registered_voters), 0) as total_registered')
            ->first();

        $totalStations          = (int) ($stationStats->total_stations ?? 0);
        $totalRegisteredVoters  = (int) ($stationStats->total_registered ?? 0);
        $resultsCount           = (int) ($aggregateData->stations_reported ?? 0);
        $totalVotesCast         = (int) ($aggregateData->total_votes_cast ?? 0);
        $validVotes             = (int) ($aggregateData->valid_votes ?? 0);
        $rejectedVotes          = (int) ($aggregateData->rejected_votes ?? 0);

        $turnoutPercentage = $totalRegisteredVoters > 0
            ? ($totalVotesCast / $totalRegisteredVoters) * 100
            : 0;

        // ── Candidate totals — FIXED N+1 ──────────────────────────────────────
        // OLD (N+1): iterated over $candidateVotes and called
        //   Candidate::with('politicalParty')->find($vote->candidate_id)
        //   inside the loop, firing 2 queries per candidate.
        //
        // NEW: one query for vote aggregation, one batch query for all candidates.

        $resultIds = Result::where('election_id', $election->id)
            ->whereIn('certification_status', $certificationStatuses)
            ->pluck('id');

        $candidateVotes = ResultCandidateVote::whereIn('result_id', $resultIds)
            ->selectRaw('candidate_id, SUM(votes) as total_votes')
            ->groupBy('candidate_id')
            ->orderByDesc('total_votes')
            ->get();

        // Batch-load all candidates + their parties in 2 queries total
        $candidateIds = $candidateVotes->pluck('candidate_id')->filter()->values();
        $candidatesMap = Candidate::with('politicalParty')
            ->whereIn('id', $candidateIds)
            ->get()
            ->keyBy('id');

        $candidates = $candidateVotes->map(function ($vote) use ($candidatesMap) {
            $candidate = $candidatesMap->get($vote->candidate_id);
            if (!$candidate) {
                return null;
            }

            return [
                'id'                  => $candidate->id,
                'name'                => $candidate->name,
                'party_name'          => $candidate->politicalParty->name ?? 'Independent',
                'party_abbreviation'  => $candidate->politicalParty->abbreviation ?? 'IND',
                'total_votes'         => (int) $vote->total_votes,
            ];
        })->filter()->values();

        // ── Polling station list ───────────────────────────────────────────────
        // FIXED: the original code used ->with(['result' => ...]) but PollingStation
        // has no 'result()' relationship (only 'results()' and 'latestResult()').
        // That silently returned null for every station. Now we use 'latestResult'
        // with a proper eager-load, and scope the results to the active election.
        $pollingStations = PollingStation::where('election_id', $election->id)
            ->with([
                'latestResult' => function ($query) use ($election, $certificationStatuses) {
                    $query->where('election_id', $election->id)
                          ->whereIn('certification_status', $certificationStatuses)
                          ->with(['partyAcceptances.politicalParty']);
                },
            ])
            ->get()
            ->map(function ($station) {
                $result = $station->latestResult;

                $stationData = [
                    'id'                => $station->id,
                    'code'              => $station->code,
                    'name'              => $station->name,
                    'registered_voters' => $station->registered_voters,
                    'latitude'          => $station->latitude,
                    'longitude'         => $station->longitude,
                    'result_status'     => $result?->certification_status ?? 'not_reported',
                    'result'            => null,
                    'party_acceptances' => [],
                ];

                if ($result) {
                    $stationData['result'] = [
                        'total_votes_cast' => $result->total_votes_cast,
                        'valid_votes'      => $result->valid_votes,
                        'rejected_votes'   => $result->rejected_votes,
                        'turnout_percentage' => $station->registered_voters > 0
                            ? round(($result->total_votes_cast / $station->registered_voters) * 100, 2)
                            : 0,
                    ];

                    $stationData['party_acceptances'] = $result->partyAcceptances
                        ->map(fn ($acceptance) => [
                            'id'                => $acceptance->id,
                            'party_abbreviation'=> $acceptance->politicalParty->abbreviation ?? 'N/A',
                            'status'            => $acceptance->status,
                            'comments'          => $acceptance->comments,
                        ])
                        ->values();
                }

                return $stationData;
            });

        return [
            'election' => [
                'id'     => $election->id,
                'name'   => $election->name,
                'type'   => $election->type,
                'status' => $election->status,
            ],
            'aggregation' => [
                'total_stations'          => $totalStations,
                'stations_reported'       => $resultsCount,
                'total_registered_voters' => $totalRegisteredVoters,
                'total_votes_cast'        => $totalVotesCast,
                'valid_votes'             => $validVotes,
                'rejected_votes'          => $rejectedVotes,
                'turnout_percentage'      => round($turnoutPercentage, 2),
                'candidates'              => $candidates,
            ],
            'pollingStations' => $pollingStations,
        ];
    }
}
