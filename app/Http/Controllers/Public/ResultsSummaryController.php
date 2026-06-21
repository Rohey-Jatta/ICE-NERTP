<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\CurrentElectionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsSummaryController extends Controller
{
    private const PARTY_COLOR_FALLBACKS = [
        'NPP'   => '#155AA6',
        'UDP'   => '#D0AC4C',
        'GDC'   => '#684AC4',
        'PDOIS' => '#8B6253',
        'IND'   => '#6B7280',
        'NUP'   => '#7A3D9A',
    ];

    public function __construct(
        private readonly CurrentElectionResolver $electionResolver = new CurrentElectionResolver(),
    ) {}

    public function index(Request $request)
    {
        $availableElections = Election::where('allow_provisional_public_display', true)
            ->whereIn('status', ['active', 'submitting', 'certifying', 'results_pending', 'certified'])
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'type', 'status', 'start_date'])
            ->map(fn($e) => [
                'id'         => $e->id,
                'name'       => $e->name,
                'type'       => $e->type,
                'status'     => $e->status,
                'start_date' => $e->start_date?->toDateString(),
            ]);

        $selectedId    = (int) $request->get('election', 0);
        $electionModel = null;

        if ($selectedId) {
            $electionModel = Election::whereIn('status', ['active', 'submitting', 'certifying', 'results_pending', 'certified'])
                ->where('id', $selectedId)
                ->first();
        }

        // Prefer the CURRENT operational election (active, submitting,
        // certifying) per CurrentElectionResolver. Falls back to
        // results_pending then certified only when nothing is currently
        // operational — satisfies rule #7 (most recent qualifying election)
        // while still letting the public view recently-closed elections.
        if (!$electionModel) {
            $electionModel = $this->electionResolver->currentOrNull()
                ?? Election::where('status', 'results_pending')
                    ->latest('start_date')
                    ->first()
                ?? Election::where('status', 'certified')
                    ->latest('start_date')
                    ->first();
        }

        if (!$electionModel) {
            return Inertia::render('Public/Results', [
                'election'           => null,
                'elections'          => $availableElections,
                'selectedElectionId' => null,
                'stats'              => null,
                'pipeline'           => null,
                'candidates'         => [],
            ]);
        }

        $cacheKey = "results_summary_v7_{$electionModel->id}_{$electionModel->status}";
        $data     = Cache::remember($cacheKey, 30, fn() => $this->computeSummary($electionModel));

        return Inertia::render('Public/Results', array_merge($data, [
            'elections'          => $availableElections,
            'selectedElectionId' => $electionModel->id,
        ]));
    }

    private function computeSummary(Election $election): array
    {
        $publicStatuses = ['nationally_certified'];

        $electionPayload = [
            'id'         => $election->id,
            'name'       => $election->name,
            'type'       => $election->type,
            'status'     => $election->status,
            'start_date' => $election->start_date?->toDateString(),
        ];

        $latestCertifiedResults = <<<SQL
SELECT DISTINCT ON (polling_station_id)
    id, polling_station_id, total_votes_cast, valid_votes, rejected_votes
FROM results
WHERE election_id = {$election->id}
  AND certification_status = 'nationally_certified'
ORDER BY polling_station_id, nationally_certified_at DESC NULLS LAST, id DESC
SQL;

        // Station universe is now ALL active stations, not stations pinned
        // to this election_id. Results are still scoped to this election
        // via the subquery join above.
        $stats = DB::table('polling_stations as ps')
            ->selectRaw('
                COUNT(DISTINCT ps.id)                                      AS total_stations,
                COALESCE(SUM(ps.registered_voters), 0)                    AS total_registered,
                COUNT(DISTINCT r.id)                                       AS stations_reported,
                COALESCE(SUM(r.total_votes_cast), 0)                      AS total_cast,
                COALESCE(SUM(r.valid_votes), 0)                           AS valid_votes,
                COALESCE(SUM(r.rejected_votes), 0)                        AS rejected_votes
            ')
            ->leftJoin(DB::raw("({$latestCertifiedResults}) AS r"), 'ps.id', '=', 'r.polling_station_id')
            ->where('ps.is_active', true)
            ->first();

        if (!$stats) {
            return [
                'election'   => $electionPayload,
                'stats'      => null,
                'pipeline'   => null,
                'candidates' => [],
                'regions'    => [],
                'message'    => 'Results will appear as the IEC Chairman certifies and publishes them.',
            ];
        }

        $latestResults = <<<SQL
SELECT DISTINCT ON (polling_station_id)
    polling_station_id, certification_status
FROM results
WHERE election_id = {$election->id}
ORDER BY polling_station_id,
    CASE WHEN certification_status = 'nationally_certified' THEN 0 ELSE 1 END,
    nationally_certified_at DESC NULLS LAST,
    submitted_at DESC,
    id DESC
SQL;

        $pipelineRaw = DB::table(DB::raw("({$latestResults}) as r"))
            ->selectRaw("
                SUM(CASE WHEN certification_status = 'submitted'             THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN certification_status NOT IN ('submitted','nationally_certified') THEN 1 ELSE 0 END) AS under_review,
                SUM(CASE WHEN certification_status = 'nationally_certified'  THEN 1 ELSE 0 END) AS certified
            ")
            ->first();

        $pipeline = [
            'submitted'    => (int) ($pipelineRaw->submitted    ?? 0),
            'under_review' => (int) ($pipelineRaw->under_review ?? 0),
            'certified'    => (int) ($pipelineRaw->certified    ?? 0),
        ];

        $latestCertifiedResultIds = <<<SQL
    SELECT DISTINCT ON (polling_station_id)
        id
    FROM results
    WHERE election_id = {$election->id}
      AND certification_status = 'nationally_certified'
    ORDER BY polling_station_id, nationally_certified_at DESC NULLS LAST, id DESC
    SQL;

        $candidates = DB::table('candidates as c')
            ->selectRaw("
                c.id,
                c.name,
                c.photo_path,
                pp.leader_photo_path,
                COALESCE(pp.name, 'Independent')  AS party_name,
                COALESCE(pp.abbreviation, 'IND')  AS party_abbr,
                COALESCE(pp.color, '#6b7280')     AS party_color,
                COALESCE(SUM(CASE WHEN nr.id IS NOT NULL THEN rcv.votes ELSE 0 END), 0) AS total_votes
            ")
            ->leftJoin('political_parties as pp', 'c.political_party_id', '=', 'pp.id')
            ->leftJoin('result_candidate_votes as rcv', 'c.id', '=', 'rcv.candidate_id')
            ->leftJoin(DB::raw("({$latestCertifiedResultIds}) AS nr"), 'rcv.result_id', '=', 'nr.id')
            ->where('c.election_id', $election->id)
            ->where('c.is_active', true)
            ->groupBy('c.id', 'c.name', 'c.photo_path', 'pp.leader_photo_path', 'pp.name', 'pp.abbreviation', 'pp.color')
            ->orderByDesc('total_votes')
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'photo_url'   => $c->photo_path
                    ? asset('storage/' . $c->photo_path)
                    : ($c->leader_photo_path ? asset('storage/' . $c->leader_photo_path) : null),
                'party_name'  => $c->party_name,
                'party_abbr'  => $c->party_abbr,
                'party_color' => $this->partyColor($c->party_abbr, $c->party_color),
                'total_votes' => (int) $c->total_votes,
            ]);

        if ((int) $stats->stations_reported === 0) {
            $candidates = collect();
        }

        return [
            'election'   => $electionPayload,
            'stats'      => $stats,
            'pipeline'   => $pipeline,
            'candidates' => $candidates,
            'regions'    => $this->computeRegions($election),
            'message'    => (int) $stats->stations_reported === 0
                ? 'No results have been officially published yet. Check back after the IEC Chairman certifies the first results.'
                : null,
        ];
    }

    private function computeRegions(Election $election): array
    {
        $publicStatuses = ['nationally_certified'];

        $voteRows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->join('results as r', function ($join) use ($election, $publicStatuses) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id)
                     ->whereIn('r.certification_status', $publicStatuses);
            })
            ->join('result_candidate_votes as rcv', 'rcv.result_id', '=', 'r.id')
            ->join('candidates as c', 'c.id', '=', 'rcv.candidate_id')
            ->leftJoin('political_parties as pp', 'pp.id', '=', 'c.political_party_id')
            ->where('ps.is_active', true)
            ->groupBy('aa.id', 'aa.name', 'c.id', 'c.name', 'pp.abbreviation', 'pp.color')
            ->selectRaw("
                aa.id                            AS region_id,
                aa.name                          AS region_name,
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
            ->leftJoin('results as r', function ($join) use ($election, $publicStatuses) {
                $join->on('r.polling_station_id', '=', 'ps.id')
                     ->where('r.election_id', $election->id)
                     ->whereIn('r.certification_status', $publicStatuses);
            })
            ->where('ps.is_active', true)
            ->groupBy('aa.id', 'aa.name')
            ->selectRaw('
                aa.id                  AS region_id,
                aa.name                AS region_name,
                COUNT(DISTINCT ps.id)  AS total_stations,
                COUNT(DISTINCT r.id)   AS reported_stations
            ')
            ->get();

        $byRegion = [];
        foreach ($voteRows as $row) {
            $rid = $row->region_id;
            if (!isset($byRegion[$rid])) {
                $byRegion[$rid] = ['total_votes' => 0, 'candidates' => []];
            }
            $byRegion[$rid]['total_votes'] += (int) $row->votes;
            $byRegion[$rid]['candidates'][] = [
                'name'       => $row->candidate_name,
                'party_abbr' => $row->party_abbr,
                'color'      => $this->partyColor($row->party_abbr, $row->party_color),
                'votes'      => (int) $row->votes,
            ];
        }

        $regions = $countRows->map(function ($cnt) use ($byRegion) {
            $agg       = $byRegion[$cnt->region_id] ?? null;
            $leader    = null;
            $leaderPct = 0.0;

            if ($agg && $agg['total_votes'] > 0) {
                usort($agg['candidates'], fn($a, $b) => $b['votes'] <=> $a['votes']);
                $leader    = $agg['candidates'][0];
                $leaderPct = round($leader['votes'] / $agg['total_votes'] * 100, 1);
            }

            return [
                'id'                => $cnt->region_id,
                'name'              => $cnt->region_name,
                'total_stations'    => (int) $cnt->total_stations,
                'reported_stations' => (int) $cnt->reported_stations,
                'leader'            => $leader,
                'leader_pct'        => $leaderPct,
            ];
        })->toArray();

        usort($regions, fn($a, $b) => $b['total_stations'] <=> $a['total_stations']);

        return $regions;
    }

    private function partyColor(?string $abbreviation, ?string $databaseColor): string
    {
        if ($databaseColor) {
            return $databaseColor;
        }

        return self::PARTY_COLOR_FALLBACKS[strtoupper((string) $abbreviation)] ?? '#6B7280';
    }
}