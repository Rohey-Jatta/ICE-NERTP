<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessResultSubmission;
use App\Models\AuditLog;
use App\Models\PollingStation;
use App\Models\Result;
use App\Services\PhotoStorageService;
use App\Services\ResultValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ResultSubmissionController - API for polling officers to submit results.
 *
 * From architecture: app/Http/Controllers/Api/ResultSubmissionController.php
 *
 * Flow:
 * 1. Validate GPS (handled by EnsureGpsValid middleware)
 * 2. Validate vote counts (ResultValidationService)
 * 3. Store photo (PhotoStorageService)
 * 4. Create Result record
 * 5. Create candidate votes
 * 6. Dispatch background job
 * 7. Return success
 */
class ResultSubmissionController extends Controller
{
    public function __construct(
        private readonly ResultValidationService $validationService,
        private readonly PhotoStorageService $photoService,
    ) {}

    /**
     * Submit result from polling station.
     * Route: POST /api/results/submit
     * Middleware: auth:sanctum, role:polling-officer, gps
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'submission_uuid' => ['required', 'uuid'],
            'polling_station_id' => ['required', 'exists:polling_stations,id'],
            'election_id' => ['required', 'exists:elections,id'],
            'total_registered_voters' => ['required', 'integer', 'min:1'],
            'total_votes_cast' => ['required', 'integer', 'min:0'],
            'valid_votes' => ['required', 'integer', 'min:0'],
            'rejected_votes' => ['required', 'integer', 'min:0'],
            'disputed_votes' => ['nullable', 'integer', 'min:0'],
            'candidate_votes' => ['required', 'array', 'min:1'],
            'candidate_votes.*.candidate_id' => ['required', 'exists:candidates,id'],
            'candidate_votes.*.votes' => ['required', 'integer', 'min:0'],
            'result_sheet_photo' => ['required', 'image', 'max:10240'], // 10MB max
            'submitted_latitude' => ['required', 'numeric'],
            'submitted_longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric'],
        ]);

        // Check for duplicate submission (idempotency)
        $existing = Result::where('submission_uuid', $validated['submission_uuid'])->first();
        if ($existing) {
            return response()->json([
                'message' => 'Result already submitted.',
                'result_id' => $existing->id,
                'status' => $existing->certification_status,
            ], 200);
        }

        // Get polling station
        $station = PollingStation::findOrFail($validated['polling_station_id']);

        // Verify officer is assigned to this station
        if ($station->assigned_officer_id !== $request->user()->id) {
            AuditLog::record(
                action: 'result.submission.unauthorized',
                event: 'blocked',
                module: 'Results',
                extra: [
                    'outcome' => 'blocked',
                    'failure_reason' => 'Officer not assigned to station',
                    'station_id' => $station->id,
                ]
            );

            return response()->json([
                'message' => 'You are not assigned to this polling station.',
            ], 403);
        }

        // Validate submission data
        $validation = $this->validationService->validateSubmission($validated, $station);
        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validation['errors'],
            ], 422);
        }

        // Detect anomalies (warnings only, don't block)
        $warnings = $this->validationService->detectAnomalies($validated, $station);

        // Store photo
        $photoData = $this->photoService->storeResultPhoto(
            $request->file('result_sheet_photo'),
            $validated['election_id'],
            $station->code,
            $validated['submission_uuid']
        );

        // Create result in transaction
        DB::beginTransaction();
        try {
            $result = Result::create([
                'polling_station_id' => $validated['polling_station_id'],
                'election_id' => $validated['election_id'],
                'submission_uuid' => $validated['submission_uuid'],
                'total_registered_voters' => $validated['total_registered_voters'],
                'total_votes_cast' => $validated['total_votes_cast'],
                'valid_votes' => $validated['valid_votes'],
                'rejected_votes' => $validated['rejected_votes'],
                'disputed_votes' => $validated['disputed_votes'] ?? 0,
                'result_sheet_photo_path' => $photoData['path'],
                'result_sheet_photo_hash' => $photoData['hash'],
                'submitted_latitude' => $validated['submitted_latitude'],
                'submitted_longitude' => $validated['submitted_longitude'],
                'gps_accuracy_meters' => $validated['gps_accuracy_meters'],
                'gps_validated' => true, // Set by GPS middleware
                'certification_status' => Result::STATUS_SUBMITTED,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'submitted_offline' => $request->boolean('was_offline', false),
                'offline_queued_at' => $request->input('queued_at'),
                'version' => 1,
            ]);

            // Create candidate votes
            foreach ($validated['candidate_votes'] as $cv) {
                $result->candidateVotes()->create([
                    'candidate_id' => $cv['candidate_id'],
                    'election_id' => $validated['election_id'],
                    'votes' => $cv['votes'],
                ]);
            }

            DB::commit();

            // Dispatch background processing job
            ProcessResultSubmission::dispatch($result);

            // Audit log
            AuditLog::record(
                action: 'result.submitted',
                event: 'created',
                module: 'Results',
                auditable: $result,
                extra: [
                    'election_id' => $result->election_id,
                    'station_code' => $station->code,
                    'outcome' => 'success',
                    'warnings' => $warnings,
                    'latitude' => $validated['submitted_latitude'],
                    'longitude' => $validated['submitted_longitude'],
                ]
            );

            return response()->json([
                'message' => 'Result submitted successfully.',
                'result_id' => $result->id,
                'status' => $result->certification_status,
                'warnings' => $warnings,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded photo on failure
            if (isset($photoData['path'])) {
                $this->photoService->deletePhoto($photoData['path']);
            }

            AuditLog::record(
                action: 'result.submission.failed',
                event: 'failure',
                module: 'Results',
                extra: [
                    'outcome' => 'failure',
                    'failure_reason' => $e->getMessage(),
                ]
            );

            return response()->json([
                'message' => 'Failed to submit result. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get officer's submitted results.
     * Route: GET /api/results/my-submissions
     */
    public function mySubmissions(Request $request): JsonResponse
    {
        $results = Result::with(['pollingStation', 'candidateVotes.candidate'])
            ->where('submitted_by', $request->user()->id)
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json([
            'results' => $results,
        ]);
    }
}
