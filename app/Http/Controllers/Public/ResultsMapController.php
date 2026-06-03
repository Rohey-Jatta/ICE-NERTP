<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsMapController extends Controller
{
    // Named party-color fallbacks (mirrors ResultsSummaryController) so the
    // choropleth shows distinct party colors even when pp.color is unset.
    private const PARTY_COLOR_FALLBACKS = [
        'NPP'   => '#155AA6',
        'UDP'   => '#D0AC4C',
        'GDC'   => '#684AC4',
        'PDOIS' => '#8B6253',
        'IND'   => '#6B7280',
        'NUP'   => '#7A3D9A',
    ];

    private function partyColor(?string $abbreviation, ?string $databaseColor): string
    {
        if ($databaseColor) {
            return $databaseColor;
        }
        return self::PARTY_COLOR_FALLBACKS[strtoupper((string) $abbreviation)] ?? '#6B7280';
    }

    // ── Public page ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
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

        $election = $this->resolvePublicElection($selectedId);

        if (!$election) {
            return Inertia::render('Public/ResultsMap', [
                'stations'           => [],
                'election'           => null,
                'elections'          => $availableElections,
                'selectedElectionId' => null,
            ]);
        }

        $cacheKey = "results_map_{$election->id}";
        $stations = Cache::remember($cacheKey, 300, fn() => $this->computeStationsData($election));

        $aggKey = "results_map_agg_v3_{$election->id}";
        $agg    = Cache::remember($aggKey, 300, fn() => $this->computeRegionAggregates($election));

        return Inertia::render('Public/ResultsMap', [
            'stations'           => $stations,
            'regions'            => $agg['regions'],
            'national'           => $agg['national'],
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

    // ── JSON endpoint for homepage embedded map ───────────────────────────────

    public function stationsJson(Request $request): JsonResponse
    {
        $selectedId = (int) $request->get('election', 0);
        $election   = $this->resolvePublicElection($selectedId);

        if (!$election) {
            return response()->json(['stations' => []]);
        }

        $cacheKey = "results_map_{$election->id}";
        $stations = Cache::remember($cacheKey, 300, fn() => $this->computeStationsData($election));

        return response()->json(['stations' => $stations]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve which publicly displayable election to show.
     * Prefers $selectedId if valid, otherwise falls back to latest active/certified.
     */
    private function resolvePublicElection(int $selectedId): ?Election
    {
        if ($selectedId) {
            $election = Election::where('allow_provisional_public_display', true)
                ->whereIn('status', ['active', 'certifying', 'results_pending', 'certified'])
                ->where('id', $selectedId)
                ->first();
            if ($election) return $election;
        }

        return Election::where('allow_provisional_public_display', true)
            ->where('status', 'active')
            ->latest()
            ->first()
            ?? Election::whereIn('status', ['certifying', 'results_pending', 'certified'])
                ->where('allow_provisional_public_display', true)
                ->latest()
                ->first();
    }

    /**
     * Build the stations array for the Leaflet map.
     * Used by both the full map page (index) and the JSON API (stationsJson).
     * Results are cached under "results_map_{election_id}".
     */
    private function computeStationsData(Election $election): array
    {
        $stationsRaw = DB::table('polling_stations as ps')
            ->selectRaw("
                ps.id, ps.code, ps.name, ps.registered_voters,
                ps.latitude, ps.longitude,
                aa.name  AS admin_area_name,
                cst.name AS constituency_name,
                w.name   AS ward_name,
                COALESCE(r.certification_status, 'not_reported') AS status,
                r.id AS result_id,
                r.total_votes_cast, r.valid_votes, r.rejected_votes
            ")
            ->leftJoin('administrative_hierarchy as w',   'w.id',   '=', 'ps.ward_id')
            ->leftJoin('administrative_hierarchy as cst', 'cst.id', '=', 'w.parent_id')
            ->leftJoin('administrative_hierarchy as aa',  'aa.id',  '=', 'cst.parent_id')
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
                    pp.color                         AS color,
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
                'color' => $this->partyColor($v->party, $v->color),
                'votes' => $v->votes,
            ])->values()->all();
            return $station;
        })->toArray();
    }

    /**
     * Per-region (admin_area) leading candidate + candidate breakdown, plus a
     * national scorecard. Powers the CNN-style choropleth and header.
     *
     * Unlike the public summary, the live map reflects ALL reported results
     * regardless of certification level (provisional + certified), matching the
     * existing per-station map behaviour.
     */
    private function computeRegionAggregates(Election $election): array
    {
        // Candidate votes per admin_area (any reported result).
        $voteRows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->join('results as r', function ($join) use ($election) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id);
            })
            ->join('result_candidate_votes as rcv', 'rcv.result_id', '=', 'r.id')
            ->join('candidates as c', 'c.id', '=', 'rcv.candidate_id')
            ->leftJoin('political_parties as pp', 'pp.id', '=', 'c.political_party_id')
            ->where('ps.election_id', $election->id)
            ->groupBy('aa.name', 'c.id', 'c.name', 'c.photo_path', 'pp.leader_photo_path', 'pp.abbreviation', 'pp.color')
            ->selectRaw("
                aa.name                          AS region_name,
                c.name                           AS candidate_name,
                c.photo_path                     AS photo_path,
                pp.leader_photo_path             AS leader_photo_path,
                COALESCE(pp.abbreviation, 'IND') AS party_abbr,
                pp.color                          AS party_color,
                SUM(rcv.votes)                   AS votes
            ")
            ->get();

        // Station counts per admin_area (total + reported).
        $countRows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->leftJoin('results as r', function ($join) use ($election) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id);
            })
            ->where('ps.election_id', $election->id)
            ->groupBy('aa.name')
            ->selectRaw('
                aa.name                AS region_name,
                COUNT(DISTINCT ps.id)  AS total_stations,
                COUNT(DISTINCT r.id)   AS reported_stations
            ')
            ->get()
            ->keyBy('region_name');

        // Group candidate votes by region.
        $byRegion = [];
        foreach ($voteRows as $row) {
            $byRegion[$row->region_name]['total'] = ($byRegion[$row->region_name]['total'] ?? 0) + (int) $row->votes;
            $byRegion[$row->region_name]['cands'][] = [
                'name'  => $row->candidate_name,
                'party' => $row->party_abbr,
                'color' => $this->partyColor($row->party_abbr, $row->party_color),
                'votes' => (int) $row->votes,
            ];
        }

        $constituencies = $this->computeConstituencies($election);

        $regions = [];
        foreach ($countRows as $name => $cnt) {
            $agg    = $byRegion[$name] ?? null;
            $total  = $agg['total'] ?? 0;
            $cands  = $agg['cands'] ?? [];
            usort($cands, fn($a, $b) => $b['votes'] <=> $a['votes']);

            $cands = array_map(fn($c) => $c + [
                'pct' => $total > 0 ? round($c['votes'] / $total * 100, 1) : 0,
            ], $cands);

            $regions[] = [
                'name'              => $name,
                'total_stations'    => (int) $cnt->total_stations,
                'reported_stations' => (int) $cnt->reported_stations,
                'reporting_pct'     => $cnt->total_stations > 0
                    ? round($cnt->reported_stations / $cnt->total_stations * 100)
                    : 0,
                'total_votes'       => $total,
                'leader'            => $cands[0] ?? null,
                'candidates'        => $cands,
                'constituencies'    => $constituencies[$name] ?? [],
            ];
        }

        // National scorecard — sum candidate votes across all regions.
        $national = [];
        foreach ($voteRows as $row) {
            $key = $row->candidate_name;
            if (!isset($national[$key])) {
                $photo = $row->photo_path ?: $row->leader_photo_path;
                $national[$key] = [
                    'name'      => $row->candidate_name,
                    'party'     => $row->party_abbr,
                    'color'     => $this->partyColor($row->party_abbr, $row->party_color),
                    'photo_url' => $photo ? asset('storage/' . $photo) : null,
                    'votes'     => 0,
                ];
            }
            $national[$key]['votes'] += (int) $row->votes;
        }
        $national = array_values($national);
        usort($national, fn($a, $b) => $b['votes'] <=> $a['votes']);
        $nationalTotal = array_sum(array_column($national, 'votes'));
        $national = array_map(fn($c) => $c + [
            'pct' => $nationalTotal > 0 ? round($c['votes'] / $nationalTotal * 100, 2) : 0,
        ], $national);

        $totalStations    = (int) $countRows->sum('total_stations');
        $reportedStations = (int) $countRows->sum('reported_stations');

        return [
            'regions'  => $regions,
            'national' => [
                'candidates'        => $national,
                'total_votes'       => $nationalTotal,
                'total_stations'    => $totalStations,
                'reported_stations' => $reportedStations,
                'reporting_pct'     => $totalStations > 0
                    ? round($reportedStations / $totalStations * 100)
                    : 0,
            ],
        ];
    }

    /**
     * Per-constituency leader + totals, keyed by parent region (admin_area)
     * name. Powers the region drill-down breakdown panel.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function computeConstituencies(Election $election): array
    {
        $voteRows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->join('results as r', function ($join) use ($election) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id);
            })
            ->join('result_candidate_votes as rcv', 'rcv.result_id', '=', 'r.id')
            ->join('candidates as c', 'c.id', '=', 'rcv.candidate_id')
            ->leftJoin('political_parties as pp', 'pp.id', '=', 'c.political_party_id')
            ->where('ps.election_id', $election->id)
            ->groupBy('aa.name', 'con.name', 'c.name', 'pp.abbreviation', 'pp.color')
            ->selectRaw("
                aa.name                          AS region_name,
                con.name                         AS con_name,
                c.name                           AS candidate_name,
                COALESCE(pp.abbreviation, 'IND') AS party_abbr,
                pp.color                         AS party_color,
                SUM(rcv.votes)                   AS votes
            ")
            ->get();

        $countRows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->leftJoin('results as r', function ($join) use ($election) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id);
            })
            ->where('ps.election_id', $election->id)
            ->groupBy('aa.name', 'con.name')
            ->selectRaw('
                aa.name                AS region_name,
                con.name               AS con_name,
                COUNT(DISTINCT ps.id)  AS total_stations,
                COUNT(DISTINCT r.id)   AS reported_stations
            ')
            ->get();

        // Accumulate votes per constituency.
        $acc = [];
        foreach ($voteRows as $row) {
            $key = $row->region_name . '||' . $row->con_name;
            $acc[$key]['total'] = ($acc[$key]['total'] ?? 0) + (int) $row->votes;
            $acc[$key]['cands'][] = [
                'name'  => $row->candidate_name,
                'party' => $row->party_abbr,
                'color' => $this->partyColor($row->party_abbr, $row->party_color),
                'votes' => (int) $row->votes,
            ];
        }

        // Also compute ward-level breakdown keyed by region||constituency.
        $wards = $this->computeWards($election);

        $byRegion = [];
        foreach ($countRows as $row) {
            $key   = $row->region_name . '||' . $row->con_name;
            $total = $acc[$key]['total'] ?? 0;
            $cands = $acc[$key]['cands'] ?? [];
            usort($cands, fn($a, $b) => $b['votes'] <=> $a['votes']);
            $leader = $cands[0] ?? null;

            $byRegion[$row->region_name][] = [
                'name'              => $row->con_name,
                'total_stations'    => (int) $row->total_stations,
                'reported_stations' => (int) $row->reported_stations,
                'reporting_pct'     => $row->total_stations > 0
                    ? round($row->reported_stations / $row->total_stations * 100)
                    : 0,
                'total_votes'       => $total,
                'leader'            => $leader,
                'leader_pct'        => ($leader && $total > 0) ? round($leader['votes'] / $total * 100, 1) : 0,
                'wards'             => $wards[$row->region_name][$row->con_name] ?? [],
            ];
        }

        // Largest constituencies first within each region.
        foreach ($byRegion as &$list) {
            usort($list, fn($a, $b) => $b['total_votes'] <=> $a['total_votes']);
        }

        return $byRegion;
    }

    /**
     * Per-ward leader + totals, keyed by [region_name][constituency_name].
     * Powers the constituency → ward drill-down panel.
     */
    private function computeWards(Election $election): array
    {
        $voteRows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->join('results as r', function ($join) use ($election) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id);
            })
            ->join('result_candidate_votes as rcv', 'rcv.result_id', '=', 'r.id')
            ->join('candidates as c', 'c.id', '=', 'rcv.candidate_id')
            ->leftJoin('political_parties as pp', 'pp.id', '=', 'c.political_party_id')
            ->where('ps.election_id', $election->id)
            ->groupBy('aa.name', 'con.name', 'w.name', 'c.name', 'pp.abbreviation', 'pp.color')
            ->selectRaw("
                aa.name                          AS region_name,
                con.name                         AS con_name,
                w.name                           AS ward_name,
                c.name                           AS candidate_name,
                COALESCE(pp.abbreviation, 'IND') AS party_abbr,
                pp.color                         AS party_color,
                SUM(rcv.votes)                   AS votes
            ")
            ->get();

        $countRows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->leftJoin('results as r', function ($join) use ($election) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id);
            })
            ->where('ps.election_id', $election->id)
            ->groupBy('aa.name', 'con.name', 'w.name')
            ->selectRaw('
                aa.name                AS region_name,
                con.name               AS con_name,
                w.name                 AS ward_name,
                COUNT(DISTINCT ps.id)  AS total_stations,
                COUNT(DISTINCT r.id)   AS reported_stations
            ')
            ->get();

        // Accumulate votes per ward.
        $acc = [];
        foreach ($voteRows as $row) {
            $key = $row->region_name . '||' . $row->con_name . '||' . $row->ward_name;
            $acc[$key]['total'] = ($acc[$key]['total'] ?? 0) + (int) $row->votes;
            $acc[$key]['cands'][] = [
                'name'  => $row->candidate_name,
                'party' => $row->party_abbr,
                'color' => $this->partyColor($row->party_abbr, $row->party_color),
                'votes' => (int) $row->votes,
            ];
        }

        $byConKey = [];
        foreach ($countRows as $row) {
            $key   = $row->region_name . '||' . $row->con_name . '||' . $row->ward_name;
            $total = $acc[$key]['total'] ?? 0;
            $cands = $acc[$key]['cands'] ?? [];
            usort($cands, fn($a, $b) => $b['votes'] <=> $a['votes']);
            $leader = $cands[0] ?? null;

            $byConKey[$row->region_name][$row->con_name][] = [
                'name'              => $row->ward_name,
                'total_stations'    => (int) $row->total_stations,
                'reported_stations' => (int) $row->reported_stations,
                'reporting_pct'     => $row->total_stations > 0
                    ? round($row->reported_stations / $row->total_stations * 100)
                    : 0,
                'total_votes'       => $total,
                'leader'            => $leader,
                'leader_pct'        => ($leader && $total > 0) ? round($leader['votes'] / $total * 100, 1) : 0,
            ];
        }

        // Sort wards by vote count descending within each constituency.
        foreach ($byConKey as &$conMap) {
            foreach ($conMap as &$wardList) {
                usort($wardList, fn($a, $b) => $b['total_votes'] <=> $a['total_votes']);
            }
        }

        return $byConKey;
    }
}
