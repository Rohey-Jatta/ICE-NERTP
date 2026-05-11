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
            ->get(['id', 'name', 'type', 'status', 'start_date'])
            ->map(fn($e) => [
                'id'         => $e->id,
                'name'       => $e->name,
                'type'       => $e->type,
                'status'     => $e->status,
                'start_date' => $e->start_date?->toDateString(),
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
            $stationsRaw = DB::table('polling_stations as ps')
                ->selectRaw("
                    ps.id, ps.code, ps.name, ps.registered_voters,
                    ps.latitude, ps.longitude,
                    aa.name AS admin_area_name,
                    cst.name AS constituency_name,
                    w.name AS ward_name,
                    COALESCE(r.certification_status, 'not_reported') AS status,
                    r.id AS result_id,
                    r.total_votes_cast, r.valid_votes, r.rejected_votes
                ")
                ->leftJoin('administrative_hierarchy as w', 'w.id', '=', 'ps.ward_id')
                ->leftJoin('administrative_hierarchy as cst', 'cst.id', '=', 'w.parent_id')
                ->leftJoin('administrative_hierarchy as aa', 'aa.id', '=', 'cst.parent_id')
                ->leftJoin('results as r', function ($join) use ($election) {
                    $join->on('r.polling_station_id', '=', 'ps.id')
                         ->where('r.election_id', $election->id);
                })
                ->where('ps.election_id', $election->id)
                ->whereNotNull('ps.latitude')
                ->whereNotNull('ps.longitude')
                ->get();

            $resultIds = $stationsRaw->pluck('result_id')->filter()->unique()->values()->all();

            $votesByResult = collect();
            if (!empty($resultIds)) {
                $votesByResult = DB::table('result_candidate_votes as rcv')
                    ->selectRaw("
                        rcv.result_id,
                        c.name,
                        COALESCE(pp.abbreviation, 'IND') AS party,
                        COALESCE(pp.color, '#6b7280')    AS color,
                        rcv.votes
                    ")
                    ->join('candidates as c', 'c.id', '=', 'rcv.candidate_id')
                    ->leftJoin('political_parties as pp', 'pp.id', '=', 'c.political_party_id')
                    ->whereIn('rcv.result_id', $resultIds)
                    ->orderByDesc('rcv.votes')
                    ->get()
                    ->groupBy('result_id');
            }

            return $stationsRaw->map(function ($station) use ($votesByResult) {
                $votes = $votesByResult->get($station->result_id, collect());
                $station->candidate_votes = $votes->map(fn($v) => [
                    'name'  => $v->name,
                    'party' => $v->party,
                    'color' => $v->color,
                    'votes' => $v->votes,
                ])->values()->all();
                return $station;
            })->toArray();
        });

        return Inertia::render('Public/ResultsMap', [
            'stations'           => $stations,
            'election'           => [
                'id'         => $election->id,
                'name'       => $election->name,
                'type'       => $election->type,
                'status'     => $election->status,
                'start_date' => $election->start_date?->toDateString(),
            ],
            'elections'          => $availableElections,
            'selectedElectionId' => $election->id,
        ]);
    }
}
