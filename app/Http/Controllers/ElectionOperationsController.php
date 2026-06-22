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
        // Resolve active election
        $activeElection = Election::whereIn('status', ['active', 'certifying', 'results_pending'])
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
        ];
    }

    // ── Election Progress ──────────────────────────────────────────────────

    private function electionProgress(?int $electionId): array
    {
        $totalStations = PollingStation::when($electionId, fn ($q) => $q->where('election_id', $electionId))
            ->count();

        $stationsReceived = DB::table('results')
            ->when($electionId, fn ($q) => $q->where('election_id', $electionId))
            ->distinct('polling_station_id')
            ->count('polling_station_id');

        $reportingRate     = $totalStations > 0 ? round(($stationsReceived / $totalStations) * 100, 1) : 0;
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
        // Subquery: distinct polling_station_ids with results
        $receivedSub = DB::table('results')
            ->select('polling_station_id')
            ->when($electionId, fn ($q) => $q->where('election_id', $electionId))
            ->distinct();

        return DB::table('administrative_hierarchy as aa')
            ->where('aa.level', 'admin_area')
            ->when($electionId, fn ($q) => $q->where('aa.election_id', $electionId))
            ->leftJoin('administrative_hierarchy as con', function ($j) {
                $j->on('con.parent_id', '=', 'aa.id')->where('con.level', 'constituency');
            })
            ->leftJoin('administrative_hierarchy as w', function ($j) {
                $j->on('w.parent_id', '=', 'con.id')->where('w.level', 'ward');
            })
            ->leftJoin('polling_stations as ps', 'ps.ward_id', '=', 'w.id')
            ->leftJoin(
                DB::raw('(' . $receivedSub->toSql() . ') as r_ps'),
                fn ($j) => $j->on('r_ps.polling_station_id', '=', 'ps.id')
                             ->addBinding($receivedSub->getBindings())
            )
            ->groupBy('aa.id', 'aa.name')
            ->selectRaw('aa.id, aa.name, COUNT(DISTINCT ps.id) as total, COUNT(DISTINCT r_ps.polling_station_id) as received')
            ->orderByRaw('received DESC')
            ->get()
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

    private function incidentReport(?int $electionId): array
    {
        $base = fn () => Incident::forElection($electionId);

        $disputes      = (clone $base())->disputes()->count();
        $rejections    = (clone $base())->rejections()->count();
        $resubmissions = (clone $base())->resubmissions()->count();

        // NOTE: PostgreSQL cannot resolve SELECT aliases (disputes, rejections,
        // resubmissions) when they appear inside a compound ORDER BY expression
        // — it tries to resolve each identifier against the underlying table's
        // real columns instead, which causes "column does not exist" errors.
        // Fix: repeat the full SUM(CASE...) expressions in the ORDER BY clause
        // rather than referencing the SELECT aliases.
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

    // ── User Activity ──────────────────────────────────────────────────────

    private function userActivity(?int $electionId): array
    {
        $totalUsers           = User::count();
        $totalLogins          = AuditLog::where('action', 'auth.login.success')->count();
        $certificationActions = AuditLog::where('action', 'like', 'certification.%.approved')->count();

        // Login activity last 7 days — batched single query
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

        // Top actions
        $submissions    = Result::when($electionId, fn ($q) => $q->where('election_id', $electionId))->count();
        $validations    = ResultCertification::where('status', 'approved')
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