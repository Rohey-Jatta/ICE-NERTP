<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsMapController extends Controller
{
    public function index()
    {
        $data = Cache::remember('results_map', 180, function () {
            $election = Election::where('allow_provisional_public_display', true)
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$election) {
                return ['election' => null, 'stations' => []];
            }

            $stations = DB::select("
                SELECT 
                    ps.id, ps.code, ps.name, ps.registered_voters,
                    ps.latitude, ps.longitude,
                    COALESCE(r.certification_status, 'not_reported') as status,
                    r.total_votes_cast, r.valid_votes, r.rejected_votes
                FROM polling_stations ps
                LEFT JOIN results r ON ps.id = r.polling_station_id AND r.election_id = ?
                WHERE ps.election_id = ? AND ps.latitude IS NOT NULL AND ps.longitude IS NOT NULL
            ", [$election->id, $election->id]);

            return [
                'election' => [
                    'id' => $election->id,
                    'name' => $election->name,
                ],
                'stations' => $stations,
            ];
        });

        return Inertia::render('Public/ResultsMap', $data);
    }
}
