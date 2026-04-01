<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AdministrativeHierarchy;
use App\Models\AuditLog;
use App\Models\Result;

Route::middleware(['auth', 'role:constituency-approver'])
    ->prefix('constituency')
    ->name('constituency.')
    ->group(function () {

    Route::get('/dashboard', function () {
        $user         = Auth::user();
        $constituency = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'constituency')->first();
        $pending      = $constituency
            ? Result::where('certification_status', Result::STATUS_PENDING_CONSTITUENCY)
                ->whereHas('pollingStation.ward', fn($q) => $q->where('parent_id', $constituency->id))->count()
            : 0;
        return Inertia::render('Constituency/Dashboard', [
            'auth'           => ['user' => $user],
            'constituency'   => $constituency,
            'pendingResults' => $pending,
            'statistics'     => ['approved' => 0, 'totalWards' => 0],
        ]);
    })->name('dashboard');

    Route::get('/approval-queue', fn() => Inertia::render('Constituency/ApprovalQueue', [
        'auth'        => ['user' => Auth::user()],
        'wardResults' => [],
    ]))->name('approval-queue');

    Route::post('/approve/{result}', function (Result $result) {
        if ($result->certification_status !== Result::STATUS_PENDING_CONSTITUENCY) {
            return back()->withErrors(['error' => 'Result is not pending constituency approval.']);
        }
        DB::transaction(function () use ($result) {
            $result->update(['certification_status' => Result::STATUS_CONSTITUENCY_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_ADMIN_AREA]);
        });
        AuditLog::record(action: 'certification.constituency.approved', event: 'updated', module: 'Certification', auditable: $result);
        return back()->with('success', 'Result certified at constituency level.');
    })->name('approve');

    Route::post('/reject/{result}', function (Result $result, Request $request) {
        if ($result->certification_status !== Result::STATUS_PENDING_CONSTITUENCY) {
            return back()->withErrors(['error' => 'Not pending constituency approval.']);
        }
        $result->update([
            'certification_status'  => Result::STATUS_PENDING_WARD,
            'last_rejection_reason' => $request->input('comments', 'Rejected at constituency level'),
            'last_rejected_by'      => Auth::id(),
            'last_rejected_at'      => now(),
        ]);
        return back()->with('success', 'Result returned to ward level.');
    })->name('reject');

    Route::get('/ward-breakdowns', fn() => Inertia::render('Constituency/WardBreakdowns', [
        'auth'  => ['user' => Auth::user()],
        'wards' => [],
    ]))->name('ward-breakdowns');

    Route::get('/reports', fn() => Inertia::render('Constituency/Reports', [
        'auth'    => ['user' => Auth::user()],
        'reports' => [],
    ]))->name('reports');
});
