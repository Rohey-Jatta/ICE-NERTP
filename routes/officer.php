<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        $user           = Auth::user();
        $station        = PollingStation::where('assigned_officer_id', $user->id)->first();
        $activeElection = Election::where('status', 'active')->latest()->first();

        $submissionStats = Result::where('submitted_by', $user->id)
            ->when($activeElection, fn($q) => $q->where('election_id', $activeElection->id))
            ->selectRaw(
                'COUNT(*) as total, '
                . 'SUM(CASE WHEN certification_status IN (?, ?, ?) THEN 1 ELSE 0 END) as pending, '
                . 'SUM(CASE WHEN certification_status IN (?, ?, ?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as certified, '
                . 'SUM(CASE WHEN certification_status = ? AND rejection_count > 0 THEN 1 ELSE 0 END) as rejected',
                [
                    Result::STATUS_SUBMITTED,
                    Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                    Result::STATUS_PENDING_WARD,
                    Result::STATUS_WARD_CERTIFIED,
                    Result::STATUS_PENDING_CONSTITUENCY,
                    Result::STATUS_CONSTITUENCY_CERTIFIED,
                    Result::STATUS_PENDING_ADMIN_AREA,
                    Result::STATUS_ADMIN_AREA_CERTIFIED,
                    Result::STATUS_PENDING_NATIONAL,
                    Result::STATUS_NATIONALLY_CERTIFIED,
                    Result::STATUS_SUBMITTED,
                ]
            )
            ->first();

        $pendingCount = (int) $submissionStats->pending;
        $certifiedCount = (int) $submissionStats->certified;
        $rejectedCount = (int) $submissionStats->rejected;

        $hasSubmitted = $station && $activeElection
            ? Result::where('polling_station_id', $station->id)
                ->where('election_id', $activeElection->id)
                ->whereNotIn('certification_status', [Result::STATUS_REJECTED])
                ->exists()
            : false;

        return Inertia::render('Officer/Dashboard', [
            'auth'    => ['user' => $user],
            'station' => $station ? [
                'id'                => $station->id,
                'name'              => $station->name,
                'code'              => $station->code,
                'registered_voters' => $station->registered_voters,
                'election_name'     => $activeElection->name ?? 'N/A',
            ] : null,
            'statistics' => [
                'totalSubmissions' => (int) $submissionStats->total,
                'pending'          => $pendingCount,
                'certified'        => $certifiedCount,
                'rejected'         => $rejectedCount,
            ],
            'hasSubmitted' => $hasSubmitted,
            'canSubmit'    => $station !== null && $activeElection !== null && !$hasSubmitted,
        ]);
    })->name('dashboard');

    // ── Result Submission Form ────────────────────────────────────────────────
    Route::get('/results/submit', function () {
        $user           = Auth::user();
        $station        = PollingStation::where('assigned_officer_id', $user->id)->first();
        // FIXED: Always find the active election — never rely on station->election_id
        $election       = Election::where('status', 'active')->latest()->first();

        if (!$station) {
            return redirect()->route('officer.dashboard')
                ->with('error', 'You have no polling station assigned. Contact the administrator.');
        }

        if (!$election) {
            return redirect()->route('officer.dashboard')
                ->with('error', 'No active election found. Contact the administrator.');
        }

        // A result that is NOT editable (already in pipeline for THIS active election)
        $existingResult = Result::where('polling_station_id', $station->id)
            ->where('election_id', $election->id)
            ->whereNotIn('certification_status', [Result::STATUS_SUBMITTED])
            ->where('rejection_count', 0)
            ->first();

        // A rejected result the officer can fix and resubmit
        $editableResult = Result::where('polling_station_id', $station->id)
            ->where('election_id', $election->id)
            ->where('submitted_by', $user->id)
            ->where('certification_status', Result::STATUS_SUBMITTED)
            ->where('rejection_count', '>', 0)
            ->latest('submitted_at')
            ->first();

        $candidates = Candidate::where('election_id', $election->id)
            ->with('politicalParty')
            ->where('is_active', true)
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name ?? $c->full_name,
                'party_name'  => $c->politicalParty->name ?? 'Independent',
                'party_abbr'  => $c->politicalParty->abbreviation ?? 'IND',
                'party_color' => $c->politicalParty->color ?? '#6b7280',
            ]);

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
            'election'       => [
                'id'   => $election->id,
                'name' => $election->name,
                'type' => $election->type,
            ],
            'candidates'     => $candidates,
            'editableResult' => $editableResult ? [
                'id'                      => $editableResult->id,
                'total_registered_voters' => $editableResult->total_registered_voters,
                'total_votes_cast'        => $editableResult->total_votes_cast,
                'valid_votes'             => $editableResult->valid_votes,
                'rejected_votes'          => $editableResult->rejected_votes,
                'last_rejection_reason'   => $editableResult->last_rejection_reason,
                'rejection_count'         => $editableResult->rejection_count,
            ] : null,
            'alreadySubmitted' => $existingResult !== null,
        ]);
    })->name('results.submit');

    // ── Submit / Resubmit Result ──────────────────────────────────────────────
    Route::post('/results/submit', function (Request $request) {
        $user = $request->user();

        if (!$user) {
            return back()->withErrors(['error' => 'Session expired. Please log in again.']);
        }

        $request->validate([
            'election_id'       => 'required|exists:elections,id',
            'registered_voters' => 'required|integer|min:1',
            'total_votes_cast'  => 'required|integer|min:0',
            'valid_votes'       => 'required|integer|min:0',
            'rejected_votes'    => 'required|integer|min:0',
            'photo'             => 'nullable|image|max:10240',
            'candidate_votes'   => 'required|array|min:1',
        ]);

        // Ensure the election being submitted to is still active
        $election = Election::where('id', $request->election_id)
            ->where('status', 'active')
            ->first();

        if (!$election) {
            return back()->withErrors(['error' => 'The selected election is no longer active.']);
        }

        $totalVotesCast   = (int) $request->total_votes_cast;
        $validVotes       = (int) $request->valid_votes;
        $rejectedVotes    = (int) $request->rejected_votes;
        $registeredVoters = (int) $request->registered_voters;

        if ($validVotes + $rejectedVotes !== $totalVotesCast) {
            return back()->withErrors([
                'total_votes_cast' => "Valid ({$validVotes}) + Rejected ({$rejectedVotes}) must equal Total Cast ({$totalVotesCast}).",
            ])->withInput();
        }

        if ($totalVotesCast > $registeredVoters) {
            return back()->withErrors([
                'total_votes_cast' => 'Total votes cast cannot exceed registered voters.',
            ])->withInput();
        }

        $candidateVotesRaw = $request->candidate_votes;
        $candidateVotesSum = array_sum(array_map('intval', array_values($candidateVotesRaw)));
        if ($candidateVotesSum !== $validVotes) {
            return back()->withErrors([
                'candidate_votes' => "Candidate votes sum ({$candidateVotesSum}) must equal valid votes ({$validVotes}).",
            ])->withInput();
        }

        $station = PollingStation::where('assigned_officer_id', $user->id)->first();
        if (!$station) {
            return back()->withErrors(['error' => 'No polling station assigned to your account.']);
        }

        $existingResult = Result::where('polling_station_id', $station->id)
            ->where('election_id', $election->id)
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
                $existingResult->update([
                    'user_id'                 => $user->id,
                    'total_registered_voters' => $registeredVoters,
                    'total_votes_cast'        => $totalVotesCast,
                    'valid_votes'             => $validVotes,
                    'rejected_votes'          => $rejectedVotes,
                    'result_sheet_photo_path' => $photoPath ?? $existingResult->result_sheet_photo_path,
                    'certification_status'    => Result::STATUS_SUBMITTED,
                    'submitted_at'            => now(),
                    'version'                 => $existingResult->version + 1,
                ]);

                $existingResult->candidateVotes()->delete();
                foreach ($candidateVotesRaw as $candidateId => $votes) {
                    ResultCandidateVote::create([
                        'result_id'    => $existingResult->id,
                        'candidate_id' => (int) $candidateId,
                        'election_id'  => $election->id,
                        'votes'        => (int) $votes,
                    ]);
                }

                \App\Jobs\ProcessResultSubmission::dispatch($existingResult->fresh());

                return redirect()->route('officer.submissions')
                    ->with('success', 'Result resubmitted successfully!');
            }

            $result = Result::create([
                'submission_uuid'         => \Illuminate\Support\Str::uuid(),
                'election_id'             => $election->id,
                'polling_station_id'      => $station->id,
                'user_id'                 => $user->id,
                'total_registered_voters' => $registeredVoters,
                'total_votes_cast'        => $totalVotesCast,
                'valid_votes'             => $validVotes,
                'rejected_votes'          => $rejectedVotes,
                'disputed_votes'          => 0,
                'result_sheet_photo_path' => $photoPath,
                'certification_status'    => Result::STATUS_SUBMITTED,
                'submitted_by'            => $user->id,
                'submitted_at'            => now(),
                'gps_validated'           => false,
            ]);

            foreach ($candidateVotesRaw as $candidateId => $votes) {
                ResultCandidateVote::create([
                    'result_id'    => $result->id,
                    'candidate_id' => (int) $candidateId,
                    'election_id'  => $election->id,
                    'votes'        => (int) $votes,
                ]);
            }

            \App\Jobs\ProcessResultSubmission::dispatch($result);

            return redirect()->route('officer.submissions')
                ->with('success', 'Results submitted successfully!');

        } catch (\Exception $e) {
            Log::error('Result submission failed', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id,
                'station' => $station->id,
            ]);
            return back()->withErrors(['error' => 'Submission failed: ' . $e->getMessage()])->withInput();
        }
    })->name('results.store');

    // ── My Submissions ────────────────────────────────────────────────────────
    Route::get('/submissions', function () {
        $user           = Auth::user();
        $station        = PollingStation::where('assigned_officer_id', $user->id)->first();
        $activeElection = Election::where('status', 'active')->latest()->first();

        $results = Result::where('submitted_by', $user->id)
            ->when($activeElection, fn($q) => $q->where('election_id', $activeElection->id))
            ->with(['pollingStation', 'candidateVotes.candidate.politicalParty'])
            ->latest('submitted_at')
            ->get()
            ->map(fn($r) => [
                'id'                      => $r->id,
                'polling_station_name'    => $r->pollingStation->name ?? 'Unknown Station',
                'polling_station_code'    => $r->pollingStation->code ?? '—',
                'submitted_at'            => $r->submitted_at?->format('Y-m-d H:i'),
                'certification_status'    => $r->certification_status,
                'total_registered_voters' => $r->total_registered_voters,
                'total_votes_cast'        => $r->total_votes_cast,
                'valid_votes'             => $r->valid_votes,
                'rejected_votes'          => $r->rejected_votes,
                'turnout'                 => $r->getTurnoutPercentage(),
                'rejection_count'         => $r->rejection_count,
                'last_rejection_reason'   => $r->last_rejection_reason,
                'photo_url'               => $r->result_sheet_photo_path
                    ? asset('storage/' . $r->result_sheet_photo_path)
                    : null,
                'is_editable'             => $r->certification_status === Result::STATUS_SUBMITTED
                    && $r->rejection_count > 0,
                'candidate_votes'         => $r->candidateVotes->map(fn($cv) => [
                    'candidate_name' => $cv->candidate->name ?? $cv->candidate->full_name ?? 'Unknown',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
                'version' => $r->version,
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