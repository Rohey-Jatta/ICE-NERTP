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

        // ── 2. Determine the selected election ────────────────────────────────
        $selectedId    = (int) $request->get('election', 0);
        $electionModel = null;

        if ($selectedId && $availableElections->contains('id', $selectedId)) {
            $electionModel = Election::find($selectedId);
        }

        if (!$electionModel) {
            // Default: certified first, then active
            $electionModel = Election::where('allow_provisional_public_display', true)
                ->where('status', 'certified')
                ->latest('start_date')
                ->first()
                ?? Election::where('allow_provisional_public_display', true)
                    ->where('status', 'active')
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
        $cacheKey = "results_summary_{$electionModel->id}";
        $data     = Cache::remember($cacheKey, 300, fn() => $this->computeSummary($electionModel));

        return Inertia::render('Public/Results', array_merge($data, [
            'elections'          => $availableElections,
            'selectedElectionId' => $electionModel->id,
        ]));
    }

    private function computeSummary(Election $election): array
    {
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

        $stats = DB::table('polling_stations as ps')
            ->selectRaw('
                COUNT(DISTINCT ps.id) as total_stations,
                COALESCE(SUM(ps.registered_voters), 0) as total_registered,
                COUNT(DISTINCT r.id) as stations_reported,
                COALESCE(SUM(r.total_votes_cast), 0) as total_cast,
                COALESCE(SUM(r.valid_votes), 0) as valid_votes,
                COALESCE(SUM(r.rejected_votes), 0) as rejected_votes
            ')
            ->leftJoin('results as r', function ($join) use ($election, $publicStatuses) {
                $join->on('ps.id', '=', 'r.polling_station_id')
                     ->where('r.election_id', $election->id)
                     ->whereIn('r.certification_status', $publicStatuses);
            })
            ->where('ps.election_id', $election->id)
            ->first();

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

        $candidates = DB::table('candidates as c')
            ->selectRaw("
                c.id, c.name,
                COALESCE(pp.name, 'Independent')  as party_name,
                COALESCE(pp.abbreviation, 'IND')  as party_abbr,
                COALESCE(pp.color, '#6b7280')      as party_color,
                COALESCE(SUM(rcv.votes), 0)        as total_votes
            ")
            ->leftJoin('political_parties as pp', 'c.political_party_id', '=', 'pp.id')
            ->leftJoin('result_candidate_votes as rcv', 'c.id', '=', 'rcv.candidate_id')
            ->leftJoin('results as r', function ($join) use ($publicStatuses) {
                $join->on('rcv.result_id', '=', 'r.id')
                     ->whereIn('r.certification_status', $publicStatuses);
            })
            ->where('c.election_id', $election->id)
            ->groupBy('c.id', 'c.name', 'pp.name', 'pp.abbreviation', 'pp.color')
            ->orderByDesc('total_votes')
            ->get();

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
    }
}