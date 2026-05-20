<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsSummaryController extends Controller
{
    public function index(Request $request)
    {
        // ── 1. Resolve which elections are publicly displayable ───────────────
        $availableElections = Election::whereIn('status', ['active', 'certifying', 'results_pending', 'certified'])
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'type', 'status', 'start_date'])
            ->map(fn($e) => [
                'id'         => $e->id,
                'name'       => $e->name,
                'type'       => $e->type,
                'status'     => $e->status,
                'start_date' => $e->start_date?->toDateString(),
            ]);

        // ── 2. Determine the selected election ────────────────────────────────
        $selectedId    = (int) $request->get('election', 0);
        $electionModel = null;

        if ($selectedId && $availableElections->contains('id', $selectedId)) {
            $electionModel = Election::find($selectedId);
        }

        if (!$electionModel) {
            // Default: certified first, then latest active
            $electionModel = Election::where('status', 'certified')
                ->latest('start_date')
                ->first()
                ?? Election::whereIn('status', ['active', 'certifying', 'results_pending'])
                    ->latest('start_date')
                    ->first();
        }

        if (!$electionModel) {
            return Inertia::render('Public/Results', [
                'election'           => null,
                'elections'          => $availableElections,
                'selectedElectionId' => null,
                'stats'              => null,
                'candidates'         => [],
            ]);
        }

        // ── 3. Compute/fetch cached summary data ──────────────────────────────
        // Cache key includes a hash so it busts when election status changes
        $cacheKey = "results_summary_v3_{$electionModel->id}_{$electionModel->status}";
        $data     = Cache::remember($cacheKey, 60, fn() => $this->computeSummary($electionModel));

        return Inertia::render('Public/Results', array_merge($data, [
            'elections'          => $availableElections,
            'selectedElectionId' => $electionModel->id,
        ]));
    }

    private function computeSummary(Election $election): array
    {
        // ── PUBLIC RULE: Only nationally_certified results are ever shown ─────
        // This is the single source of truth for what is "published".
        // No provisional data, no partially-approved data, ever.
        $publicStatuses = ['nationally_certified'];

        $electionPayload = [
            'id'         => $election->id,
            'name'       => $election->name,
            'type'       => $election->type,
            'status'     => $election->status,
            'start_date' => $election->start_date?->toDateString(),
        ];

        // ── Aggregate stats: total stations + certified results ───────────────
        $stats = DB::table('polling_stations as ps')
            ->selectRaw('
                COUNT(DISTINCT ps.id)                                      AS total_stations,
                COALESCE(SUM(ps.registered_voters), 0)                    AS total_registered,
                COUNT(DISTINCT r.id)                                       AS stations_reported,
                COALESCE(SUM(r.total_votes_cast), 0)                      AS total_cast,
                COALESCE(SUM(r.valid_votes), 0)                           AS valid_votes,
                COALESCE(SUM(r.rejected_votes), 0)                        AS rejected_votes
            ')
            ->leftJoin('results as r', function ($join) use ($election, $publicStatuses) {
                $join->on('ps.id', '=', 'r.polling_station_id')
                     ->where('r.election_id', $election->id)
                     ->whereIn('r.certification_status', $publicStatuses);
            })
            ->where('ps.election_id', $election->id)
            ->first();

        if (!$stats) {
            return [
                'election'   => $electionPayload,
                'stats'      => null,
                'candidates' => [],
                'message'    => 'Results will appear as the IEC Chairman certifies and publishes them.',
            ];
        }

        // ── Candidate results: only from nationally_certified results ─────────
        $candidates = DB::table('candidates as c')
            ->selectRaw("
                c.id,
                c.name,
                c.photo_path,
                COALESCE(pp.name, 'Independent')  AS party_name,
                COALESCE(pp.abbreviation, 'IND')  AS party_abbr,
                COALESCE(pp.color, '#6b7280')     AS party_color,
                COALESCE(SUM(CASE WHEN r.id IS NOT NULL THEN rcv.votes ELSE 0 END), 0) AS total_votes
            ")
            ->leftJoin('political_parties as pp', 'c.political_party_id', '=', 'pp.id')
            ->leftJoin('result_candidate_votes as rcv', 'c.id', '=', 'rcv.candidate_id')
            ->leftJoin('results as r', function ($join) use ($election, $publicStatuses) {
                $join->on('rcv.result_id', '=', 'r.id')
                     ->where('r.election_id', $election->id)
                     ->whereIn('r.certification_status', $publicStatuses);
            })
            ->where('c.election_id', $election->id)
            ->where('c.is_active', true)
            ->groupBy('c.id', 'c.name', 'c.photo_path', 'pp.name', 'pp.abbreviation', 'pp.color')
            ->orderByDesc('total_votes')
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'photo_url'   => $c->photo_path ? asset('storage/' . $c->photo_path) : null,
                'party_name'  => $c->party_name,
                'party_abbr'  => $c->party_abbr,
                'party_color' => $c->party_color,
                'total_votes' => (int) $c->total_votes,
            ]);

        // If zero certified stations, don't show empty candidate rows
        if ((int) $stats->stations_reported === 0) {
            $candidates = collect();
        }

        return [
            'election'   => $electionPayload,
            'stats'      => $stats,
            'candidates' => $candidates,
            'message'    => (int) $stats->stations_reported === 0
                ? 'No results have been officially published yet. Check back after the IEC Chairman certifies the first results.'
                : null,
        ];
    }
}