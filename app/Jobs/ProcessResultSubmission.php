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

            if ($result->certification_status !== Result::STATUS_SUBMITTED) {
                Log::info('ProcessResultSubmission: result already advanced, skipping', [
                    'id'     => $result->id,
                    'status' => $result->certification_status,
                ]);
                return;
            }

            // PARALLEL WORKFLOW:
            // Ward Approvers and Party Representatives now work simultaneously.
            // Results go directly to PENDING_WARD so ward approvers get immediate access.
            // Party reps can still review and respond independently — their input is
            // recorded as informational context and does NOT block ward certification.
            $result->forceFill(['certification_status' => Result::STATUS_PENDING_WARD])->save();

            AuditLog::record(
                action: 'result.processing.completed',
                event:  'updated',
                module: 'Results',
                auditable: $result,
                extra: [
                    'outcome'    => 'success',
                    'new_status' => Result::STATUS_PENDING_WARD,
                    'workflow'   => 'parallel',
                ]
            );

            Log::info('ProcessResultSubmission: advanced to pending_ward (parallel workflow)', [
                'result_id'  => $result->id,
                'station_id' => $result->polling_station_id,
                'new_status' => Result::STATUS_PENDING_WARD,
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
