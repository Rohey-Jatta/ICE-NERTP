<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Result;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessResultSubmission - Background job for post-submission tasks.
 *
 * From architecture: app/Jobs/ProcessResultSubmission.php
 *
 * Tasks:
 * 1. Verify photo upload completed
 * 2. Generate result version snapshot
 * 3. Trigger aggregation job (if certified)
 * 4. Send notifications to party reps
 */
class ProcessResultSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public Result $result
    ) {}

    public function handle(): void
    {
        Log::info("Processing result submission", [
            'result_id' => $this->result->id,
            'station_code' => $this->result->pollingStation->code,
        ]);

        // Create initial version snapshot
        $this->createVersionSnapshot();

        // Transition to pending party acceptance if required
        $this->transitionToPendingAcceptance();

        // Audit log
        AuditLog::record(
            action: 'result.processing.completed',
            event: 'updated',
            module: 'Results',
            auditable: $this->result,
            extra: [
                'election_id' => $this->result->election_id,
                'outcome' => 'success',
            ]
        );

        Log::info("Result processing completed", ['result_id' => $this->result->id]);
    }

    private function createVersionSnapshot(): void
    {
        $this->result->versions()->create([
            'version_number' => 1,
            'result_snapshot' => $this->result->toArray(),
            'votes_snapshot' => $this->result->candidateVotes->toArray(),
            'changed_by' => $this->result->submitted_by,
            'change_reason' => 'initial_submission',
            'certification_status_at_version' => $this->result->certification_status,
        ]);
    }

    private function transitionToPendingAcceptance(): void
    {
        if ($this->result->election->requires_party_acceptance) {
            $this->result->update([
                'certification_status' => Result::STATUS_PENDING_PARTY_ACCEPTANCE,
            ]);

            // TODO: Send notification to party reps assigned to this station
            Log::info("Result transitioned to pending party acceptance", [
                'result_id' => $this->result->id,
            ]);
        } else {
            // Skip party acceptance, go straight to ward
            $this->result->update([
                'certification_status' => Result::STATUS_PENDING_WARD,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Result processing failed", [
            'result_id' => $this->result->id,
            'error' => $exception->getMessage(),
        ]);

        AuditLog::record(
            action: 'result.processing.failed',
            event: 'failure',
            module: 'Results',
            auditable: $this->result,
            extra: [
                'outcome' => 'failure',
                'failure_reason' => $exception->getMessage(),
            ]
        );
    }
}
