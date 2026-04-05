<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;

Route::middleware(['auth', 'role:polling-officer'])
    ->prefix('officer')
    ->name('officer.')
    ->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user    = Auth::user();
        $station = PollingStation::where('assigned_officer_id', $user->id)
            ->with('election')
            ->first();

        // Real submission statistics
        $submissions = Result::where('submitted_by', $user->id)
            ->orderByDesc('submitted_at')
            ->get();

        $pendingCount   = $submissions->whereIn('certification_status', [
            Result::STATUS_SUBMITTED,
            Result::STATUS_PENDING_PARTY_ACCEPTANCE,
            Result::STATUS_PENDING_WARD,
        ])->count();

        $certifiedCount = $submissions->whereIn('certification_status', [
            Result::STATUS_WARD_CERTIFIED,
            Result::STATUS_PENDING_CONSTITUENCY,
            Result::STATUS_CONSTITUENCY_CERTIFIED,
            Result::STATUS_PENDING_ADMIN_AREA,
            Result::STATUS_ADMIN_AREA_CERTIFIED,
            Result::STATUS_PENDING_NATIONAL,
            Result::STATUS_NATIONALLY_CERTIFIED,
        ])->count();

        $rejectedCount  = $submissions->where('certification_status', Result::STATUS_SUBMITTED)
            ->where('rejection_count', '>', 0)->count();

        // Has officer already submitted for this station/election?
        $hasSubmitted = $station
            ? Result::where('polling_station_id', $station->id)
                ->whereNotIn('certification_status', [Result::STATUS_REJECTED])
                ->exists()
            : false;

        return Inertia::render('Officer/Dashboard', [
            'auth'        => ['user' => $user],
            'station'     => $station ? [
                'id'                => $station->id,
                'name'              => $station->name,
                'code'              => $station->code,
                'registered_voters' => $station->registered_voters,
                'election_name'     => $station->election->name ?? 'N/A',
            ] : null,
            'statistics' => [
                'totalSubmissions' => $submissions->count(),
                'pending'          => $pendingCount,
                'certified'        => $certifiedCount,
                'rejected'         => $rejectedCount,
            ],
            'hasSubmitted' => $hasSubmitted,
            'canSubmit'    => $station !== null && !$hasSubmitted,
        ]);
    })->name('dashboard');

    // ── Result Submission Form ────────────────────────────────────────────────
    Route::get('/results/submit', function () {
        $user     = Auth::user();
        $station  = PollingStation::where('assigned_officer_id', $user->id)->first();
        $election = $station
            ? Election::where('id', $station->election_id)
                ->where('status', 'active')
                ->first()
            : Election::where('status', 'active')->first();

        if (!$station) {
            return redirect()->route('officer.dashboard')
                ->with('error', 'You have no polling station assigned. Contact the administrator.');
        }

        // Check if already submitted a non-rejected result
        $existingResult = Result::where('polling_station_id', $station->id)
            ->whereNotIn('certification_status', [Result::STATUS_SUBMITTED])
            ->where('rejection_count', 0)
            ->first();

        // Only allow editing if in submitted/rejected state
        $editableResult = Result::where('polling_station_id', $station->id)
            ->where('submitted_by', $user->id)
            ->where('certification_status', Result::STATUS_SUBMITTED)
            ->where('rejection_count', '>', 0)
            ->latest('submitted_at')
            ->first();

        $candidates = $election
            ? Candidate::where('election_id', $election->id)
                ->with('politicalParty')
                ->where('is_active', true)
                ->get()
                ->map(fn($c) => [
                    'id'         => $c->id,
                    'name'       => $c->name ?? $c->full_name,
                    'party_name' => $c->politicalParty->name ?? 'Independent',
                    'party_abbr' => $c->politicalParty->abbreviation ?? 'IND',
                    'party_color'=> $c->politicalParty->color ?? '#6b7280',
                ])
            : [];

        return Inertia::render('Officer/ResultSubmit', [
            'auth'           => ['user' => $user],
            'station'        => [
                'id'                => $station->id,
                'name'              => $station->name,
                'code'              => $station->code,
                'registered_voters' => $station->registered_voters,
                'latitude'          => $station->latitude,
                'longitude'         => $station->longitude,
            ],
            'election'       => $election ? [
                'id'   => $election->id,
                'name' => $election->name,
                'type' => $election->type,
            ] : null,
            'candidates'     => $candidates,
            'editableResult' => $editableResult ? [
                'id'                     => $editableResult->id,
                'total_registered_voters'=> $editableResult->total_registered_voters,
                'total_votes_cast'       => $editableResult->total_votes_cast,
                'valid_votes'            => $editableResult->valid_votes,
                'rejected_votes'         => $editableResult->rejected_votes,
                'last_rejection_reason'  => $editableResult->last_rejection_reason,
                'rejection_count'        => $editableResult->rejection_count,
            ] : null,
            'alreadySubmitted' => $existingResult !== null,
        ]);
    })->name('results.submit');

    // ── Submit / Resubmit Result ──────────────────────────────────────────────
    Route::post('/results/submit', function (Request $request) {
        $user = Auth::user();

        $request->validate([
            'election_id'       => 'required|exists:elections,id',
            'registered_voters' => 'required|integer|min:1',
            'total_votes_cast'  => 'required|integer|min:0',
            'valid_votes'       => 'required|integer|min:0',
            'rejected_votes'    => 'required|integer|min:0',
            'photo'             => 'nullable|image|max:10240',
            'candidate_votes'   => 'required|array|min:1',
        ]);

        // Validate arithmetic
        $calculatedTotal = (int)$request->valid_votes + (int)$request->rejected_votes;
        if ($calculatedTotal !== (int)$request->total_votes_cast) {
            return back()->withErrors([
                'total_votes_cast' => "Valid votes ({$request->valid_votes}) + Rejected votes ({$request->rejected_votes}) must equal Total votes cast ({$request->total_votes_cast}).",
            ])->withInput();
        }

        if ((int)$request->total_votes_cast > (int)$request->registered_voters) {
            return back()->withErrors([
                'total_votes_cast' => 'Total votes cast cannot exceed registered voters.',
            ])->withInput();
        }

        $candidateVotesSum = array_sum(array_values($request->candidate_votes));
        if ($candidateVotesSum !== (int)$request->valid_votes) {
            return back()->withErrors([
                'candidate_votes' => "Sum of candidate votes ({$candidateVotesSum}) must equal valid votes ({$request->valid_votes}).",
            ])->withInput();
        }

        $station = PollingStation::where('assigned_officer_id', $user->id)->first();
        if (!$station) {
            return back()->withErrors(['error' => 'No polling station assigned to your account.']);
        }

        // Check for an existing rejected result to update (resubmission)
        $existingResult = Result::where('polling_station_id', $station->id)
            ->where('submitted_by', $user->id)
            ->where('certification_status', Result::STATUS_SUBMITTED)
            ->where('rejection_count', '>', 0)
            ->latest('submitted_at')
            ->first();

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('result-photos', 'public');
        }

        try {
            if ($existingResult) {
                // Resubmission after rejection — update existing record
                $existingResult->update([
                    'total_registered_voters' => $request->registered_voters,
                    'total_votes_cast'        => $request->total_votes_cast,
                    'valid_votes'             => $request->valid_votes,
                    'rejected_votes'          => $request->rejected_votes,
                    'result_sheet_photo_path' => $photoPath ?? $existingResult->result_sheet_photo_path,
                    'certification_status'    => Result::STATUS_SUBMITTED,
                    'submitted_at'            => now(),
                    'version'                 => $existingResult->version + 1,
                ]);

                // Refresh candidate votes
                $existingResult->candidateVotes()->delete();
                foreach ($request->candidate_votes as $candidateId => $votes) {
                    ResultCandidateVote::create([
                        'result_id'    => $existingResult->id,
                        'candidate_id' => $candidateId,
                        'election_id'  => $request->election_id,
                        'votes'        => (int) $votes,
                    ]);
                }

                AuditLog::record(
                    action: 'result.resubmitted',
                    event: 'updated',
                    module: 'Results',
                    auditable: $existingResult,
                    extra: ['outcome' => 'success', 'station_code' => $station->code]
                );

                // Dispatch background job to process
                \App\Jobs\ProcessResultSubmission::dispatch($existingResult->fresh());

                return redirect()->route('officer.submissions')
                    ->with('success', 'Result resubmitted successfully! It will now go through the approval process.');
            }

            // Fresh submission
            $result = Result::create([
                'submission_uuid'         => Str::uuid(),
                'user_id'                 => $user->id,
                'election_id'             => $request->election_id,
                'polling_station_id'      => $station->id,
                'total_registered_voters' => $request->registered_voters,
                'total_votes_cast'        => $request->total_votes_cast,
                'valid_votes'             => $request->valid_votes,
                'rejected_votes'          => $request->rejected_votes,
                'disputed_votes'          => 0,
                'result_sheet_photo_path' => $photoPath,
                'certification_status'    => Result::STATUS_SUBMITTED,
                'submitted_by'            => $user->id,
                'submitted_at'            => now(),
                'gps_validated'           => false,
            ]);

            foreach ($request->candidate_votes as $candidateId => $votes) {
                ResultCandidateVote::create([
                    'result_id'    => $result->id,
                    'candidate_id' => $candidateId,
                    'election_id'  => $request->election_id,
                    'votes'        => (int) $votes,
                ]);
            }

            AuditLog::record(
                action: 'result.submitted',
                event: 'created',
                module: 'Results',
                auditable: $result,
                extra: ['outcome' => 'success', 'station_code' => $station->code, 'election_id' => $request->election_id]
            );

            // Dispatch background processing job
            \App\Jobs\ProcessResultSubmission::dispatch($result);

            return redirect()->route('officer.submissions')
                ->with('success', 'Results submitted successfully! They have entered the certification pipeline.');

        } catch (\Exception $e) {
            Log::error('Result submission failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to submit result: ' . $e->getMessage()])->withInput();
        }
    })->name('results.store');

    // ── My Submissions ────────────────────────────────────────────────────────
    Route::get('/submissions', function () {
        $user    = Auth::user();
        $station = PollingStation::where('assigned_officer_id', $user->id)->first();

        $results = Result::where('submitted_by', $user->id)
            ->with(['pollingStation', 'candidateVotes.candidate.politicalParty'])
            ->latest('submitted_at')
            ->get()
            ->map(fn($r) => [
                'id'                       => $r->id,
                'polling_station_name'     => $r->pollingStation->name ?? 'Unknown Station',
                'polling_station_code'     => $r->pollingStation->code ?? '—',
                'submitted_at'             => $r->submitted_at?->format('Y-m-d H:i'),
                'certification_status'     => $r->certification_status,
                'total_registered_voters'  => $r->total_registered_voters,
                'total_votes_cast'         => $r->total_votes_cast,
                'valid_votes'              => $r->valid_votes,
                'rejected_votes'           => $r->rejected_votes,
                'turnout'                  => $r->getTurnoutPercentage(),
                'rejection_count'          => $r->rejection_count,
                'last_rejection_reason'    => $r->last_rejection_reason,
                'photo_url'                => $r->result_sheet_photo_path
                    ? asset('storage/' . $r->result_sheet_photo_path)
                    : null,
                'is_editable'              => $r->certification_status === Result::STATUS_SUBMITTED
                    && $r->rejection_count > 0,
                'candidate_votes'          => $r->candidateVotes->map(fn($cv) => [
                    'candidate_name' => $cv->candidate->name ?? $cv->candidate->full_name ?? 'Unknown',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
                'version'                  => $r->version,
            ]);

        return Inertia::render('Officer/Submissions', [
            'auth'        => ['user' => $user],
            'submissions' => $results,
            'station'     => $station ? [
                'name' => $station->name,
                'code' => $station->code,
            ] : null,
        ]);
    })->name('submissions');
});