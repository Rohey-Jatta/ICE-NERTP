<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsMapController extends Controller
{
    public function index(Request $request)
    {
        // Resolve which election to show (active → most recent)
        $election = Election::where('status', 'active')->latest()->first()
            ?? Election::whereIn('status', ['certifying', 'results_pending', 'certified'])
                       ->latest()
                       ->first();

        if (! $election) {
            return Inertia::render('Public/ResultsMap', [
                'stations' => [],
                'election' => null,
            ]);
        }

        // Fetch all stations with results + candidate votes via correlated subquery.
        // The subquery uses json_agg (PostgreSQL) to aggregate candidate data per station.
        $stationsRaw = DB::select("
            SELECT
                ps.id,
                ps.code,
                ps.name,
                ps.registered_voters,
                ps.latitude,
                ps.longitude,
                COALESCE(r.certification_status, 'not_reported')  AS status,
                r.total_votes_cast,
                r.valid_votes,
                r.rejected_votes,
                (
                    SELECT json_agg(
                        json_build_object(
                            'name',  c.name,
                            'party', COALESCE(pp.abbreviation, 'IND'),
                            'color', COALESCE(pp.color, '#6b7280'),
                            'votes', rcv.votes
                        )
                        ORDER BY rcv.votes DESC
                    )
                    FROM result_candidate_votes rcv
                    JOIN candidates c ON c.id = rcv.candidate_id
                    LEFT JOIN political_parties pp ON pp.id = c.political_party_id
                    WHERE rcv.result_id = r.id
                ) AS candidate_votes
            FROM polling_stations ps
            LEFT JOIN results r
                ON  r.polling_station_id = ps.id
                AND r.election_id        = ?
            WHERE ps.election_id    = ?
              AND ps.latitude       IS NOT NULL
              AND ps.longitude      IS NOT NULL
        ", [$election->id, $election->id]);

        // Decode the JSON candidate_votes column (PostgreSQL returns it as a string)
        $stations = collect($stationsRaw)->map(function ($station) {
            $station->candidate_votes = json_decode($station->candidate_votes ?? '[]', true) ?? [];
            return $station;
        })->toArray();

        return Inertia::render('Public/ResultsMap', [
            'stations' => $stations,
            'election' => [
                'id'   => $election->id,
                'name' => $election->name,
                'date' => $election->date,
                'type' => $election->type,
            ],
        ]);
    }
}
