<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Result;
use App\Models\ResultCertification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CertificationWorkflowService - Sequential approval state machine.
 * 
 * From architecture: app/Services/CertificationWorkflowService.php
 * 
 * Workflow:
 * Submitted → Pending Party → Pending Ward → Ward Certified →
 * Pending Constituency → Constituency Certified → Pending Admin Area →
 * Admin Area Certified → Pending National → Nationally Certified
 * 
 * Rejection at any level returns to originating polling station.
 */
class CertificationWorkflowService
{
    // Certification levels in order
    const LEVELS = [
        Result::STATUS_PENDING_WARD => 'ward',
        Result::STATUS_PENDING_CONSTITUENCY => 'constituency',
        Result::STATUS_PENDING_ADMIN_AREA => 'admin_area',
        Result::STATUS_PENDING_NATIONAL => 'national',
    ];

    // Certified statuses
    const CERTIFIED_LEVELS = [
        'ward' => Result::STATUS_WARD_CERTIFIED,
        'constituency' => Result::STATUS_CONSTITUENCY_CERTIFIED,
        'admin_area' => Result::STATUS_ADMIN_AREA_CERTIFIED,
        'national' => Result::STATUS_NATIONALLY_CERTIFIED,
    ];

    /**
     * Approve result at a specific certification level.
     */
    public function approve(Result $result, User $approver, string $level, ?string $comments = null): bool
    {
        // Verify approver has permission for this level
        if (!$this->canApprove($approver, $level)) {
            throw new \Exception("User does not have permission to approve at {$level} level");
        }

        // Verify result is in the correct status for this level
        $expectedStatus = $this->getPendingStatus($level);
        if ($result->certification_status !== $expectedStatus) {
            throw new \Exception("Result is not pending approval at {$level} level");
        }

        DB::beginTransaction();
        try {
            // Create certification record
            $certification = ResultCertification::create([
                'result_id' => $result->id,
                'level' => $level,
                'approver_id' => $approver->id,
                'status' => 'approved',
                'comments' => $comments,
                'certified_at' => now(),
            ]);

            // Update result status to certified at this level
            $certifiedStatus = self::CERTIFIED_LEVELS[$level];
            $result->update([
                'certification_status' => $certifiedStatus,
            ]);

            // Auto-promote to next level
            $this->promoteToNextLevel($result);

            // Create version snapshot
            $this->createVersionSnapshot($result, $approver, "Approved at {$level} level");

            // Audit log
            AuditLog::record(
                action: "certification.{$level}.approved",
                event: 'updated',
                module: 'Certification',
                auditable: $result,
                extra: [
                    'level' => $level,
                    'approver_id' => $approver->id,
                    'outcome' => 'success',
                ]
            );

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject result at a specific level - returns to polling station.
     */
    public function reject(Result $result, User $approver, string $level, string $reason): bool
    {
        if (!$this->canApprove($approver, $level)) {
            throw new \Exception("User does not have permission to reject at {$level} level");
        }

        $expectedStatus = $this->getPendingStatus($level);
        if ($result->certification_status !== $expectedStatus) {
            throw new \Exception("Result is not pending approval at {$level} level");
        }

        DB::beginTransaction();
        try {
            // Create certification record with rejected status
            $certification = ResultCertification::create([
                'result_id' => $result->id,
                'level' => $level,
                'approver_id' => $approver->id,
                'status' => 'rejected',
                'comments' => $reason,
                'certified_at' => now(),
            ]);

            // Return to submitted status for re-submission
            $result->update([
                'certification_status' => Result::STATUS_SUBMITTED,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by' => $approver->id,
            ]);

            // Create version snapshot
            $this->createVersionSnapshot($result, $approver, "Rejected at {$level} level: {$reason}");

            // Audit log
            AuditLog::record(
                action: "certification.{$level}.rejected",
                event: 'updated',
                module: 'Certification',
                auditable: $result,
                extra: [
                    'level' => $level,
                    'approver_id' => $approver->id,
                    'reason' => $reason,
                    'outcome' => 'rejected',
                ]
            );

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Auto-promote result to next certification level.
     */
    private function promoteToNextLevel(Result $result): void
    {
        $nextStatus = match($result->certification_status) {
            Result::STATUS_WARD_CERTIFIED => Result::STATUS_PENDING_CONSTITUENCY,
            Result::STATUS_CONSTITUENCY_CERTIFIED => Result::STATUS_PENDING_ADMIN_AREA,
            Result::STATUS_ADMIN_AREA_CERTIFIED => Result::STATUS_PENDING_NATIONAL,
            Result::STATUS_NATIONALLY_CERTIFIED => null, // Final status
            default => null,
        };

        if ($nextStatus) {
            $result->update(['certification_status' => $nextStatus]);
        }
    }

    /**
     * Check if user can approve at a specific level.
     */
    private function canApprove(User $user, string $level): bool
    {
        return match($level) {
            'ward' => $user->hasRole('ward-approver'),
            'constituency' => $user->hasRole('constituency-approver'),
            'admin_area' => $user->hasRole('admin-area-approver'),
            'national' => $user->hasRole('iec-chairman'),
            default => false,
        };
    }

    /**
     * Get the pending status for a certification level.
     */
    private function getPendingStatus(string $level): string
    {
        return match($level) {
            'ward' => Result::STATUS_PENDING_WARD,
            'constituency' => Result::STATUS_PENDING_CONSTITUENCY,
            'admin_area' => Result::STATUS_PENDING_ADMIN_AREA,
            'national' => Result::STATUS_PENDING_NATIONAL,
            default => throw new \Exception("Invalid certification level: {$level}"),
        };
    }

    /**
     * Create version snapshot after certification action.
     */
    private function createVersionSnapshot(Result $result, User $user, string $reason): void
    {
        $result->versions()->create([
            'version_number' => $result->versions()->count() + 1,
            'result_snapshot' => $result->toArray(),
            'votes_snapshot' => $result->candidateVotes->toArray(),
            'changed_by' => $user->id,
            'change_reason' => $reason,
            'certification_status_at_version' => $result->certification_status,
        ]);
    }

    /**
     * Get approval queue for a specific level.
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

        // Filter by approver's jurisdiction
        if ($level === 'ward' && $approver->hasRole('ward-approver')) {
            // Ward approver sees only their ward
            $wardId = $approver->ward_id; // Assuming this exists
            if ($wardId) {
                $query->whereHas('pollingStation', fn($q) => $q->where('ward_id', $wardId));
            }
        }

        if ($level === 'constituency' && $approver->hasRole('constituency-approver')) {
            // Constituency approver sees their constituency
            $constituencyId = $approver->constituency_id;
            if ($constituencyId) {
                $query->whereHas('pollingStation.ward', fn($q) => $q->where('constituency_id', $constituencyId));
            }
        }

        // Additional filters
        if (isset($filters['station_code'])) {
            $query->whereHas('pollingStation', fn($q) => $q->where('code', 'like', "%{$filters['station_code']}%"));
        }

        if (isset($filters['submitted_after'])) {
            $query->where('submitted_at', '>=', $filters['submitted_after']);
        }

        return $query->orderBy('submitted_at', 'asc')->paginate(20);
    }
}
