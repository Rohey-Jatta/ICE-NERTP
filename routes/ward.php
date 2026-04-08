<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\AuditLog;
use App\Models\AdministrativeHierarchy;
use App\Models\PollingStation;
use App\Models\Result;

Route::middleware(['auth', 'role:ward-approver'])
    ->prefix('ward')
    ->name('ward.')
    ->group(function () {

    // ── Dashboard ────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user = Auth::user();

        // Find the ward(s) this user is assigned to approve
        $ward = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')
            ->first();

        if (!$ward) {
            return Inertia::render('Ward/Dashboard', [
                'auth'       => ['user' => $user],
                'ward'       => null,
                'statistics' => ['pending' => 0, 'certified' => 0, 'rejected' => 0, 'total' => 0],
                'results'    => [],
            ]);
        }

        // All polling stations in this ward
        $stationIds = PollingStation::where('ward_id', $ward->id)->pluck('id');

        // Results that have passed party review and are now waiting for WARD approval
        $pendingResults = Result::whereIn('polling_station_id', $stationIds)
            ->where('certification_status', Result::STATUS_PENDING_WARD)
            ->with(['pollingStation', 'candidateVotes.candidate.politicalParty'])
            ->latest('submitted_at')
            ->get();

        // Results already certified at ward level or higher
        $certifiedResults = Result::whereIn('polling_station_id', $stationIds)
            ->whereIn('certification_status', [
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_PENDING_CONSTITUENCY,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_PENDING_ADMIN_AREA,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_PENDING_NATIONAL,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])
            ->count();

        $rejectedByWard = Result::whereIn('polling_station_id', $stationIds)
            ->where('certification_status', Result::STATUS_SUBMITTED)
            ->where('rejection_count', '>', 0)
            ->count();

        return Inertia::render('Ward/Dashboard', [
            'auth' => ['user' => $user],
            'ward' => [
                'id'   => $ward->id,
                'name' => $ward->name,
                'code' => $ward->code,
            ],
            'statistics' => [
                'totalStations' => $stationIds->count(),
                'pending'       => $pendingResults->count(),
                'certified'     => $certifiedResults,
                'rejected'      => $rejectedByWard,
            ],
            'results' => $pendingResults->map(fn($r) => [
                'id'                      => $r->id,
                'polling_station_name'    => $r->pollingStation->name ?? '—',
                'polling_station_code'    => $r->pollingStation->code ?? '—',
                'total_registered_voters' => $r->total_registered_voters,
                'total_votes_cast'        => $r->total_votes_cast,
                'valid_votes'             => $r->valid_votes,
                'rejected_votes'          => $r->rejected_votes,
                'submitted_at'            => $r->submitted_at?->format('Y-m-d H:i'),
                'certification_status'    => $r->certification_status,
                'candidate_votes'         => $r->candidateVotes->map(fn($cv) => [
                    'candidate_name' => $cv->candidate->name ?? $cv->candidate->full_name ?? 'Unknown',
                    'party_name'     => $cv->candidate->politicalParty->name ?? 'Independent',
                    'party_abbr'     => $cv->candidate->politicalParty->abbreviation ?? 'IND',
                    'party_color'    => $cv->candidate->politicalParty->color ?? '#6b7280',
                    'votes'          => $cv->votes,
                ]),
            ]),
            'certifiedResults' => Result::whereIn('polling_station_id', $stationIds)
                ->where('certification_status', Result::STATUS_WARD_CERTIFIED)
                ->count(),
        ]);
    })->name('dashboard');

    // ── Certify a result (advance to constituency) ───────────────────────────
    Route::post('/results/{id}/certify', function (Request $request, $id) {
        $user = Auth::user();

        $request->validate([
            'action' => 'required|in:certify,reject',
            'notes'  => 'nullable|string|max:2000',
        ]);

        $result = Result::findOrFail($id);

        // Verify this ward approver owns this ward
        $ward = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'ward')
            ->firstOrFail();

        $stationInWard = PollingStation::where('id', $result->polling_station_id)
            ->where('ward_id', $ward->id)
            ->exists();

        if (!$stationInWard) {
            return back()->withErrors(['error' => 'This polling station is not in your ward.']);
        }

        DB::beginTransaction();
        try {
            if ($request->action === 'reject') {
                // Roll back to officer
                $result->update([
                    'certification_status'  => Result::STATUS_SUBMITTED,
                    'last_rejection_reason' => $request->notes ?? 'Rejected by Ward Approver.',
                    'rejection_count'       => $result->rejection_count + 1,
                    'ward_rejected_at'      => now(),
                ]);

                AuditLog::record(
                    action: 'result.rejected_by_ward',
                    event:  'updated',
                    module: 'Results',
                    auditable: $result,
                    extra: ['reason' => $request->notes, 'ward_id' => $ward->id]
                );

                DB::commit();
                return redirect()->route('ward.dashboard')
                    ->with('success', 'Result rejected and returned to the Polling Officer.');
            }

            // Certify — advance to constituency
            $result->update([
                'certification_status' => Result::STATUS_PENDING_CONSTITUENCY,
                'ward_certified_at'    => now(),
                'ward_certified_by'    => $user->id,
            ]);

            AuditLog::record(
                action: 'result.ward_certified',
                event:  'updated',
                module: 'Results',
                auditable: $result,
                extra: ['ward_id' => $ward->id, 'certified_by' => $user->id]
            );

            DB::commit();
            return redirect()->route('ward.dashboard')
                ->with('success', 'Result certified at ward level and forwarded to the Constituency Approver.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ward certification failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed: ' . $e->getMessage()]);
        }
    })->name('results.certify');
});