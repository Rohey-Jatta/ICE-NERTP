<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\Result;

Route::middleware(['auth', 'role:admin-area-approver'])
    ->prefix('admin-area')
    ->name('admin-area.')
    ->group(function () {

    Route::get('/dashboard', fn() => Inertia::render('AdminArea/Dashboard', [
        'auth'           => ['user' => Auth::user()],
        'adminArea'      => null,
        'pendingResults' => 0,
        'statistics'     => ['approved' => 0, 'constituencies' => 0, 'progress' => 0],
    ]))->name('dashboard');

    Route::get('/approval-queue', fn() => Inertia::render('AdminArea/ApprovalQueue', [
        'auth'                => ['user' => Auth::user()],
        'constituencyResults' => [],
    ]))->name('approval-queue');

    Route::post('/certify/{result}', function (Result $result) {
        if ($result->certification_status !== Result::STATUS_PENDING_ADMIN_AREA) {
            return back()->withErrors(['error' => 'Not pending admin area approval.']);
        }
        DB::transaction(function () use ($result) {
            $result->update(['certification_status' => Result::STATUS_ADMIN_AREA_CERTIFIED]);
            $result->update(['certification_status' => Result::STATUS_PENDING_NATIONAL]);
        });
        AuditLog::record(action: 'certification.admin_area.approved', event: 'updated', module: 'Certification', auditable: $result);
        return back()->with('success', 'Result certified at admin area level.');
    })->name('certify');

    Route::post('/reject/{result}', function (Result $result, Request $request) {
        if ($result->certification_status !== Result::STATUS_PENDING_ADMIN_AREA) {
            return back()->withErrors(['error' => 'Not pending admin area approval.']);
        }
        $result->update([
            'certification_status'  => Result::STATUS_PENDING_CONSTITUENCY,
            'last_rejection_reason' => $request->input('comments', 'Rejected at admin area level'),
            'last_rejected_by'      => Auth::id(),
            'last_rejected_at'      => now(),
        ]);
        return back()->with('success', 'Result returned to constituency level.');
    })->name('reject');

    Route::get('/constituency-breakdowns', fn() => Inertia::render('AdminArea/ConstituencyBreakdowns', [
        'auth'           => ['user' => Auth::user()],
        'constituencies' => [],
    ]))->name('constituency-breakdowns');

    Route::get('/analytics', fn() => Inertia::render('AdminArea/Analytics', [
        'auth' => ['user' => Auth::user()],
    ]))->name('analytics');
});
