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
        // ── 1. Build the available-elections dropdown ─────────────────────────
        // Shown in the election selector; uses allow_provisional_public_display
        // so admins can hide elections they haven't made public yet.
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

        // ── 2. Resolve which election to display ──────────────────────────────
        $selectedId    = (int) $request->get('election', 0);
        $electionModel = null;

        // If an explicit ID is given, try to find it regardless of the public flag
        // (direct URL access should always work)
        if ($selectedId) {
            $electionModel = Election::whereIn('status', ['active', 'certifying', 'results_pending', 'certified'])
                ->where('id', $selectedId)
                ->first();
        }

        // Default: prefer active/in-progress over an older certified one
        if (!$electionModel) {
            $electionModel = Election::whereIn('status', ['active', 'certifying', 'results_pending'])
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

        // ── 3. Compute / fetch cached summary data ────────────────────────────
        // Cache key includes election status so that when an election is published
        // (status: active → certified) the key changes and is always fresh.
        $cacheKey = "results_summary_v3_{$electionModel->id}_{$electionModel->status}";
        $data     = Cache::remember($cacheKey, 30, fn() => $this->computeSummary($electionModel));

        return Inertia::render('Public/Results', array_merge($data, [
            'elections'          => $availableElections,
            'selectedElectionId' => $electionModel->id,
        ]));
    }

    private function computeSummary(Election $election): array
    {
        // ── PUBLIC RULE: Only nationally_certified results appear in totals ────
        $publicStatuses = ['nationally_certified'];

        $electionPayload = [
            'id'         => $election->id,
            'name'       => $election->name,
            'type'       => $election->type,
            'status'     => $election->status,
            'start_date' => $election->start_date?->toDateString(),
        ];

        // ── Certified aggregate ────────────────────────────────────────────────
        // Use the latest nationally certified result per polling station.
        // This avoids stale/duplicate rows from older result versions.
        $latestCertifiedResults = <<<SQL
SELECT DISTINCT ON (polling_station_id)
    id, polling_station_id, total_votes_cast, valid_votes, rejected_votes
FROM results
WHERE election_id = {$election->id}
  AND certification_status = 'nationally_certified'
ORDER BY polling_station_id, nationally_certified_at DESC NULLS LAST, id DESC
SQL;

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
            ->where(function ($query) use ($election) {
                $query->where('ps.election_id', $election->id)
                      ->orWhereIn('ps.id', function ($query) use ($election) {
                          $query->select('polling_station_id')
                                ->from('results')
                                ->where('election_id', $election->id);
                      });
            })
            ->first();

        if (!$stats) {
            return [
                'election'   => $electionPayload,
                'stats'      => null,
                'pipeline'   => null,
                'candidates' => [],
                'message'    => 'Results will appear as the IEC Chairman certifies and publishes them.',
            ];
        }

        // ── Pipeline breakdown — gives the public visibility into progression ──
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
            ->selectRaw("\n                SUM(CASE WHEN certification_status = 'submitted'             THEN 1 ELSE 0 END) AS submitted,\n                SUM(CASE WHEN certification_status NOT IN ('submitted','nationally_certified') THEN 1 ELSE 0 END) AS under_review,\n                SUM(CASE WHEN certification_status = 'nationally_certified'  THEN 1 ELSE 0 END) AS certified\n            ")
            ->first();

        $pipeline = [
            'submitted'    => (int) ($pipelineRaw->submitted    ?? 0),
            'under_review' => (int) ($pipelineRaw->under_review ?? 0),
            'certified'    => (int) ($pipelineRaw->certified    ?? 0),
        ];

        // ── Candidate results: only from nationally_certified results ──────────
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
                'party_color' => $c->party_color,
                'total_votes' => (int) $c->total_votes,
            ]);

        // Don't show empty candidate rows if nothing is certified yet
        if ((int) $stats->stations_reported === 0) {
            $candidates = collect();
        }

        return [
            'election'   => $electionPayload,
            'stats'      => $stats,
            'pipeline'   => $pipeline,
            'candidates' => $candidates,
            'message'    => (int) $stats->stations_reported === 0
                ? 'No results have been officially published yet. Check back after the IEC Chairman certifies the first results.'
                : null,
        ];
    }
}
