<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCertification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ElectionOperationsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ElectionOperations/Dashboard', [
            'data' => $this->getData(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->getData());
    }

    private function getData(): array
    {
        return $this->computeData();
    }

    private function computeData(): array
    {
        // Resolve active election — prefer in-flight, fall back to latest certified
        $activeElection = Election::whereIn('status', ['active', 'submitting', 'certifying', 'results_pending'])
            ->latest('start_date')
            ->first()
            ?? Election::where('status', 'certified')
                ->latest('start_date')
                ->first();

        $electionId = $activeElection?->id;

        return [
            'election'     => $this->electionPayload($activeElection),
            'progress'     => $this->electionProgress($electionId),
            'incidents'    => $this->incidentReport($electionId),
            'userActivity' => $this->userActivity($electionId),
            'observations' => $this->recentPublicObservations($electionId),
        ];
    }

    // ── Election Progress ──────────────────────────────────────────────────

    private function electionProgress(?int $electionId): array
    {
        if (!$electionId) {
            return [
                'totalStations'      => 0,
                'stationsReceived'   => 0,
                'reportingRate'      => 0,
                'outstandingStations'=> 0,
                'byArea'             => [],
            ];
        }

        // Total active stations (not scoped to election_id — stations float)
        $totalStations = PollingStation::where('is_active', true)->count();

        // Stations that have submitted ANY result for this election
        $stationsReceived = DB::table('results')
            ->where('election_id', $electionId)
            ->distinct('polling_station_id')
            ->count('polling_station_id');

        $reportingRate       = $totalStations > 0 ? round(($stationsReceived / $totalStations) * 100, 1) : 0;
        $outstandingStations = max(0, $totalStations - $stationsReceived);

        $byArea = $this->progressByArea($electionId);

        return [
            'totalStations'      => $totalStations,
            'stationsReceived'   => $stationsReceived,
            'reportingRate'      => $reportingRate,
            'outstandingStations'=> $outstandingStations,
            'byArea'             => $byArea,
        ];
    }

    private function progressByArea(?int $electionId): array
    {
        if (!$electionId) return [];

        $rows = DB::table('polling_stations as ps')
            ->join('administrative_hierarchy as w',   'ps.ward_id',    '=', 'w.id')
            ->join('administrative_hierarchy as con', 'w.parent_id',   '=', 'con.id')
            ->join('administrative_hierarchy as aa',  'con.parent_id', '=', 'aa.id')
            ->leftJoin(
                DB::raw("(
                    SELECT DISTINCT polling_station_id
                    FROM results
                    WHERE election_id = {$electionId}
                ) AS r_ps"),
                'r_ps.polling_station_id', '=', 'ps.id'
            )
            ->where('ps.is_active', true)
            ->groupBy('aa.id', 'aa.name')
            ->selectRaw('
                aa.id,
                aa.name,
                COUNT(DISTINCT ps.id) as total,
                COUNT(DISTINCT r_ps.polling_station_id) as received
            ')
            ->orderByRaw('received DESC')
            ->get();

        return $rows
            ->map(fn ($a) => [
                'name'        => $a->name,
                'total'       => (int) $a->total,
                'received'    => (int) $a->received,
                'rate'        => $a->total > 0 ? round(($a->received / $a->total) * 100) : 0,
                'outstanding' => max(0, (int) $a->total - (int) $a->received),
            ])
            ->filter(fn ($a) => $a['total'] > 0)
            ->values()
            ->toArray();
    }

    // ── Incident Report ────────────────────────────────────────────────────
    // Incidents are auto-created from:
    //   - 'rejection' type: when a ward/constituency/admin/national approver rejects a result
    //   - 'resubmission' type: when a polling officer resubmits a previously rejected result
    //   - 'dispute' type: when an election monitor submits an irregularity/incident/process_concern observation
    //   - Also from party_acceptances with status = 'rejected'

    private function incidentReport(?int $electionId): array
    {
        $incidentBase = fn () => Incident::forElection($electionId);

        $incidentDisputes      = (clone $incidentBase())->disputes()->whereNull('observation_id')->count();
        $incidentRejections    = (clone $incidentBase())->rejections()->count();
        $incidentResubmissions = (clone $incidentBase())->resubmissions()->count();

        // Some operational events are recorded in their source tables/audit
        // trail rather than materialized into incidents. Include those sources
        // so the dashboard cards reflect submitted disputes/resubmissions even
        // before or without an incident row.
        $monitorDisputes = DB::table('monitor_observations')
            ->when($electionId, fn($q) => $q->where('election_id', $electionId))
            ->whereIn('observation_type', ['irregularity', 'incident', 'process_concern'])
            ->count();

        $partyDisputes = DB::table('party_acceptances as pa')
            ->join('results as r', 'r.id', '=', 'pa.result_id')
            ->when($electionId, fn($q) => $q->where('r.election_id', $electionId))
            ->where('pa.status', 'rejected')
            ->count();

        $auditResubmissions = DB::table('audit_logs')
            ->when($electionId, fn($q) => $q->where('election_id', $electionId))
            ->where('action', 'result.resubmitted')
            ->count();

        $disputes      = $incidentDisputes + $monitorDisputes + $partyDisputes;
        $rejections    = $incidentRejections;
        $resubmissions = $incidentResubmissions + $auditResubmissions;

        $areaTotals = [];
        $addAreaRows = function ($rows) use (&$areaTotals) {
            foreach ($rows as $row) {
                $name = $row->administrative_area_name ?? 'Unknown';
                $areaTotals[$name] ??= [
                    'administrative_area_name' => $name,
                    'disputes' => 0,
                    'rejections' => 0,
                    'resubmissions' => 0,
                ];
                $areaTotals[$name]['disputes']      += (int) ($row->disputes ?? 0);
                $areaTotals[$name]['rejections']    += (int) ($row->rejections ?? 0);
                $areaTotals[$name]['resubmissions'] += (int) ($row->resubmissions ?? 0);
            }
        };

        $addAreaRows(Incident::forElection($electionId)
            ->whereNotNull('administrative_area_name')
            ->selectRaw("administrative_area_name,
                SUM(CASE WHEN type = 'dispute' AND observation_id IS NULL THEN 1 ELSE 0 END) as disputes,
                SUM(CASE WHEN type = 'rejection'    THEN 1 ELSE 0 END) as rejections,
                SUM(CASE WHEN type = 'resubmission' THEN 1 ELSE 0 END) as resubmissions")
            ->groupBy('administrative_area_name')
            ->get());

        $hierarchyJoin = function ($query) {
            return $query
                ->join('polling_stations as ps', 'ps.id', '=', 'src.polling_station_id')
                ->leftJoin('administrative_hierarchy as w', 'ps.ward_id', '=', 'w.id')
                ->leftJoin('administrative_hierarchy as con', 'w.parent_id', '=', 'con.id')
                ->leftJoin('administrative_hierarchy as aa', 'con.parent_id', '=', 'aa.id');
        };

        $addAreaRows($hierarchyJoin(DB::table('monitor_observations as src'))
            ->when($electionId, fn($q) => $q->where('src.election_id', $electionId))
            ->whereIn('src.observation_type', ['irregularity', 'incident', 'process_concern'])
            ->selectRaw('aa.name as administrative_area_name, COUNT(*) as disputes, 0 as rejections, 0 as resubmissions')
            ->groupBy('aa.name')
            ->get());

        $addAreaRows($hierarchyJoin(DB::table('party_acceptances as pa')
            ->join('results as src', 'src.id', '=', 'pa.result_id'))
            ->when($electionId, fn($q) => $q->where('src.election_id', $electionId))
            ->where('pa.status', 'rejected')
            ->selectRaw('aa.name as administrative_area_name, COUNT(*) as disputes, 0 as rejections, 0 as resubmissions')
            ->groupBy('aa.name')
            ->get());

        $addAreaRows($hierarchyJoin(DB::table('audit_logs as al')
            ->join('results as src', 'src.id', '=', 'al.result_id'))
            ->when($electionId, fn($q) => $q->where('al.election_id', $electionId))
            ->where('al.action', 'result.resubmitted')
            ->selectRaw('aa.name as administrative_area_name, 0 as disputes, 0 as rejections, COUNT(*) as resubmissions')
            ->groupBy('aa.name')
            ->get());

        $byArea = collect($areaTotals)
            ->sortByDesc(fn($row) => $row['disputes'] + $row['rejections'] + $row['resubmissions'])
            ->values()
            ->toArray();

        return [
            'disputes'      => $disputes,
            'rejections'    => $rejections,
            'resubmissions' => $resubmissions,
            'byArea'        => $byArea,
        ];
    }

    // ── Recent Public Observations (from Election Monitors) ───────────────

    private function recentPublicObservations(?int $electionId): array
    {
        try {
            // Get the current election's monitor IDs to scope observations correctly
            // Observations are linked to election_monitors, which are linked to elections
            $query = DB::table('monitor_observations as mo')
                ->join('polling_stations as ps', 'mo.polling_station_id', '=', 'ps.id')
                ->join('election_monitors as em', 'mo.election_monitor_id', '=', 'em.id')
                ->join('users as u', 'em.user_id', '=', 'u.id')
                ->leftJoin('administrative_hierarchy as w',   'ps.ward_id',  '=', 'w.id')
                ->leftJoin('administrative_hierarchy as con', 'w.parent_id', '=', 'con.id')
                ->leftJoin('administrative_hierarchy as aa',  'con.parent_id','=', 'aa.id')
                ->where('mo.is_public', true);

            // Scope to the election recorded on the submitted observation so newly
            // submitted public observations appear even if monitor accreditation data
            // is corrected independently.
            if ($electionId) {
                $query->where('mo.election_id', $electionId);
            }

            $rows = $query->select(
                    'mo.id',
                    'mo.title',
                    'mo.observation',
                    'mo.observation_type',
                    'mo.severity',
                    'mo.observed_at',
                    'mo.created_at',
                    'mo.latitude',
                    'mo.longitude',
                    'mo.photo_paths',
                    'mo.documents_paths',
                    'ps.name as station_name',
                    'ps.code as station_code',
                    'w.name as ward_name',
                    'con.name as constituency_name',
                    'aa.name as admin_area_name',
                    'u.name as monitor_name'
                )
                ->orderByDesc('mo.observed_at')
                ->limit(20)
                ->get();

            // Count queries — scoped by the observation's election_id for consistency
            // with the recent rows above.
            $countBase = DB::table('monitor_observations as mo')
                ->where('mo.is_public', true);

            if ($electionId) {
                $countBase->where('mo.election_id', $electionId);
            }

            $totalCount    = (clone $countBase)->count();
            $criticalCount = (clone $countBase)->where('mo.severity', 'critical')->count();
            $flaggedCount  = (clone $countBase)
                ->whereIn('mo.observation_type', ['irregularity', 'incident', 'process_concern'])
                ->count();

            return [
                'total'    => $totalCount,
                'critical' => $criticalCount,
                'flagged'  => $flaggedCount,
                'recent'   => $rows->map(fn ($obs) => [
                    'id'               => $obs->id,
                    'title'            => $obs->title,
                    'observation'      => mb_substr($obs->observation, 0, 200) . (mb_strlen($obs->observation) > 200 ? '…' : ''),
                    'observation_type' => $obs->observation_type,
                    'severity'         => $obs->severity,
                    'observed_at'      => $obs->observed_at,
                    'station_name'     => $obs->station_name,
                    'station_code'     => $obs->station_code,
                    'ward_name'        => $obs->ward_name,
                    'constituency_name'=> $obs->constituency_name,
                    'admin_area_name'  => $obs->admin_area_name,
                    'monitor_name'     => $obs->monitor_name,
                    'latitude'         => $obs->latitude,
                    'longitude'        => $obs->longitude,
                    'photos'           => $this->filePayloads($obs->photo_paths),
                    'documents'        => $this->filePayloads($obs->documents_paths),
                    'has_photos'       => !empty($obs->photo_paths),
                    'has_documents'    => !empty($obs->documents_paths),
                ])->toArray(),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[ElectionOps] Observations fetch failed: ' . $e->getMessage());
            return [
                'total'    => 0,
                'critical' => 0,
                'flagged'  => 0,
                'recent'   => [],
            ];
        }
    }


    private function filePayloads($paths): array
    {
        $decoded = is_string($paths) ? json_decode($paths, true) : $paths;
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->map(fn ($path) => [
                'name' => basename($path),
                'url'  => asset('storage/' . ltrim($path, '/')),
            ])
            ->values()
            ->toArray();
    }

    // ── User Activity ──────────────────────────────────────────────────────

    private function userActivity(?int $electionId): array
    {
        // Total users and logins are system-wide metrics (not election-scoped)
        $totalUsers  = User::count();

        // Login activity last 7 days — system-wide (who logged in recently)
        $sevenDaysAgo = now()->subDays(6)->startOfDay();

        $totalLogins = AuditLog::where('action', 'auth.login.success')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        // Certification actions scoped to current election via audit log election_id
        $certificationActions = AuditLog::where('action', 'like', 'certification.%.approved')
            ->when($electionId, fn ($q) => $q->where('election_id', $electionId))
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        // Login activity chart — last 7 days
        $loginCounts = AuditLog::where('action', 'auth.login.success')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->groupBy('date')
            ->pluck('count', 'date');

        $loginActivity = collect(range(6, 0))->map(function ($daysAgo) use ($loginCounts) {
            $date = now()->subDays($daysAgo)->toDateString();
            return [
                'day'   => now()->subDays($daysAgo)->format('D'),
                'count' => (int) ($loginCounts[$date] ?? 0),
            ];
        })->values();

        // Election-scoped submission/validation/certification stats
        $submissions = Result::when($electionId, fn ($q) => $q->where('election_id', $electionId))->count();

        $validations = ResultCertification::where('status', 'approved')
            ->whereIn('certification_level', ['ward', 'constituency', 'admin_area'])
            ->when($electionId, fn ($q) => $q->whereHas('result', fn ($r) => $r->where('election_id', $electionId)))
            ->count();

        $certifications = Result::where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)
            ->when($electionId, fn ($q) => $q->where('election_id', $electionId))
            ->count();

        return [
            'totalUsers'           => $totalUsers,
            'totalLogins'          => $totalLogins,
            'certificationActions' => $certificationActions,
            'loginActivity'        => $loginActivity,
            'submissions'          => $submissions,
            'validations'          => $validations,
            'certifications'       => $certifications,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function electionPayload(?Election $election): ?array
    {
        if (! $election) {
            return null;
        }

        return [
            'id'     => $election->id,
            'name'   => $election->name,
            'type'   => $election->type,
            'status' => $election->status,
        ];
    }
}