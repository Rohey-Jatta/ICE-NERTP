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
use Illuminate\Support\Facades\Cache;
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
        return Cache::remember('election_operations_dashboard', 25, fn () => $this->computeData());
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

        // Get all active stations with their admin area via hierarchy join
        // Count total stations per area and how many have submitted results
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

    private function incidentReport(?int $electionId): array
    {
        $base = fn () => Incident::forElection($electionId);

        $disputes      = (clone $base())->disputes()->count();
        $rejections    = (clone $base())->rejections()->count();
        $resubmissions = (clone $base())->resubmissions()->count();

        $byArea = Incident::forElection($electionId)
            ->whereNotNull('administrative_area_name')
            ->selectRaw("
                administrative_area_name,
                SUM(CASE WHEN type = 'dispute'       THEN 1 ELSE 0 END) as disputes,
                SUM(CASE WHEN type = 'rejection'     THEN 1 ELSE 0 END) as rejections,
                SUM(CASE WHEN type = 'resubmission'  THEN 1 ELSE 0 END) as resubmissions
            ")
            ->groupBy('administrative_area_name')
            ->orderByRaw("
                (
                    SUM(CASE WHEN type = 'dispute'      THEN 1 ELSE 0 END) +
                    SUM(CASE WHEN type = 'rejection'    THEN 1 ELSE 0 END) +
                    SUM(CASE WHEN type = 'resubmission' THEN 1 ELSE 0 END)
                ) DESC
            ")
            ->get()
            ->map(fn ($row) => [
                'administrative_area_name' => $row->administrative_area_name,
                'disputes'      => (int) $row->disputes,
                'rejections'    => (int) $row->rejections,
                'resubmissions' => (int) $row->resubmissions,
            ])
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
            $query = DB::table('monitor_observations as mo')
                ->join('polling_stations as ps', 'mo.polling_station_id', '=', 'ps.id')
                ->join('election_monitors as em', 'mo.election_monitor_id', '=', 'em.id')
                ->join('users as u', 'em.user_id', '=', 'u.id')
                ->leftJoin('administrative_hierarchy as w',   'ps.ward_id',  '=', 'w.id')
                ->leftJoin('administrative_hierarchy as con', 'w.parent_id', '=', 'con.id')
                ->leftJoin('administrative_hierarchy as aa',  'con.parent_id','=', 'aa.id')
                ->where('mo.is_public', true);

            // Scope to the current election if one is active
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

            // Count queries — also scoped to election
            $countBase = DB::table('monitor_observations as mo')
                ->join('election_monitors as em', 'mo.election_monitor_id', '=', 'em.id')
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
                    'has_photos'       => !empty($obs->photo_paths),
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

    // ── User Activity ──────────────────────────────────────────────────────

    private function userActivity(?int $electionId): array
    {
        $totalUsers           = User::count();
        $totalLogins          = AuditLog::where('action', 'auth.login.success')->count();
        $certificationActions = AuditLog::where('action', 'like', 'certification.%.approved')->count();

        // Login activity last 7 days
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $loginCounts  = AuditLog::where('action', 'auth.login.success')
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

        // Scope submissions/validations/certifications to the current election
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