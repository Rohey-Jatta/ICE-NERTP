<?php

namespace App\Jobs;

use App\Models\Result;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessResultSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Result $result) {}

    public function handle(): void
    {
        try {
            $result = $this->result->fresh();

            if (!$result) {
                Log::warning('ProcessResultSubmission: result not found', ['id' => $this->result->id]);
                return;
            }

            // Only process if still in initial SUBMITTED state (not already advanced)
            if ($result->certification_status !== Result::STATUS_SUBMITTED) {
                Log::info('ProcessResultSubmission: result already advanced, skipping', [
                    'id'     => $result->id,
                    'status' => $result->certification_status,
                ]);
                return;
            }

            // Advance from SUBMITTED → PENDING_PARTY_ACCEPTANCE
            // This makes the result visible to party representatives
            $result->update([
                'certification_status' => Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                'processing_started_at' => now(),
            ]);

            AuditLog::record(
                action: 'result.processing.completed',
                event:  'updated',
                module: 'Results',
                auditable: $result,
                extra: [
                    'outcome'    => 'success',
                    'new_status' => Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                ]
            );

            Log::info('ProcessResultSubmission: advanced to PENDING_PARTY_ACCEPTANCE', [
                'result_id'  => $result->id,
                'station_id' => $result->polling_station_id,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessResultSubmission failed', [
                'result_id' => $this->result->id,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}