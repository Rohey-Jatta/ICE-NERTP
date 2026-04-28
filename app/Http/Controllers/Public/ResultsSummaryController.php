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
                COALESCE(pp.name, 'Independent')   as party_name,
                COALESCE(pp.abbreviation, 'IND')   as party_abbr,
                COALESCE(pp.color, '#6b7280')       as party_color,
                COALESCE(SUM(rcv.votes), 0)         as total_votes
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
    }
}