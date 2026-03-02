<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Models\Candidate;
use App\Models\PartyAcceptance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PublicResultsController extends Controller
{
    public function index()
    {
        try {
            $data = Cache::remember('public_results_data', 30, function () {
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

    private function getResultsData()
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

        $totalStations = PollingStation::where('election_id', $election->id)->count();
        $totalRegisteredVoters = PollingStation::where('election_id', $election->id)->sum('registered_voters');

        $resultsCount = Result::where('election_id', $election->id)
            ->whereIn('certification_status', [
                'submitted', 'ward_certified', 'constituency_certified',
                'admin_area_certified', 'nationally_certified'
            ])
            ->count();

        $aggregateData = Result::where('election_id', $election->id)
            ->whereIn('certification_status', [
                'submitted', 'ward_certified', 'constituency_certified',
                'admin_area_certified', 'nationally_certified'
            ])
            ->selectRaw('
                SUM(total_votes_cast) as total_votes_cast,
                SUM(valid_votes) as valid_votes,
                SUM(rejected_votes) as rejected_votes
            ')
            ->first();

        $totalVotesCast = $aggregateData->total_votes_cast ?? 0;
        $validVotes = $aggregateData->valid_votes ?? 0;
        $rejectedVotes = $aggregateData->rejected_votes ?? 0;

        $turnoutPercentage = $totalRegisteredVoters > 0
            ? ($totalVotesCast / $totalRegisteredVoters) * 100
            : 0;

        $resultIds = Result::where('election_id', $election->id)
            ->whereIn('certification_status', [
                'submitted', 'ward_certified', 'constituency_certified',
                'admin_area_certified', 'nationally_certified'
            ])
            ->pluck('id');

        $candidateVotes = ResultCandidateVote::whereIn('result_id', $resultIds)
            ->selectRaw('candidate_id, SUM(votes) as total_votes')
            ->groupBy('candidate_id')
            ->orderByDesc('total_votes')
            ->get();

        $candidates = $candidateVotes->map(function ($vote) {
            $candidate = Candidate::with('politicalParty')->find($vote->candidate_id);
            if (!$candidate) return null;

            return [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'party_name' => $candidate->politicalParty->name ?? 'Independent',
                'party_abbreviation' => $candidate->politicalParty->abbreviation ?? 'IND',
                'total_votes' => $vote->total_votes,
            ];
        })->filter();

        $pollingStations = PollingStation::where('election_id', $election->id)
            ->with(['result' => function ($query) {
                $query->latest()
                    ->with(['partyAcceptances' => function ($q) {
                        $q->with('politicalParty');
                    }]);
            }])
            ->get()
            ->map(function ($station) {
                $stationData = [
                    'id' => $station->id,
                    'code' => $station->code,
                    'name' => $station->name,
                    'registered_voters' => $station->registered_voters,
                    'latitude' => $station->latitude,
                    'longitude' => $station->longitude,
                    'result_status' => $station->result?->certification_status ?? 'not_reported',
                    'result' => null,
                    'party_acceptances' => [],
                ];

                if ($station->result) {
                    $stationData['result'] = [
                        'total_votes_cast' => $station->result->total_votes_cast,
                        'valid_votes' => $station->result->valid_votes,
                        'rejected_votes' => $station->result->rejected_votes,
                        'turnout_percentage' => $station->registered_voters > 0
                            ? ($station->result->total_votes_cast / $station->registered_voters) * 100
                            : 0,
                    ];

                    $acceptances = $station->result->partyAcceptances->map(function ($acceptance) {
                        return [
                            'id' => $acceptance->id,
                            'party_abbreviation' => $acceptance->politicalParty->abbreviation ?? 'N/A',
                            'status' => $acceptance->status,
                            'comments' => $acceptance->comments,
                        ];
                    });

                    $stationData['party_acceptances'] = $acceptances;
                }

                return $stationData;
            });

        return [
            'election' => [
                'id' => $election->id,
                'name' => $election->name,
                'type' => $election->type,
                'status' => $election->status,
            ],
            'aggregation' => [
                'total_stations' => $totalStations,
                'stations_reported' => $resultsCount,
                'total_registered_voters' => $totalRegisteredVoters,
                'total_votes_cast' => $totalVotesCast,
                'valid_votes' => $validVotes,
                'rejected_votes' => $rejectedVotes,
                'turnout_percentage' => round($turnoutPercentage, 2),
                'candidates' => $candidates->values(),
            ],
            'pollingStations' => $pollingStations,
        ];
    }
}
