<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;

Route::middleware(['auth', 'role:iec-chairman'])
    ->prefix('chairman')
    ->name('chairman.')
    ->group(function () {

    Route::get('/dashboard', function () {
        $pending = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)->count();
        return Inertia::render('Chairman/Dashboard', [
            'auth'            => ['user' => Auth::user()],
            'pendingNational' => $pending,
            'statistics'      => [
                'nationallyCertified' => Result::where('certification_status', Result::STATUS_NATIONALLY_CERTIFIED)->count(),
                'totalStations'       => PollingStation::count(),
                'totalVoters'         => PollingStation::sum('registered_voters'),
                'nationalProgress'    => 0,
            ],
            'recentActivity' => [],
        ]);
    })->name('dashboard');

    Route::get('/national-queue', function () {
        $results = Result::where('certification_status', Result::STATUS_PENDING_NATIONAL)
            ->with(['pollingStation', 'candidateVotes'])
            ->get()
            ->map(fn($r) => [
                'id'             => $r->id,
                'area'           => $r->pollingStation->name ?? 'Unknown',
                'votes'          => $r->total_votes_cast,
                'progress'       => 100,
                'constituencies' => 1,
                'certified_at'   => $r->updated_at?->format('Y-m-d H:i'),
            ]);
        return Inertia::render('Chairman/NationalQueue', [
            'auth'             => ['user' => Auth::user()],
            'adminAreaResults' => $results,
        ]);
    })->name('national-queue');

    Route::post('/certify-national/{result}', function (Result $result) {
        if ($result->certification_status !== Result::STATUS_PENDING_NATIONAL) {
            return back()->withErrors(['error' => 'Not pending national certification.']);
        }
        $result->update([
            'certification_status'    => Result::STATUS_NATIONALLY_CERTIFIED,
            'nationally_certified_at' => now(),
        ]);
        AuditLog::record(action: 'certification.national.approved', event: 'updated', module: 'Certification', auditable: $result);
        return back()->with('success', 'Result nationally certified!');
    })->name('certify-national');

    Route::post('/reject/{result}', function (Result $result, Request $request) {
        if ($result->certification_status !== Result::STATUS_PENDING_NATIONAL) {
            return back()->withErrors(['error' => 'Not pending national approval.']);
        }
        $result->update([
            'certification_status'  => Result::STATUS_PENDING_ADMIN_AREA,
            'last_rejection_reason' => $request->input('comments', 'Rejected at national level'),
            'last_rejected_by'      => Auth::id(),
            'last_rejected_at'      => now(),
        ]);
        return back()->with('success', 'Result returned to admin area level.');
    })->name('reject');

    Route::get('/all-results', fn() => Inertia::render('Chairman/AllResults', [
        'auth'    => ['user' => Auth::user()],
        'results' => [],
    ]))->name('all-results');

    Route::get('/analytics', fn() => Inertia::render('Chairman/Analytics', [
        'auth'              => ['user' => Auth::user()],
        'nationalStats'     => [],
        'regionalBreakdown' => [],
    ]))->name('analytics');

    Route::get('/publish', fn() => Inertia::render('Chairman/Publish', [
        'auth'           => ['user' => Auth::user()],
        'readinessCheck' => ['allCertified' => false, 'partyAcceptances' => false, 'auditComplete' => false],
        'summary'        => [],
    ]))->name('publish');

    Route::post('/publish-results', function () {
        $election = Election::where('status', 'active')->first();
        if (!$election) {
            return back()->withErrors(['error' => 'No active election found.']);
        }
        $election->update(['status' => 'certified']);
        return redirect('/results')->with('success', 'Results published successfully!');
    })->name('publish-results');
});
