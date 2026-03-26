<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Result;
use App\Models\ResultCertification;
use App\Models\User;
use App\Models\AdministrativeHierarchy;
use Illuminate\Support\Facades\DB;

/**
 * CertificationWorkflowService — Sequential approval state machine.
 *
 * Workflow:
 * Submitted → Pending Party → Pending Ward → Ward Certified →
 * Pending Constituency → Constituency Certified → Pending Admin Area →
 * Admin Area Certified → Pending National → Nationally Certified
 */
class CertificationWorkflowService
{
    const CERTIFIED_LEVELS = [
        'ward'         => Result::STATUS_WARD_CERTIFIED,
        'constituency' => Result::STATUS_CONSTITUENCY_CERTIFIED,
        'admin_area'   => Result::STATUS_ADMIN_AREA_CERTIFIED,
        'national'     => Result::STATUS_NATIONALLY_CERTIFIED,
    ];

    /**
     * Approve a result at a specific certification level.
     */
    public function approve(Result $result, User $approver, string $level, ?string $comments = null): bool
    {
        if (!$this->canApprove($approver, $level)) {
            throw new \Exception("User does not have permission to approve at {$level} level.");
        }

        $expectedStatus = $this->getPendingStatus($level);
        if ($result->certification_status !== $expectedStatus) {
            throw new \Exception("Result is not pending approval at {$level} level. Current status: {$result->certification_status}");
        }

        DB::beginTransaction();
        try {
            // Find the approver's hierarchy node
            $levelMap = [
                'ward'         => 'ward',
                'constituency' => 'constituency',
                'admin_area'   => 'admin_area',
                'national'     => 'national',
            ];
            $node = AdministrativeHierarchy::where('assigned_approver_id', $approver->id)
                ->where('level', $levelMap[$level])
                ->first();

            // Record the certification decision
            ResultCertification::create([
                'result_id'             => $result->id,
                'certification_level'   => $level,          // correct column name
                'hierarchy_node_id'     => $node?->id ?? $result->pollingStation?->ward_id ?? 1,
                'approver_id'           => $approver->id,
                'status'                => 'approved',
                'comments'              => $comments,
                'assigned_at'           => now(),
                'decided_at'            => now(),           // correct column name
            ]);

            // Transition to certified status for this level
            $certifiedStatus = self::CERTIFIED_LEVELS[$level];
            $result->update(['certification_status' => $certifiedStatus]);

            // Auto-promote to next pending level
            $this->promoteToNextLevel($result);

            // Append a version snapshot
            $this->createVersionSnapshot($result, $approver, "Approved at {$level} level");

            AuditLog::record(
                action:    "certification.{$level}.approved",
                event:     'updated',
                module:    'Certification',
                auditable: $result,
                extra:     ['level' => $level, 'outcome' => 'success']
            );

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject a result at a specific level — returns it to the polling station.
     */
    public function reject(Result $result, User $approver, string $level, string $reason): bool
    {
        if (!$this->canApprove($approver, $level)) {
            throw new \Exception("User does not have permission to reject at {$level} level.");
        }

        $expectedStatus = $this->getPendingStatus($level);
        if ($result->certification_status !== $expectedStatus) {
            throw new \Exception("Result is not pending approval at {$level} level.");
        }

        DB::beginTransaction();
        try {
            $node = AdministrativeHierarchy::where('assigned_approver_id', $approver->id)
                ->first();

            ResultCertification::create([
                'result_id'           => $result->id,
                'certification_level' => $level,
                'hierarchy_node_id'   => $node?->id ?? $result->pollingStation?->ward_id ?? 1,
                'approver_id'         => $approver->id,
                'status'              => 'rejected',
                'comments'            => $reason,
                'assigned_at'         => now(),
                'decided_at'          => now(),
            ]);

            // Return to submitted status so the officer can re-submit
            $result->update([
                'certification_status' => Result::STATUS_SUBMITTED,
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
                extra:     ['level' => $level, 'reason' => $reason, 'outcome' => 'rejected']
            );

            DB::commit();
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

    // ── Private helpers ───────────────────────────────────────────────

    private function promoteToNextLevel(Result $result): void
    {
        $next = match ($result->certification_status) {
            Result::STATUS_WARD_CERTIFIED         => Result::STATUS_PENDING_CONSTITUENCY,
            Result::STATUS_CONSTITUENCY_CERTIFIED => Result::STATUS_PENDING_ADMIN_AREA,
            Result::STATUS_ADMIN_AREA_CERTIFIED   => Result::STATUS_PENDING_NATIONAL,
            default                                => null,
        };

        if ($next) {
            $result->update(['certification_status' => $next]);
        }
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
            // Non-fatal — don't fail the whole certification over a snapshot error
            \Illuminate\Support\Facades\Log::warning('[CertificationWorkflow] version snapshot failed: ' . $e->getMessage());
        }
    }
}
