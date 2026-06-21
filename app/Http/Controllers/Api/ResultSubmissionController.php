<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\NoCurrentElectionException;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessResultSubmission;
use App\Models\AuditLog;
use App\Models\PollingStation;
use App\Models\Result;
use App\Services\CurrentElectionResolver;
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
 * 1. Resolve the current operational election (CurrentElectionResolver)
 * 2. Validate GPS (handled by EnsureGpsValid middleware)
 * 3. Validate vote counts (ResultValidationService)
 * 4. Store photo (PhotoStorageService)
 * 5. Create Result record
 * 6. Create candidate votes
 * 7. Dispatch background job
 * 8. Return success
 *
 * IMPORTANT: election_id is no longer trusted from the client. Per the
 * polling-station election-assignment refactor, the submission ALWAYS
 * validates against whatever election the CurrentElectionResolver
 * resolves to at the moment of submission — never the client-supplied
 * value, and never polling_stations.election_id (which is now just
 * historical metadata).
 */
class ResultSubmissionController extends Controller
{
    public function __construct(
        private readonly ResultValidationService $validationService,
        private readonly PhotoStorageService $photoService,
        private readonly CurrentElectionResolver $electionResolver,
    ) {}

    /**
     * Submit result from polling station.
     * Route: POST /api/results/submit
     * Middleware: auth:sanctum, role:polling-officer, gps
     */
    public function submit(Request $request): JsonResponse
    {
        // Resolve the current election FIRST. If none qualifies, block the
        // entire operation per business rule #8 — there is nothing valid
        // to submit a result against.
        try {
            $currentElection = $this->electionResolver->current();
        } catch (NoCurrentElectionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'NO_CURRENT_ELECTION',
            ], 409);
        }

        $validated = $request->validate([
            'submission_uuid' => ['required', 'uuid'],
            'polling_station_id' => ['required', 'exists:polling_stations,id'],
            // election_id is still accepted from the client for backward
            // compatibility with existing frontend payloads, but it is
            // IGNORED for authorization purposes — see the check below.
            'election_id' => ['nullable', 'integer'],
            'total_registered_voters' => ['required', 'integer', 'min:1'],
            'total_votes_cast' => ['required', 'integer', 'min:0'],
            'valid_votes' => ['required', 'integer', 'min:0'],
            'rejected_votes' => ['required', 'integer', 'min:0'],
            'disputed_votes' => ['nullable', 'integer', 'min:0'],
            'candidate_votes' => ['required', 'array', 'min:1'],
            'candidate_votes.*.candidate_id' => ['required', 'exists:candidates,id'],
            'candidate_votes.*.votes' => ['required', 'integer', 'min:0'],
            'result_sheet_photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'], // 10MB max
            'submitted_latitude' => ['required', 'numeric', 'between:-90,90'],
            'submitted_longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        // If the client sent an election_id that does NOT match the
        // current operational election, reject outright. This is the
        // "always validate against the resolver's current election"
        // behavior — clients can no longer submit against a stale or
        // arbitrary election_id.
        if (!empty($validated['election_id']) && (int) $validated['election_id'] !== $currentElection->id) {
            AuditLog::record(
                action: 'result.submission.stale_election_rejected',
                event: 'blocked',
                module: 'Results',
                extra: [
                    'outcome'         => 'blocked',
                    'failure_reason'  => 'Submitted election_id does not match current operational election',
                    'submitted_election_id' => $validated['election_id'],
                    'current_election_id'   => $currentElection->id,
                ]
            );

            return response()->json([
                'message' => 'This result was prepared against an election that is no longer current. Please refresh and resubmit.',
                'code'    => 'STALE_ELECTION_CONTEXT',
            ], 409);
        }

        // Always use the resolver's election_id going forward — never the
        // client-supplied value.
        $electionId = $currentElection->id;

        // Check for duplicate submission (idempotency)
        $existing = Result::where('submission_uuid', $validated['submission_uuid'])->first();
        if ($existing) {
            return response()->json([
                'message' => 'Result already submitted.',
                'result_id' => $existing->id,
                'status' => $existing->certification_status,
            ], 200);
        }

        // Get polling station — no longer filtered by election_id at all,
        // since stations are not statically owned by an election.
        $station = PollingStation::findOrFail($validated['polling_station_id']);

        if (!$station->is_active) {
            return response()->json([
                'message' => 'This polling station is not active.',
            ], 422);
        }

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

        $activeSubmissionExists = Result::where('polling_station_id', $station->id)
            ->where('election_id', $electionId)
            ->where('rejection_count', 0)
            ->exists();

        if ($activeSubmissionExists) {
            return response()->json([
                'message' => 'A submission for this polling station is already in progress.',
            ], 409);
        }

        // Validate submission data
        $submissionForValidation = array_merge($validated, ['election_id' => $electionId]);
        $validation = $this->validationService->validateSubmission($submissionForValidation, $station);
        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validation['errors'],
            ], 422);
        }

        // Detect anomalies (warnings only, don't block)
        $warnings = $this->validationService->detectAnomalies($submissionForValidation, $station);

        // Store photo
        $photoData = $this->photoService->storeResultPhoto(
            $request->file('result_sheet_photo'),
            $electionId,
            $station->code,
            $validated['submission_uuid']
        );

        // Create result in transaction
        DB::beginTransaction();
        try {
            $result = Result::create([
                'polling_station_id' => $validated['polling_station_id'],
                'election_id' => $electionId,
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
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'submitted_offline' => $request->boolean('was_offline', false),
                'offline_queued_at' => $request->input('queued_at'),
                'version' => 1,
            ]);

            $result->forceFill(['certification_status' => Result::STATUS_SUBMITTED])->save();

            // Create candidate votes
            foreach ($validated['candidate_votes'] as $cv) {
                $result->candidateVotes()->create([
                    'candidate_id' => $cv['candidate_id'],
                    'election_id' => $electionId,
                    'votes' => $cv['votes'],
                ]);
            }

            // Update the station's "last seen election" marker now that it
            // has a confirmed submission under this election.
            $station->markSeenUnder($currentElection);

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
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $results = Result::with(['pollingStation', 'candidateVotes.candidate'])
            ->where('submitted_by', $request->user()->id)
            ->orderByDesc('submitted_at')
            ->paginate($perPage);

        return response()->json($results);
    }
}