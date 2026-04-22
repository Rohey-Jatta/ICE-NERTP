<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsStationsController extends Controller
{
    public function index()
    {
        $data = Cache::remember('results_stations', 60, function () {
            // Include certified elections — original code only checked 'active'
            $election = Election::where('allow_provisional_public_display', true)
                ->whereIn('status', ['active', 'certifying', 'results_pending', 'certified'])
                ->latest()
                ->first();

            if (!$election) {
                return ['election' => null, 'stations' => [], 'isPublished' => false];
            }

            // Results are "published" only when the election is officially certified
            $isPublished = $election->status === 'certified';

            // Fetch all stations with their results
            $stations = DB::select("
                SELECT
                    ps.id, ps.code, ps.name, ps.registered_voters,
                    COALESCE(r.certification_status, 'not_reported') AS status,
                    r.id   AS result_id,
                    r.total_votes_cast,
                    r.valid_votes,
                    r.rejected_votes,
                    r.result_sheet_photo_path
                FROM polling_stations ps
                LEFT JOIN results r
                    ON  r.polling_station_id = ps.id
                    AND r.election_id        = ?
                WHERE ps.election_id = ?
                ORDER BY ps.code
            ", [$election->id, $election->id]);

            // Collect result IDs for batch sub-queries
            $resultIds = collect($stations)
                ->filter(fn($s) => $s->result_id !== null)
                ->pluck('result_id')
                ->unique()
                ->values()
                ->toArray();

            $candidateVotesByResult   = [];
            $partyAcceptancesByResult = [];

            if (!empty($resultIds)) {
                $placeholders = implode(',', array_fill(0, count($resultIds), '?'));

                // Candidate votes — always shown (published or not)
                $cvRows = DB::select("
                    SELECT
                        rcv.result_id,
                        c.name                                  AS candidate_name,
                        COALESCE(pp.name, 'Independent')        AS party_name,
                        COALESCE(pp.abbreviation, 'IND')        AS party_abbr,
                        COALESCE(pp.color, '#6b7280')           AS party_color,
                        rcv.votes
                    FROM result_candidate_votes rcv
                    JOIN candidates c ON c.id = rcv.candidate_id
                    LEFT JOIN political_parties pp ON pp.id = c.political_party_id
                    WHERE rcv.result_id IN ({$placeholders})
                    ORDER BY rcv.result_id, rcv.votes DESC
                ", $resultIds);

                foreach ($cvRows as $row) {
                    $candidateVotesByResult[$row->result_id][] = [
                        'candidate_name' => $row->candidate_name,
                        'party_name'     => $row->party_name,
                        'party_abbr'     => $row->party_abbr,
                        'party_color'    => $row->party_color,
                        'votes'          => $row->votes,
                    ];
                }

                // Party acceptances + photos — only for published elections
                if ($isPublished) {
                    $paRows = DB::select("
                        SELECT
                            pa.result_id,
                            pp.name         AS party_name,
                            pp.abbreviation AS party_abbr,
                            pa.status,
                            pa.comments
                        FROM party_acceptances pa
                        JOIN political_parties pp ON pp.id = pa.political_party_id
                        WHERE pa.result_id IN ({$placeholders})
                        ORDER BY pa.result_id
                    ", $resultIds);

                    foreach ($paRows as $row) {
                        $partyAcceptancesByResult[$row->result_id][] = [
                            'party_name' => $row->party_name,
                            'party_abbr' => $row->party_abbr,
                            'status'     => $row->status,
                            'comments'   => $row->comments,
                        ];
                    }
                }
            }

            $mappedStations = collect($stations)->map(function ($station) use (
                $isPublished,
                $candidateVotesByResult,
                $partyAcceptancesByResult
            ) {
                $resultId = $station->result_id;

                return [
                    'id'                => $station->id,
                    'code'              => $station->code,
                    'name'              => $station->name,
                    'registered_voters' => $station->registered_voters,
                    'status'            => $station->status,
                    'total_votes_cast'  => $station->total_votes_cast,
                    'valid_votes'       => $station->valid_votes,
                    'rejected_votes'    => $station->rejected_votes,
                    'candidate_votes'   => $resultId ? ($candidateVotesByResult[$resultId]  ?? []) : [],
                    'party_acceptances' => ($isPublished && $resultId)
                                            ? ($partyAcceptancesByResult[$resultId] ?? [])
                                            : [],
                    'photo_url'         => ($isPublished && $station->result_sheet_photo_path)
                                            ? asset('storage/' . $station->result_sheet_photo_path)
                                            : null,
                ];
            })->toArray();

            return [
                'election'    => ['id' => $election->id, 'name' => $election->name],
                'stations'    => $mappedStations,
                'isPublished' => $isPublished,
            ];
        });

        return Inertia::render('Public/ResultsStations', $data);
    }
}
