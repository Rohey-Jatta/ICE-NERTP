<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\AuditLog;
use App\Models\Result;

Route::middleware(['auth', 'role:ward-approver'])
    ->prefix('ward')
    ->name('ward.')
    ->group(function () {

    Route::get('/dashboard', function () {
        $user    = Auth::user();
        $ward    = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();
        $pending = $ward
            ? Result::where('certification_status', Result::STATUS_PENDING_WARD)
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))->count()
            : 0;
        return Inertia::render('Ward/Dashboard', [
            'auth'           => ['user' => $user],
            'ward'           => $ward,
            'pendingResults' => $pending,
            'statistics'     => ['approved' => 0, 'rejected' => 0],
        ]);
    })->name('dashboard');

    Route::get('/approval-queue', function () {
        $user    = Auth::user();
        $ward    = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')->first();
        $pending = $ward
            ? Result::where('certification_status', Result::STATUS_PENDING_WARD)
                ->whereHas('pollingStation', fn($q) => $q->where('ward_id', $ward->id))
                ->with(['pollingStation', 'candidateVotes.candidate'])
                ->get()
                ->map(fn($r) => [
                    'id'              => $r->id,
                    'polling_station' => $r->pollingStation->name ?? 'Unknown',
                    'officer'         => 'Officer',
                    'submitted_at'    => $r->submitted_at?->format('Y-m-d H:i'),
                    'total_votes'     => $r->total_votes_cast,
                    'valid_votes'     => $r->valid_votes,
                    'rejected_votes'  => $r->rejected_votes,
                    'turnout'         => $r->getTurnoutPercentage() . '%',
                    'party_acceptance'=> 'Pending',
                ])
            : collect();
        return Inertia::render('Ward/ApprovalQueue', [
            'auth'           => ['user' => $user],
            'pendingResults' => $pending,
        ]);
    })->name('approval-queue');

    Route::post('/approve/{result}', function (Result $result) {
        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }
        DB::transaction(function () use ($result) {
            $result->update(['certification_status' => Result::STATUS_WARD_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_CONSTITUENCY]);
        });
        AuditLog::record(action: 'certification.ward.approved', event: 'updated', module: 'Certification', auditable: $result);
        return back()->with('success', 'Result certified at ward level.');
    })->name('approve');

    Route::post('/reject/{result}', function (Result $result, Request $request) {
        if ($result->certification_status !== Result::STATUS_PENDING_WARD) {
            return back()->withErrors(['error' => 'Result is not pending ward approval.']);
        }
        $result->update([
            'certification_status'  => Result::STATUS_SUBMITTED,
            'last_rejection_reason' => $request->input('comments', 'Rejected at ward level'),
            'last_rejected_by'      => Auth::id(),
            'last_rejected_at'      => now(),
        ]);
        AuditLog::record(action: 'certification.ward.rejected', event: 'updated', module: 'Certification', auditable: $result);
        return back()->with('success', 'Result rejected and returned to officer.');
    })->name('reject');

    Route::get('/analytics', fn() => Inertia::render('Ward/Analytics', [
        'auth' => ['user' => Auth::user()],
    ]))->name('analytics');
});
