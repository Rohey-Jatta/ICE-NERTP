<?php

namespace App\Jobs;

use App\Models\Result;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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

            // Only process if still in initial SUBMITTED state
            if ($result->certification_status !== Result::STATUS_SUBMITTED) {
                Log::info('ProcessResultSubmission: result already advanced, skipping', [
                    'id'     => $result->id,
                    'status' => $result->certification_status,
                ]);
                return;
            }

            // Check if any ACTIVE party representatives are assigned to this polling station.
            // If none are assigned, skip party acceptance entirely and go straight to ward review.
            $hasPartyReps = DB::table('party_representative_polling_station as prps')
                ->join('party_representatives as pr', 'pr.id', '=', 'prps.party_representative_id')
                ->where('prps.polling_station_id', $result->polling_station_id)
                ->where('pr.is_active', true)
                ->exists();

            $nextStatus = $hasPartyReps
                ? Result::STATUS_PENDING_PARTY_ACCEPTANCE
                : Result::STATUS_PENDING_WARD;

            $result->update(['certification_status' => $nextStatus]);

            AuditLog::record(
                action: 'result.processing.completed',
                event:  'updated',
                module: 'Results',
                auditable: $result,
                extra: [
                    'outcome'                  => 'success',
                    'new_status'               => $nextStatus,
                    'skipped_party_acceptance' => !$hasPartyReps,
                ]
            );

            Log::info('ProcessResultSubmission: advanced', [
                'result_id'      => $result->id,
                'station_id'     => $result->polling_station_id,
                'new_status'     => $nextStatus,
                'has_party_reps' => $hasPartyReps,
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
