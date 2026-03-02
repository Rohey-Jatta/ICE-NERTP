<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsSummaryController extends Controller
{
    public function index()
    {
        $data = Cache::remember('results_summary', 180, function () {
            $election = Election::where('allow_provisional_public_display', true)
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$election) {
                return ['election' => null, 'stats' => null, 'candidates' => []];
            }

            $stats = DB::selectOne("
                SELECT 
                    COUNT(DISTINCT ps.id) as total_stations,
                    COALESCE(SUM(ps.registered_voters), 0) as total_registered,
                    COUNT(DISTINCT r.id) as stations_reported,
                    COALESCE(SUM(r.total_votes_cast), 0) as total_cast,
                    COALESCE(SUM(r.valid_votes), 0) as valid_votes,
                    COALESCE(SUM(r.rejected_votes), 0) as rejected_votes
                FROM polling_stations ps
                LEFT JOIN results r ON ps.id = r.polling_station_id AND r.election_id = ?
                WHERE ps.election_id = ?
            ", [$election->id, $election->id]);

            $candidates = DB::select("
                SELECT 
                    c.id, c.name,
                    COALESCE(pp.name, 'Independent') as party_name,
                    COALESCE(pp.abbreviation, 'IND') as party_abbr,
                    COALESCE(SUM(rcv.votes), 0) as total_votes
                FROM candidates c
                LEFT JOIN political_parties pp ON c.political_party_id = pp.id
                LEFT JOIN result_candidate_votes rcv ON c.id = rcv.candidate_id
                LEFT JOIN results r ON rcv.result_id = r.id
                WHERE c.election_id = ?
                GROUP BY c.id, c.name, pp.name, pp.abbreviation
                ORDER BY total_votes DESC
            ", [$election->id]);

            return [
                'election' => [
                    'id' => $election->id,
                    'name' => $election->name,
                    'type' => $election->type,
                ],
                'stats' => $stats,
                'candidates' => $candidates,
            ];
        });

        return Inertia::render('Public/Results', $data);
    }
}
