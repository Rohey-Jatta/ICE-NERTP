<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsMapController extends Controller
{
    public function index(Request $request)
    {
        // ── Resolve election (honours ?election=ID param) ─────────────────────
        $selectedId = (int) $request->get('election', 0);

        $availableElections = Election::where('allow_provisional_public_display', true)
            ->whereIn('status', ['active', 'certifying', 'results_pending', 'certified'])
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'type', 'status'])
            ->map(fn($e) => [
                'id'     => $e->id,
                'name'   => $e->name,
                'type'   => $e->type,
                'status' => $e->status,
            ]);

        $election = null;
        if ($selectedId && $availableElections->contains('id', $selectedId)) {
            $election = Election::find($selectedId);
        }
        if (!$election) {
            $election = Election::where('allow_provisional_public_display', true)
                ->where('status', 'active')->latest()->first()
                ?? Election::whereIn('status', ['certifying', 'results_pending', 'certified'])
                    ->where('allow_provisional_public_display', true)->latest()->first();
        }

        if (!$election) {
            return Inertia::render('Public/ResultsMap', [
                'stations'           => [],
                'election'           => null,
                'elections'          => $availableElections,
                'selectedElectionId' => null,
            ]);
        }

        $cacheKey = "results_map_{$election->id}";
        $stations = Cache::remember($cacheKey, 300, function () use ($election) {
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

            return collect($stationsRaw)->map(function ($station) {
                $station->candidate_votes = json_decode($station->candidate_votes ?? '[]', true) ?? [];
                return $station;
            })->toArray();
        });

        return Inertia::render('Public/ResultsMap', [
            'stations'           => $stations,
            'election'           => [
                'id'   => $election->id,
                'name' => $election->name,
                'type' => $election->type,
            ],
            'elections'          => $availableElections,
            'selectedElectionId' => $election->id,
        ]);
    }
}