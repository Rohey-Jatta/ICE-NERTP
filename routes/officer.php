<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;

Route::middleware(['auth', 'role:polling-officer'])
    ->prefix('officer')
    ->name('officer.')
    ->group(function () {

    Route::get('/dashboard', function () {
        $user    = Auth::user();
        $station = PollingStation::where('assigned_officer_id', $user->id)->first();
        return Inertia::render('Officer/Dashboard', [
            'auth'        => ['user' => $user],
            'station'     => $station,
            'submissions' => [],
        ]);
    })->name('dashboard');

    Route::get('/results/submit', function () {
        $election = Election::where('status', 'active')->first();
        return Inertia::render('Officer/ResultSubmit', [
            'auth'       => ['user' => Auth::user()],
            'candidates' => $election
                ? Candidate::where('election_id', $election->id)->with('politicalParty')->get()
                : [],
            'election'   => $election,
        ]);
    })->name('results.submit');

    Route::post('/results/submit', function (Request $request) {
        $request->validate([
            'election_id'       => 'required|exists:elections,id',
            'registered_voters' => 'required|integer|min:0',
            'total_votes_cast'  => 'required|integer|min:0',
            'valid_votes'       => 'required|integer|min:0',
            'rejected_votes'    => 'required|integer|min:0',
            'photo'             => 'required|image|max:10240',
            'candidate_votes'   => 'required|array',
        ]);

        $station = PollingStation::where('assigned_officer_id', Auth::id())->first();
        if (!$station) {
            return back()->withErrors(['error' => 'No polling station assigned to your account.']);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('result-photos', 'public');
        }

        try {
            $result = Result::create([
                'submission_uuid'         => Str::uuid(),
                'user_id'                 => Auth::id(),
                'election_id'             => $request->election_id,
                'polling_station_id'      => $station->id,
                'total_registered_voters' => $request->registered_voters,
                'total_votes_cast'        => $request->total_votes_cast,
                'valid_votes'             => $request->valid_votes,
                'rejected_votes'          => $request->rejected_votes,
                'disputed_votes'          => 0,
                'result_sheet_photo_path' => $photoPath,
                'certification_status'    => Result::STATUS_SUBMITTED,
                'submitted_by'            => Auth::id(),
                'submitted_at'            => now(),
            ]);

            foreach ($request->candidate_votes as $candidateId => $votes) {
                ResultCandidateVote::create([
                    'result_id'    => $result->id,
                    'candidate_id' => $candidateId,
                    'election_id'  => $request->election_id,
                    'votes'        => (int) $votes,
                ]);
            }

            return redirect()->route('officer.submissions')->with('success', 'Results submitted successfully!');
        } catch (\Exception $e) {
            Log::error('Result submission failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to submit: ' . $e->getMessage()]);
        }
    })->name('results.store');

    Route::get('/submissions', function () {
        $results = Result::where('submitted_by', Auth::id())
            ->with('pollingStation')
            ->latest('submitted_at')
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'polling_station'=> $r->pollingStation->name ?? 'Unknown Station',
                'submitted_at'   => $r->submitted_at?->format('Y-m-d H:i'),
                'status'         => $r->certification_status,
                'total_votes'    => $r->total_votes_cast,
                'turnout'        => $r->getTurnoutPercentage(),
                'rejected_votes' => $r->rejected_votes,
            ]);
        return Inertia::render('Officer/Submissions', [
            'auth'        => ['user' => Auth::user()],
            'submissions' => $results,
        ]);
    })->name('submissions');
});
