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
        $data = Cache::remember('results_summary', 300, function () {
            $election = Election::whereIn('status', ['certified'])
                ->where('allow_provisional_public_display', true)
                ->latest()
                ->first();

            if (!$election) {
                $election = Election::where('status', 'active')
                    ->where('allow_provisional_public_display', true)
                    ->latest()
                    ->first();
            }

            if (!$election) {
                return ['election' => null, 'stats' => null, 'candidates' => []];
            }

            $publicStatuses = [
                'ward_certified',
                'pending_constituency',
                'constituency_certified',
                'pending_admin_area',
                'admin_area_certified',
                'pending_national',
                'nationally_certified',
            ];

            if ($election->status === 'certified') {
                $publicStatuses = ['nationally_certified'];
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
                LEFT JOIN results r ON ps.id = r.polling_station_id
                    AND r.election_id = ?
                    AND r.certification_status = ANY(?)
                WHERE ps.election_id = ?
            ", [$election->id, '{' . implode(',', $publicStatuses) . '}', $election->id]);

            if (!$stats || $stats->stations_reported == 0) {
                return [
                    'election' => [
                        'id'   => $election->id,
                        'name' => $election->name,
                        'type' => $election->type,
                    ],
                    'stats'      => null,
                    'candidates' => [],
                    'message'    => 'Results will be published after certification is complete.',
                ];
            }

            $candidates = DB::select("
                SELECT
                    c.id, c.name,
                    COALESCE(pp.name, 'Independent') as party_name,
                    COALESCE(pp.abbreviation, 'IND') as party_abbr,
                    COALESCE(pp.color, '#6b7280') as party_color,
                    COALESCE(SUM(rcv.votes), 0) as total_votes
                FROM candidates c
                LEFT JOIN political_parties pp ON c.political_party_id = pp.id
                LEFT JOIN result_candidate_votes rcv ON c.id = rcv.candidate_id
                LEFT JOIN results r ON rcv.result_id = r.id
                    AND r.certification_status = ANY(?)
                WHERE c.election_id = ?
                GROUP BY c.id, c.name, pp.name, pp.abbreviation, pp.color
                ORDER BY total_votes DESC
            ", ['{' . implode(',', $publicStatuses) . '}', $election->id]);

            return [
                'election' => [
                    'id'   => $election->id,
                    'name' => $election->name,
                    'type' => $election->type,
                ],
                'stats'      => $stats,
                'candidates' => $candidates,
                'message'    => null,
            ];
        });

        return Inertia::render('Public/Results', $data);
    }
}