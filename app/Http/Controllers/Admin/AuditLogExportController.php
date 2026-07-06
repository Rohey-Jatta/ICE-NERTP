<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AuditLogExportController
 *
 * Streams audit log records as a UTF-8 CSV without loading the entire
 * result set into memory. Supports the same filter params as the Phase 3
 * AuditLogs.jsx filter panel so the exported rows exactly match what the
 * user sees on screen.
 *
 * Route: GET /admin/audit-logs/export  (permission: view-audit-logs)
 *
 * Performance contract:
 *  - Uses Laravel's cursor() for O(1) memory regardless of row count.
 *  - Flushes every 500 rows to keep the TCP send buffer from stalling.
 *  - Hard cap of 100 000 rows per export to protect the DB under load.
 *    Larger extracts should use a queued job + S3 link.
 */
class AuditLogExportController extends Controller
{
    const MAX_ROWS   = 100_000;
    const FLUSH_EVERY = 500;

    /**
     * Stream a filtered CSV export.
     */
    public function export(Request $request): StreamedResponse
    {
        // Validate filter params (same keys as the UI filter panel)
        $filters = $request->validate([
            'user_id'            => 'nullable|integer',
            'module'             => 'nullable|string|max:100',
            'action'             => 'nullable|string|max:255',
            'workflow_stage'     => 'nullable|string|max:100',
            'decision'           => 'nullable|string|max:100',
            'election_id'        => 'nullable|integer',
            'polling_station_id' => 'nullable|integer',
            'outcome'            => 'nullable|in:success,failure,blocked',
            'date_from'          => 'nullable|date',
            'date_to'            => 'nullable|date|after_or_equal:date_from',
            'search'             => 'nullable|string|max:255',
        ]);

        $filename = 'audit-log-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM — ensures Excel opens non-ASCII correctly
            fwrite($out, "\xEF\xBB\xBF");

            // Header row
            fputcsv($out, $this->columns());

            $count = 0;

            AuditLog::with('user')
                ->applyFilters($filters)
                ->orderByDesc('created_at')
                ->limit(self::MAX_ROWS)
                ->cursor()    // generator — one row in memory at a time
                ->each(function (AuditLog $log) use ($out, &$count) {
                    fputcsv($out, $this->row($log));
                    $count++;

                    // Periodic flush keeps the TCP buffer from stalling on large exports
                    if ($count % self::FLUSH_EVERY === 0) {
                        ob_flush();
                        flush();
                    }
                });

            if ($count === self::MAX_ROWS) {
                // Append a sentinel row so the recipient knows the export was capped
                fputcsv($out, array_fill(0, count($this->columns()), ''));
                fputcsv($out, ['[EXPORT CAPPED]', "Only the first " . number_format(self::MAX_ROWS) . " records are included.", "Use a queued export for larger datasets."]);
            }

            fclose($out);

        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
            'Pragma'              => 'no-cache',
        ]);
    }

    /**
     * Chain-of-custody export for a single result — all events in
     * chronological order, formatted for printing or attaching to a report.
     *
     * Route: GET /admin/audit-logs/chain/{resultId}/export
     */
    public function exportChain(int $resultId): StreamedResponse
    {
        $filename = "chain-of-custody-result-{$resultId}-" . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($resultId) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $this->columns());

            AuditLog::with('user')
                ->chainForResult($resultId)
                ->cursor()
                ->each(fn (AuditLog $log) => fputcsv($out, $this->row($log)));

            fclose($out);

        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /** Column headers — must stay in sync with row(). */
    private function columns(): array
    {
        return [
            'ID',
            'Timestamp',
            'User Name',
            'User Role',
            'Module',
            'Action',
            'Workflow Stage',
            'Decision',
            'Description',
            'Comment (Exact)',
            'Previous Custodian Role',
            'Current Custodian Role',
            'Next Custodian Role',
            'Previous Status',
            'New Status',
            'Outcome',
            'Failure Reason',
            'Election ID',
            'Polling Station ID',
            'Result ID',
            'Ward ID',
            'Constituency ID',
            'Admin Area ID',
            'Correlation ID',
            'IP Address',
            'OS',
            'Browser',
            'Device Type',
            'Session ID',
            'Old Values (JSON)',
            'New Values (JSON)',
        ];
    }

    /** Map one AuditLog model to a CSV row array. */
    private function row(AuditLog $log): array
    {
        return [
            $log->id,
            $log->created_at?->format('Y-m-d H:i:s'),
            $log->user?->name ?? 'System',
            $log->user_role ?? '',
            $log->module ?? '',
            $log->action ?? '',
            $log->workflow_stage ?? '',
            $log->decision ?? '',
            $log->description ?? '',
            $log->comment_text ?? '',
            $log->previous_custodian_role ?? '',
            $log->current_custodian_role ?? '',
            $log->next_custodian_role ?? '',
            $log->previous_status ?? '',
            $log->new_status ?? '',
            $log->outcome ?? '',
            $log->failure_reason ?? '',
            $log->election_id ?? '',
            $log->polling_station_id ?? '',
            $log->result_id ?? '',
            $log->ward_id ?? '',
            $log->constituency_id ?? '',
            $log->admin_area_id ?? '',
            $log->correlation_id ?? '',
            $log->ip_address ?? '',
            $log->os ?? '',
            $log->browser ?? '',
            $log->device_type ?? '',
            $log->session_id ?? '',
            $log->old_values ? json_encode($log->old_values) : '',
            $log->new_values ? json_encode($log->new_values) : '',
        ];
    }
}