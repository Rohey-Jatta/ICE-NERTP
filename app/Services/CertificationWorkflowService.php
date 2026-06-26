<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Incident;
use App\Models\Result;
use App\Models\ResultCertification;
use App\Models\User;
use App\Models\AdministrativeHierarchy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CertificationWorkflowService — Sequential approval state machine.
 */
class CertificationWorkflowService
{
    const CERTIFIED_LEVELS = [
        'ward'         => Result::STATUS_WARD_CERTIFIED,
        'constituency' => Result::STATUS_CONSTITUENCY_CERTIFIED,
        'admin_area'   => Result::STATUS_ADMIN_AREA_CERTIFIED,
        'national'     => Result::STATUS_NATIONALLY_CERTIFIED,
    ];

    const REJECTION_TARGETS = [
        'ward'         => Result::STATUS_SUBMITTED,
        'constituency' => Result::STATUS_PENDING_WARD,
        'admin_area'   => Result::STATUS_PENDING_CONSTITUENCY,
        'national'     => Result::STATUS_PENDING_ADMIN_AREA,
    ];

    /**
     * Approve a result at a specific certification level.
     */
    public function approve(
        Result $result,
        User $approver,
        string $level,
        ?string $comments = null,
        bool $withReservation = false
    ): bool
    {
        if (!$this->canApprove($approver, $level)) {
            throw new \Exception("User does not have permission to approve at {$level} level.");
        }

        if (!$this->isPendingAtLevel($result, $level)) {
            throw new \Exception("Result is not pending approval at {$level} level. Current status: {$result->certification_status}");
        }

        DB::beginTransaction();
        try {
            $node = $this->resolveHierarchyNode($result, $approver, $level);
            $decisionComments = $withReservation && $comments
                ? '[RESERVATION] ' . $comments
                : $comments;

            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => $level,
                'hierarchy_node_id'   => $node->id,
                'approver_id'         => $approver->id,
                'status'              => 'approved',
                'comments'            => $decisionComments,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $certifiedStatus = self::CERTIFIED_LEVELS[$level];
            $updates = ['certification_status' => $certifiedStatus];
            if ($level === 'national') {
                $updates['nationally_certified_at'] = now();
            }
            $result->forceFill($updates)->save();

            $this->promoteToNextLevel($result);

            $this->createVersionSnapshot(
                $result,
                $approver,
                $withReservation
                    ? "Approved with reservation at {$level} level"
                    : "Approved at {$level} level"
            );

            AuditLog::record(
                action:    $withReservation
                    ? "certification.{$level}.approved_with_reservation"
                    : "certification.{$level}.approved",
                event:     'updated',
                module:    'Certification',
                auditable: $result,
                extra:     [
                    'level'       => $level,
                    'outcome'     => 'success',
                    'comments'    => $comments,
                    'reservation' => $withReservation ? $comments : null,
                    'election_id' => $result->election_id,
                ]
            );

            DB::commit();
            $this->forgetWorkflowCaches($result);
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject a result at a specific level and return it to the correct earlier review point.
     */
    public function reject(Result $result, User $approver, string $level, string $reason): bool
    {
        if (!$this->canApprove($approver, $level)) {
            throw new \Exception("User does not have permission to reject at {$level} level.");
        }

        if (!$this->isPendingAtLevel($result, $level)) {
            throw new \Exception("Result is not pending approval at {$level} level.");
        }

        DB::beginTransaction();
        try {
            $node = $this->resolveHierarchyNode($result, $approver, $level);

            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => $level,
                'hierarchy_node_id'   => $node->id,
                'approver_id'         => $approver->id,
                'status'              => 'rejected',
                'comments'            => $reason,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            $result->update([
                'certification_status' => $this->getRejectionTargetStatus($level),
                'last_rejection_reason'=> $reason,
                'last_rejected_by'     => $approver->id,
                'last_rejected_at'     => now(),
                'rejection_count'      => $result->rejection_count + 1,
            ]);

            $this->createVersionSnapshot($result, $approver, "Rejected at {$level} level: {$reason}");

            AuditLog::record(
                action:    "certification.{$level}.rejected",
                event:     'updated',
                module:    'Certification',
                auditable: $result,
                extra:     [
                    'level'          => $level,
                    'reason'         => $reason,
                    'outcome'        => 'failure',
                    'failure_reason' => $reason,
                    'election_id'    => $result->election_id,
                ]
            );

            DB::commit();
            $this->forgetWorkflowCaches($result);

            // ── Auto-create Rejection Incident ────────────────────────────
            $this->createRejectionIncident($result, $level, $reason);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get the approval queue for a specific level.
     */
    public function getApprovalQueue(string $level, User $approver, array $filters = [])
    {
        $pendingStatus = $this->getPendingStatus($level);

        $query = Result::with([
            'pollingStation.ward',
            'election',
            'candidateVotes.candidate',
            'partyAcceptances.politicalParty',
        ])->where('certification_status', $pendingStatus);

        // Scope to the approver's assigned area
        if ($level === 'ward') {
            $wardId = AdministrativeHierarchy::where('assigned_approver_id', $approver->id)
                ->where('level', 'ward')->value('id');
            if ($wardId) {
                $query->whereHas('pollingStation', fn($q) => $q->where('ward_id', $wardId));
            }
        }

        if ($level === 'constituency') {
            $constId = AdministrativeHierarchy::where('assigned_approver_id', $approver->id)
                ->where('level', 'constituency')->value('id');
            if ($constId) {
                $query->whereHas('pollingStation.ward', fn($q) => $q->where('parent_id', $constId));
            }
        }

        if ($level === 'admin_area') {
            $areaId = AdministrativeHierarchy::where('assigned_approver_id', $approver->id)
                ->where('level', 'admin_area')->value('id');
            if ($areaId) {
                $query->whereHas('pollingStation.ward.parent', fn($q) => $q->where('parent_id', $areaId));
            }
        }

        if (!empty($filters['station_code'])) {
            $query->whereHas('pollingStation', fn($q) =>
                $q->where('code', 'like', '%' . $filters['station_code'] . '%')
            );
        }

        return $query->orderBy('submitted_at')->paginate(20);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Auto-create a Rejection incident after a result is rejected.
     */
    private function createRejectionIncident(Result $result, string $level, string $reason): void
    {
        try {
            $result->loadMissing('pollingStation');
            $station = $result->pollingStation;

            // Resolve admin area via hierarchy
            $adminArea = DB::table('administrative_hierarchy as aa')
                ->join('administrative_hierarchy as con', 'con.parent_id', '=', 'aa.id')
                ->join('administrative_hierarchy as w',   'w.parent_id',   '=', 'con.id')
                ->join('polling_stations as ps',          'ps.ward_id',    '=', 'w.id')
                ->where('ps.id', $result->polling_station_id)
                ->select('aa.id', 'aa.name')
                ->first();

            Incident::create([
                'election_id'              => $result->election_id,
                'result_id'                => $result->id,
                'type'                     => 'rejection',
                'administrative_area_id'   => $adminArea?->id,
                'administrative_area_name' => $adminArea?->name,
                'polling_station_id'       => $result->polling_station_id,
                'polling_station_name'     => $station?->name,
                'description'              => "Result rejected at {$level} level: {$reason}",
            ]);

            // Bust operations dashboard cache
            Cache::forget('election_operations_dashboard');
        } catch (\Throwable $e) {
            Log::warning('[Incident] Failed to create rejection incident: ' . $e->getMessage());
        }
    }

    private function promoteToNextLevel(Result $result): void
    {
        $next = match ($result->certification_status) {
            Result::STATUS_WARD_CERTIFIED         => Result::STATUS_PENDING_CONSTITUENCY,
            Result::STATUS_CONSTITUENCY_CERTIFIED => Result::STATUS_PENDING_ADMIN_AREA,
            Result::STATUS_ADMIN_AREA_CERTIFIED   => Result::STATUS_PENDING_NATIONAL,
            default                                => null,
        };

        if ($next) {
            $result->forceFill(['certification_status' => $next])->save();
        }
    }

    private function isPendingAtLevel(Result $result, string $level): bool
    {
        $allowed = [$this->getPendingStatus($level)];

        if ($level === 'ward') {
            $allowed[] = Result::STATUS_PENDING_PARTY_ACCEPTANCE;
            // Also allow rejections that came back to pending_ward
            // (e.g. rejected from constituency, now at pending_ward with rejection_count > 0)
        }

        return in_array($result->certification_status, $allowed, true);
    }

    private function canApprove(User $user, string $level): bool
    {
        return match ($level) {
            'ward'         => $user->hasRole('ward-approver'),
            'constituency' => $user->hasRole('constituency-approver'),
            'admin_area'   => $user->hasRole('admin-area-approver'),
            'national'     => $user->hasRole('iec-chairman'),
            default        => false,
        };
    }

    private function getPendingStatus(string $level): string
    {
        return match ($level) {
            'ward'         => Result::STATUS_PENDING_WARD,
            'constituency' => Result::STATUS_PENDING_CONSTITUENCY,
            'admin_area'   => Result::STATUS_PENDING_ADMIN_AREA,
            'national'     => Result::STATUS_PENDING_NATIONAL,
            default        => throw new \Exception("Invalid certification level: {$level}"),
        };
    }

    private function getRejectionTargetStatus(string $level): string
    {
        return self::REJECTION_TARGETS[$level]
            ?? throw new \Exception("Invalid certification level: {$level}");
    }

    private function resolveHierarchyNode(Result $result, User $approver, string $level): AdministrativeHierarchy
    {
        $node = AdministrativeHierarchy::where('assigned_approver_id', $approver->id)
            ->where('level', $level)
            ->first();

        if ($node) {
            return $node;
        }

        if ($level === 'ward' && $result->pollingStation?->ward_id) {
            $node = AdministrativeHierarchy::find($result->pollingStation->ward_id);
            if ($node) {
                return $node;
            }
        }

        $fallback = AdministrativeHierarchy::where('level', $level)->first()
            ?? AdministrativeHierarchy::orderBy('id')->first();

        if (!$fallback) {
            throw new \Exception("No hierarchy node available for {$level} certification.");
        }

        return $fallback;
    }

    private function createVersionSnapshot(Result $result, User $user, string $reason): void
    {
        try {
            $result->versions()->create([
                'version_number'                => $result->versions()->count() + 1,
                'result_snapshot'               => $result->toArray(),
                'votes_snapshot'                => $result->candidateVotes->toArray(),
                'changed_by'                    => $user->id,
                'change_reason'                 => 'initial_submission',
                'change_notes'                  => $reason,
                'certification_status_at_version'=> $result->certification_status,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CertificationWorkflow] version snapshot failed: ' . $e->getMessage());
        }
    }

    private function forgetWorkflowCaches(Result $result): void
    {
        Election::forgetPublicCaches($result->election_id, $result->election?->status);
        Cache::forget('chairman_dashboard_stats');
        Cache::forget('election_operations_dashboard');
        // Also clear ward dashboard cache for this result's ward
        if ($result->pollingStation?->ward_id) {
            $ward = AdministrativeHierarchy::find($result->pollingStation->ward_id);
            if ($ward?->assigned_approver_id) {
                $approver = $ward->assigned_approver_id;
                Cache::forget("ward_dashboard_v3_{$approver}_{$ward->id}_{$result->election_id}");
                Cache::forget("ward_dashboard_v2_{$approver}_{$ward->id}_{$result->election_id}");
            }
        }
    }
}