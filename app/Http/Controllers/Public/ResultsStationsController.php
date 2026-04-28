<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ResultsStationsController extends Controller
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
                ->whereIn('status', ['active', 'certifying', 'results_pending', 'certified'])
                ->latest()->first();
        }

        if (!$election) {
            return Inertia::render('Public/ResultsStations', [
                'election'           => null,
                'elections'          => $availableElections,
                'selectedElectionId' => null,
                'stations'           => [],
                'isPublished'        => false,
                'filterOptions'      => ['wards' => [], 'constituencies' => [], 'adminAreas' => []],
            ]);
        }

        $isPublished = $election->status === 'certified';
        $cacheKey    = "results_stations_{$election->id}_" . ($isPublished ? 'pub' : 'prov');

        $data = Cache::remember($cacheKey, 300, fn() => $this->computeStations($election, $isPublished));

        // Filter options are cached separately (less volatile than station results)
        $filterOptions = Cache::remember("stations_filters_{$election->id}", 600, fn() => [
            'wards'         => DB::table('administrative_hierarchy')
                ->where('election_id', $election->id)
                ->where('level', 'ward')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'constituencies' => DB::table('administrative_hierarchy')
                ->where('election_id', $election->id)
                ->where('level', 'constituency')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'adminAreas'    => DB::table('administrative_hierarchy')
                ->where('election_id', $election->id)
                ->where('level', 'admin_area')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);

        return Inertia::render('Public/ResultsStations', array_merge($data, [
            'elections'          => $availableElections,
            'selectedElectionId' => $election->id,
            'isPublished'        => $isPublished,
            'filterOptions'      => $filterOptions,
        ]));
    }

    private function computeStations(Election $election, bool $isPublished): array
    {
        // ── Extended query: includes ward / constituency / admin_area for client-side filtering
        $stations = DB::select("
            SELECT
                ps.id,
                ps.code,
                ps.name,
                ps.registered_voters,
                -- Hierarchy (used for client-side filtering)
                w.id   AS ward_id,
                w.name AS ward_name,
                c.id   AS constituency_id,
                c.name AS constituency_name,
                aa.id   AS admin_area_id,
                aa.name AS admin_area_name,
                COALESCE(r.certification_status, 'not_reported') AS status,
                r.id                  AS result_id,
                r.total_votes_cast,
                r.valid_votes,
                r.rejected_votes,
                r.result_sheet_photo_path
            FROM polling_stations ps
            LEFT JOIN administrative_hierarchy w  ON ps.ward_id   = w.id
            LEFT JOIN administrative_hierarchy c  ON w.parent_id  = c.id
            LEFT JOIN administrative_hierarchy aa ON c.parent_id  = aa.id
            LEFT JOIN results r
                ON  r.polling_station_id = ps.id
                AND r.election_id        = ?
            WHERE ps.election_id = ?
            ORDER BY COALESCE(aa.name,''), COALESCE(c.name,''), COALESCE(w.name,''), ps.code
        ", [$election->id, $election->id]);

        $resultIds = collect($stations)->filter(fn($s) => $s->result_id !== null)
            ->pluck('result_id')->unique()->values()->toArray();

        $candidateVotesByResult   = [];
        $partyAcceptancesByResult = [];

        if (!empty($resultIds)) {
            $placeholders = implode(',', array_fill(0, count($resultIds), '?'));

            $cvRows = DB::select("
                SELECT
                    rcv.result_id,
                    c.name                           AS candidate_name,
                    COALESCE(pp.name, 'Independent') AS party_name,
                    COALESCE(pp.abbreviation, 'IND') AS party_abbr,
                    COALESCE(pp.color, '#6b7280')    AS party_color,
                    rcv.votes
                FROM result_candidate_votes rcv
                JOIN candidates c ON c.id = rcv.candidate_id
                LEFT JOIN political_parties pp ON pp.id = c.political_party_id
                WHERE rcv.result_id IN ({$placeholders})
                ORDER BY rcv.result_id, rcv.votes DESC
            ", $resultIds);

            foreach ($cvRows as $row) {
                $candidateVotesByResult[$row->result_id][] = [
                    'candidate_name' => $row->candidate_name,
                    'party_name'     => $row->party_name,
                    'party_abbr'     => $row->party_abbr,
                    'party_color'    => $row->party_color,
                    'votes'          => $row->votes,
                ];
            }

            if ($isPublished) {
                $paRows = DB::select("
                    SELECT
                        pa.result_id,
                        pp.name         AS party_name,
                        pp.abbreviation AS party_abbr,
                        pa.status,
                        pa.comments
                    FROM party_acceptances pa
                    JOIN political_parties pp ON pp.id = pa.political_party_id
                    WHERE pa.result_id IN ({$placeholders})
                ", $resultIds);

                foreach ($paRows as $row) {
                    $partyAcceptancesByResult[$row->result_id][] = [
                        'party_name' => $row->party_name,
                        'party_abbr' => $row->party_abbr,
                        'status'     => $row->status,
                        'comments'   => $row->comments,
                    ];
                }
            }
        }

        $mappedStations = collect($stations)->map(function ($station) use (
            $isPublished,
            $candidateVotesByResult,
            $partyAcceptancesByResult
        ) {
            $resultId = $station->result_id;
            return [
                'id'                => $station->id,
                'code'              => $station->code,
                'name'              => $station->name,
                'registered_voters' => $station->registered_voters,
                'status'            => $station->status,
                // Hierarchy IDs for client-side filtering
                'ward_id'           => $station->ward_id,
                'ward_name'         => $station->ward_name,
                'constituency_id'   => $station->constituency_id,
                'constituency_name' => $station->constituency_name,
                'admin_area_id'     => $station->admin_area_id,
                'admin_area_name'   => $station->admin_area_name,
                // Result data
                'total_votes_cast'  => $station->total_votes_cast,
                'valid_votes'       => $station->valid_votes,
                'rejected_votes'    => $station->rejected_votes,
                'candidate_votes'   => $resultId ? ($candidateVotesByResult[$resultId] ?? []) : [],
                'party_acceptances' => ($isPublished && $resultId)
                                        ? ($partyAcceptancesByResult[$resultId] ?? [])
                                        : [],
                'photo_url'         => ($isPublished && $station->result_sheet_photo_path)
                                        ? asset('storage/' . $station->result_sheet_photo_path)
                                        : null,
            ];
        })->toArray();

        return [
            'election' => [
                'id'   => $election->id,
                'name' => $election->name,
            ],
            'stations' => $mappedStations,
        ];
    }
}