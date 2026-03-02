<?php

namespace App\Http\Controllers;

use App\Models\Result;
use App\Services\CertificationWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * ApprovalController - Certification workflow for approvers.
 * 
 * From architecture: Sequential approval at Ward/Constituency/Admin Area/National levels.
 * 
 * Handles approval queues and approve/reject actions.
 */
class ApprovalController extends Controller
{
    public function __construct(
        private readonly CertificationWorkflowService $certificationService
    ) {}

    /**
     * Show approval queue for the approver's level.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Determine user's approval level
        $level = $this->getUserLevel($user);
        
        if (!$level) {
            abort(403, 'You do not have approval permissions');
        }

        $filters = $request->only(['station_code', 'submitted_after']);
        $queue = $this->certificationService->getApprovalQueue($level, $user, $filters);

        return Inertia::render('IEC/ApprovalQueue', [
            'level' => $level,
            'queue' => $queue,
            'filters' => $filters,
        ]);
    }

    /**
     * Approve a result.
     * POST /api/approval/approve
     */
    public function approve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result_id' => ['required', 'exists:results,id'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = Result::findOrFail($validated['result_id']);
        $user = $request->user();
        $level = $this->getUserLevel($user);

        try {
            $this->certificationService->approve(
                $result,
                $user,
                $level,
                $validated['comments'] ?? null
            );

            return response()->json([
                'message' => 'Result approved successfully',
                'result' => $result->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject a result.
     * POST /api/approval/reject
     */
    public function reject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result_id' => ['required', 'exists:results,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $result = Result::findOrFail($validated['result_id']);
        $user = $request->user();
        $level = $this->getUserLevel($user);

        try {
            $this->certificationService->reject(
                $result,
                $user,
                $level,
                $validated['reason']
            );

            return response()->json([
                'message' => 'Result rejected and returned to polling station',
                'result' => $result->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get single result details for review.
     */
    public function show(int $resultId): JsonResponse
    {
        $result = Result::with([
            'pollingStation',
            'candidateVotes.candidate.politicalParty',
            'partyAcceptances.politicalParty',
            'certifications.approver',
            'versions',
        ])->findOrFail($resultId);

        return response()->json([
            'result' => $result,
        ]);
    }

    private function getUserLevel($user): ?string
    {
        if ($user->hasRole('ward-approver')) return 'ward';
        if ($user->hasRole('constituency-approver')) return 'constituency';
        if ($user->hasRole('admin-area-approver')) return 'admin_area';
        if ($user->hasRole('iec-chairman')) return 'national';
        return null;
    }
}
