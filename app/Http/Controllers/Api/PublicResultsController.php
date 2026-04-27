<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Election;
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
            $data = Cache::remember('public_results_data', 300, function () {
                return $this->getResultsData();
            });

            return Inertia::render('Public/Results', $data);
        } catch (\Exception $e) {
            Log::error('Public results error: ' . $e->getMessage());

            return Inertia::render('Public/Results', [
                'election'        => null,
                'aggregation'     => null,
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
                'election'        => null,
                'aggregation'     => null,
                'pollingStations' => [],
            ];
        }

        $certificationStatuses = [
            'submitted', 'ward_certified', 'constituency_certified',
            'admin_area_certified', 'nationally_certified',
        ];

        // Single aggregate query
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

        $totalStations         = (int) ($stationStats->total_stations ?? 0);
        $totalRegisteredVoters = (int) ($stationStats->total_registered ?? 0);
        $resultsCount          = (int) ($aggregateData->stations_reported ?? 0);
        $totalVotesCast        = (int) ($aggregateData->total_votes_cast ?? 0);
        $validVotes            = (int) ($aggregateData->valid_votes ?? 0);
        $rejectedVotes         = (int) ($aggregateData->rejected_votes ?? 0);

        $turnoutPercentage = $totalRegisteredVoters > 0
            ? ($totalVotesCast / $totalRegisteredVoters) * 100
            : 0;

        // Candidate totals — 2 queries total (aggregation + batch candidate load)
        $resultIds = Result::where('election_id', $election->id)
            ->whereIn('certification_status', $certificationStatuses)
            ->pluck('id');

        $candidateVotes = ResultCandidateVote::whereIn('result_id', $resultIds)
            ->selectRaw('candidate_id, SUM(votes) as total_votes')
            ->groupBy('candidate_id')
            ->orderByDesc('total_votes')
            ->get();

        $candidateIds  = $candidateVotes->pluck('candidate_id')->filter()->values();
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
                'id'                 => $candidate->id,
                'name'               => $candidate->name,
                'party_name'         => $candidate->politicalParty->name ?? 'Independent',
                'party_abbreviation' => $candidate->politicalParty->abbreviation ?? 'IND',
                'total_votes'        => (int) $vote->total_votes,
            ];
        })->filter()->values();

        // Polling stations — use a single raw query instead of Eloquent with eager loading
        // This avoids loading all relationships into memory for potentially thousands of stations
        $stationsRaw = DB::select("
            SELECT
                ps.id,
                ps.code,
                ps.name,
                ps.registered_voters,
                ps.latitude,
                ps.longitude,
                COALESCE(r.certification_status, 'not_reported') AS result_status,
                r.total_votes_cast,
                r.valid_votes,
                r.rejected_votes,
                r.id AS result_id
            FROM polling_stations ps
            LEFT JOIN results r
                ON r.polling_station_id = ps.id
                AND r.election_id = ?
                AND r.certification_status = ANY(?)
            WHERE ps.election_id = ?
        ", [
            $election->id,
            '{' . implode(',', $certificationStatuses) . '}',
            $election->id,
        ]);

        // Batch-load party acceptances for results that exist
        $resultIdsWithResults = collect($stationsRaw)
            ->filter(fn($s) => $s->result_id !== null)
            ->pluck('result_id')
            ->unique()
            ->values()
            ->toArray();

        $acceptancesByResult = [];
        if (!empty($resultIdsWithResults)) {
            $acceptanceRows = DB::select("
                SELECT pa.result_id, pp.abbreviation AS party_abbreviation, pa.status, pa.comments, pa.id
                FROM party_acceptances pa
                JOIN political_parties pp ON pp.id = pa.political_party_id
                WHERE pa.result_id IN (" . implode(',', array_fill(0, count($resultIdsWithResults), '?')) . ")
            ", $resultIdsWithResults);

            foreach ($acceptanceRows as $row) {
                $acceptancesByResult[$row->result_id][] = [
                    'id'                 => $row->id,
                    'party_abbreviation' => $row->party_abbreviation,
                    'status'             => $row->status,
                    'comments'           => $row->comments,
                ];
            }
        }

        $pollingStations = collect($stationsRaw)->map(function ($station) use ($acceptancesByResult) {
            $resultId    = $station->result_id;
            $stationData = [
                'id'                => $station->id,
                'code'              => $station->code,
                'name'              => $station->name,
                'registered_voters' => $station->registered_voters,
                'latitude'          => $station->latitude,
                'longitude'         => $station->longitude,
                'result_status'     => $station->result_status,
                'result'            => null,
                'party_acceptances' => [],
            ];

            if ($resultId) {
                $stationData['result'] = [
                    'total_votes_cast'   => $station->total_votes_cast,
                    'valid_votes'        => $station->valid_votes,
                    'rejected_votes'     => $station->rejected_votes,
                    'turnout_percentage' => $station->registered_voters > 0
                        ? round(($station->total_votes_cast / $station->registered_voters) * 100, 2)
                        : 0,
                ];

                $stationData['party_acceptances'] = $acceptancesByResult[$resultId] ?? [];
            }

            return $stationData;
        })->toArray();

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