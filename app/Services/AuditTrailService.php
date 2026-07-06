<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Result;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AuditTrailService — single entry-point for forensic, append-only audit
 * recording per the Enterprise Election Audit Trail spec.
 *
 * Every public method produces a fully explicit, human-readable description
 * and captures chain-of-custody + device/session metadata. Audit failures
 * are caught and logged; they must NEVER block the action that triggered them.
 */
class AuditTrailService
{
    // ─────────────────────────────────────────────────────────────────
    // Core recorder
    // ─────────────────────────────────────────────────────────────────

    /**
     * Write one forensic audit record.  Prefer the named helpers below;
     * use this directly only for actions not yet covered by a named helper.
     */
    public function record(array $payload): ?AuditLog
    {
        try {
            $user = Auth::user();
            $req  = request();

            [$os, $browser, $deviceType] = $this->parseUserAgent($req?->userAgent());

            return AuditLog::create([
                // Identification
                'correlation_id'           => $payload['correlation_id'] ?? (string) Str::uuid(),
                'user_id'                  => Auth::id(),
                'user_role'                => $user?->getRoleNames()->first(),
                // Action descriptor
                'action'                   => $payload['action'],
                'event'                    => $payload['event']  ?? 'action',
                'module'                   => $payload['module'] ?? 'System',
                'description'              => $payload['description'] ?? null,
                // Election / geographic scope
                'election_id'              => $payload['election_id']        ?? null,
                'polling_station_id'       => $payload['polling_station_id'] ?? null,
                'ward_id'                  => $payload['ward_id']            ?? null,
                'constituency_id'          => $payload['constituency_id']    ?? null,
                'admin_area_id'            => $payload['admin_area_id']      ?? null,
                'result_id'                => $payload['result_id']          ?? null,
                // Auditable morphs
                'auditable_type'           => $payload['auditable_type'] ?? null,
                'auditable_id'             => $payload['auditable_id']   ?? null,
                // Chain of custody
                'workflow_stage'           => $payload['workflow_stage']           ?? null,
                'decision'                 => $payload['decision']                 ?? null,
                'previous_custodian_role'  => $payload['previous_custodian_role']  ?? null,
                'current_custodian_role'   => $payload['current_custodian_role']   ?? null,
                'next_custodian_role'      => $payload['next_custodian_role']      ?? null,
                'previous_status'          => $payload['previous_status'] ?? null,
                'new_status'               => $payload['new_status']      ?? null,
                // Comment (exact, untruncated)
                'comment_text'             => $payload['comment_text'] ?? null,
                // Before / after values
                'old_values'               => $payload['old_values'] ?? null,
                'new_values'               => $payload['new_values'] ?? null,
                // Outcome
                'outcome'                  => $payload['outcome']         ?? 'success',
                'failure_reason'           => $payload['failure_reason']  ?? null,
                // Request / device metadata
                'ip_address'               => $req?->ip(),
                'user_agent'               => $req?->userAgent(),
                'session_id'               => $this->safeSessionId($req),
                'request_id'               => $req?->header('X-Request-Id'),
                'url'                      => $req?->fullUrl(),
                'http_method'              => $req?->method(),
                'os'                       => $os,
                'browser'                  => $browser,
                'device_type'              => $deviceType,
                'timezone'                 => config('app.timezone'),
                'latitude'                 => $payload['latitude']  ?? null,
                'longitude'                => $payload['longitude'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('[AuditTrail] Failed to write audit record: ' . $e->getMessage(), [
                'action' => $payload['action'] ?? 'unknown',
            ]);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Geographic / hierarchy resolver
    // ─────────────────────────────────────────────────────────────────

    /**
     * Return ward / constituency / admin-area IDs for a result's station.
     * Safe to call even if relationships are not loaded yet.
     */
    public function resolveHierarchy(Result $result): array
    {
        try {
            $result->loadMissing('pollingStation.ward.parent.parent');
            $ward         = $result->pollingStation?->ward;
            $constituency = $ward?->parent;
            $adminArea    = $constituency?->parent;

            return [
                'polling_station_id' => $result->polling_station_id,
                'ward_id'            => $ward?->id,
                'constituency_id'    => $constituency?->id,
                'admin_area_id'      => $adminArea?->id,
                'correlation_id'     => $result->submission_uuid,
            ];
        } catch (\Throwable $e) {
            Log::warning('[AuditTrail] resolveHierarchy failed: ' . $e->getMessage());
            return [
                'polling_station_id' => $result->polling_station_id,
                'correlation_id'     => $result->submission_uuid,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Named, spec-aligned recorders
    // ─────────────────────────────────────────────────────────────────

    /** Polling Station Officer submitted a result. */
    public function resultSubmitted(Result $result): ?AuditLog
    {
        $officer   = Auth::user();
        $hierarchy = $this->resolveHierarchy($result);

        return $this->record(array_merge($hierarchy, [
            'action'                  => 'result.submitted',
            'event'                   => 'created',
            'module'                  => 'Results',
            'election_id'             => $result->election_id,
            'result_id'               => $result->id,
            'workflow_stage'          => 'polling_officer',
            'current_custodian_role'  => 'polling-officer',
            'next_custodian_role'     => 'ward-approver',
            'new_status'              => $result->certification_status,
            'new_values' => [
                'total_registered_voters' => $result->total_registered_voters,
                'total_votes_cast'        => $result->total_votes_cast,
                'valid_votes'             => $result->valid_votes,
                'rejected_votes'          => $result->rejected_votes,
                'disputed_votes'          => $result->disputed_votes,
                'submission_uuid'         => $result->submission_uuid,
            ],
            'description' => sprintf(
                'Polling Station Officer %s (ID:%d) submitted result for station %s. '
                . 'Total cast: %d, valid: %d, rejected: %d. '
                . 'Custody transferred to Ward Approver queue.',
                $officer?->name  ?? 'Unknown',
                $officer?->id    ?? 0,
                $result->pollingStation?->code ?? $result->polling_station_id,
                $result->total_votes_cast,
                $result->valid_votes,
                $result->rejected_votes
            ),
        ]));
    }

    /** Polling Station Officer re-submitted a previously rejected result. */
    public function resultResubmitted(Result $result, int $rejectionCount): ?AuditLog
    {
        $officer   = Auth::user();
        $hierarchy = $this->resolveHierarchy($result);

        return $this->record(array_merge($hierarchy, [
            'action'                  => 'result.resubmitted',
            'event'                   => 'updated',
            'module'                  => 'Results',
            'election_id'             => $result->election_id,
            'result_id'               => $result->id,
            'workflow_stage'          => 'polling_officer',
            'decision'                => 'resubmitted',
            'current_custodian_role'  => 'polling-officer',
            'next_custodian_role'     => 'ward-approver',
            'new_status'              => $result->certification_status,
            'description' => sprintf(
                'Polling Station Officer %s (ID:%d) resubmitted result for station %s '
                . '(resubmission #%d after previous rejection). '
                . 'New totals — cast: %d, valid: %d, rejected: %d.',
                $officer?->name ?? 'Unknown',
                $officer?->id   ?? 0,
                $result->pollingStation?->code ?? $result->polling_station_id,
                $rejectionCount,
                $result->total_votes_cast,
                $result->valid_votes,
                $result->rejected_votes
            ),
        ]));
    }

    /** Approver (ward / constituency / admin-area / national) approved a result. */
    public function approval(
        Result  $result,
        string  $level,
        string  $currentRole,
        string  $nextRole,
        ?string $comments,
        bool    $withReservation,
        string  $previousStatus
    ): ?AuditLog {
        $approver  = Auth::user();
        $hierarchy = $this->resolveHierarchy($result);
        $verb      = $withReservation ? 'approved with reservation' : 'approved';
        $action    = "certification.{$level}." . ($withReservation ? 'approved_with_reservation' : 'approved');

        $description = sprintf(
            '%s %s (ID:%d) %s result for Polling Station %s at %s. '
            . 'Previous status: %s → New status: %s. '
            . 'Custody transferred to %s.',
            ucwords(str_replace('-', ' ', $currentRole)),
            $approver?->name ?? 'Unknown',
            $approver?->id   ?? 0,
            $verb,
            $result->pollingStation?->code ?? $result->polling_station_id,
            now()->toDateTimeString(),
            $previousStatus,
            $result->certification_status,
            ucwords(str_replace('-', ' ', $nextRole))
        );

        if ($comments) {
            $description .= " Approver comment: \"{$comments}\"";
        }

        return $this->record(array_merge($hierarchy, [
            'action'                  => $action,
            'event'                   => 'updated',
            'module'                  => 'Certification',
            'election_id'             => $result->election_id,
            'result_id'               => $result->id,
            'workflow_stage'          => $level,
            'decision'                => $withReservation ? 'approved_with_reservation' : 'approved',
            'previous_custodian_role' => $currentRole,
            'current_custodian_role'  => $currentRole,
            'next_custodian_role'     => $nextRole,
            'previous_status'         => $previousStatus,
            'new_status'              => $result->certification_status,
            'comment_text'            => $comments,
            'description'             => $description,
        ]));
    }

    /** Approver rejected a result and returned it to an earlier stage. */
    public function rejection(
        Result  $result,
        string  $level,
        string  $currentRole,
        string  $returnedToRole,
        string  $reason,
        string  $previousStatus
    ): ?AuditLog {
        $approver  = Auth::user();
        $hierarchy = $this->resolveHierarchy($result);

        $description = sprintf(
            '%s %s (ID:%d) REJECTED result for Polling Station %s at %s. '
            . 'Previous status: %s → Returned to: %s (new status: %s). '
            . 'Rejection reason: "%s". Total rejections for this result: %d.',
            ucwords(str_replace('-', ' ', $currentRole)),
            $approver?->name ?? 'Unknown',
            $approver?->id   ?? 0,
            $result->pollingStation?->code ?? $result->polling_station_id,
            now()->toDateTimeString(),
            $previousStatus,
            ucwords(str_replace('-', ' ', $returnedToRole)),
            $result->certification_status,
            $reason,
            $result->rejection_count
        );

        return $this->record(array_merge($hierarchy, [
            'action'                  => "certification.{$level}.rejected",
            'event'                   => 'updated',
            'module'                  => 'Certification',
            'election_id'             => $result->election_id,
            'result_id'               => $result->id,
            'workflow_stage'          => $level,
            'decision'                => 'rejected',
            'previous_custodian_role' => $currentRole,
            'current_custodian_role'  => $currentRole,
            'next_custodian_role'     => $returnedToRole,
            'previous_status'         => $previousStatus,
            'new_status'              => $result->certification_status,
            'comment_text'            => $reason,
            'outcome'                 => 'failure',
            'failure_reason'          => $reason,
            'description'             => $description,
        ]));
    }

    /** IEC Chairman nationally certified a result. */
    public function nationalCertification(Result $result, ?string $comments): ?AuditLog
    {
        $chairman  = Auth::user();
        $hierarchy = $this->resolveHierarchy($result);

        $description = sprintf(
            'IEC Chairman %s (ID:%d) NATIONALLY CERTIFIED result for Polling Station %s at %s. '
            . 'Result is now publicly visible. Total votes cast: %d, valid: %d.',
            $chairman?->name ?? 'Unknown',
            $chairman?->id   ?? 0,
            $result->pollingStation?->code ?? $result->polling_station_id,
            now()->toDateTimeString(),
            $result->total_votes_cast,
            $result->valid_votes
        );

        if ($comments) {
            $description .= " Chairman comment: \"{$comments}\"";
        }

        return $this->record(array_merge($hierarchy, [
            'action'                  => 'certification.national.certified',
            'event'                   => 'updated',
            'module'                  => 'Certification',
            'election_id'             => $result->election_id,
            'result_id'               => $result->id,
            'workflow_stage'          => 'national',
            'decision'                => 'certified',
            'previous_custodian_role' => 'admin-area-approver',
            'current_custodian_role'  => 'iec-chairman',
            'next_custodian_role'     => 'public',
            'previous_status'         => 'pending_national',
            'new_status'              => $result->certification_status,
            'comment_text'            => $comments,
            'description'             => $description,
        ]));
    }

    /** Political Party Representative accepted / rejected a result. */
    public function partyAcceptance(
        Result  $result,
        string  $partyName,
        string  $status,
        ?string $comments
    ): ?AuditLog {
        $rep       = Auth::user();
        $hierarchy = $this->resolveHierarchy($result);
        $statusLabel = str_replace('_', ' ', $status);

        $description = sprintf(
            'Party Representative %s (ID:%d) of %s recorded "%s" for Polling Station %s result at %s.',
            $rep?->name  ?? 'Unknown',
            $rep?->id    ?? 0,
            $partyName,
            $statusLabel,
            $result->pollingStation?->code ?? $result->polling_station_id,
            now()->toDateTimeString()
        );

        if ($comments) {
            $description .= " Comment: \"{$comments}\"";
        }

        return $this->record(array_merge($hierarchy, [
            'action'                  => 'party_acceptance.' . $status,
            'event'                   => 'created',
            'module'                  => 'PartyAcceptance',
            'election_id'             => $result->election_id,
            'result_id'               => $result->id,
            'workflow_stage'          => 'party_representative',
            'decision'                => $status,
            'current_custodian_role'  => 'party-representative',
            'comment_text'            => $comments,
            'description'             => $description,
        ]));
    }

    /** Election Monitor submitted an observation. */
    public function observationSubmitted(
        int     $observationId,
        int     $electionId,
        int     $pollingStationId,
        string  $stationCode,
        string  $type,
        string  $severity,
        string  $title
    ): ?AuditLog {
        $monitor = Auth::user();

        return $this->record([
            'action'              => 'monitor.observation.submitted',
            'event'               => 'created',
            'module'              => 'ElectionMonitor',
            'election_id'         => $electionId,
            'polling_station_id'  => $pollingStationId,
            'workflow_stage'      => 'election_monitor',
            'current_custodian_role' => 'election-monitor',
            'description' => sprintf(
                'Election Monitor %s (ID:%d) submitted a %s-severity %s observation '
                . '(ID:%d) titled "%s" for Polling Station %s at %s.',
                $monitor?->name ?? 'Unknown',
                $monitor?->id   ?? 0,
                strtoupper($severity),
                str_replace('_', ' ', $type),
                $observationId,
                $title,
                $stationCode,
                now()->toDateTimeString()
            ),
        ]);
    }

    /** Authentication event (login success/failure/logout/2FA/device). */
    public function authEvent(string $action, string $description, array $extra = []): ?AuditLog
    {
        return $this->record(array_merge([
            'action'      => $action,
            'event'       => $extra['event'] ?? 'action',
            'module'      => 'Authentication',
            'description' => $description,
        ], $extra));
    }

    /** Administrative lifecycle event (election created/closed/published etc.). */
    public function electionEvent(
        string $action,
        int    $electionId,
        string $electionName,
        string $description,
        array  $extra = []
    ): ?AuditLog {
        return $this->record(array_merge([
            'action'      => $action,
            'event'       => $extra['event'] ?? 'updated',
            'module'      => 'ElectionManagement',
            'election_id' => $electionId,
            'description' => $description,
        ], $extra));
    }

    /** Generic comment made at any workflow stage. */
    public function comment(
        string  $module,
        string  $roleLabel,
        ?string $stationCode,
        string  $stage,
        string  $commentText,
        array   $extra = []
    ): ?AuditLog {
        $user = Auth::user();
        return $this->record(array_merge([
            'action'         => strtolower($module) . '.comment',
            'event'          => 'action',
            'module'         => $module,
            'workflow_stage' => $stage,
            'comment_text'   => $commentText,
            'description'    => sprintf(
                '%s %s (ID:%d) added a comment%s: "%s"',
                $roleLabel,
                $user?->name ?? 'Unknown',
                $user?->id   ?? 0,
                $stationCode ? " on Polling Station {$stationCode}" : '',
                $commentText
            ),
        ], $extra));
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    private function parseUserAgent(?string $ua): array
    {
        $ua = $ua ?? '';
        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac')    => 'macOS',
            str_contains($ua, 'Linux')  => 'Linux',
            default                      => 'Unknown',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg')     => 'Edge',
            str_contains($ua, 'Chrome')  => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari')  => 'Safari',
            default                       => 'Unknown',
        };
        $deviceType = match (true) {
            str_contains(strtolower($ua), 'tablet') || str_contains(strtolower($ua), 'ipad') => 'tablet',
            str_contains(strtolower($ua), 'mobile') || str_contains(strtolower($ua), 'android') => 'mobile',
            default => 'desktop',
        };
        return [$os, $browser, $deviceType];
    }

    private function safeSessionId($request): ?string
    {
        try {
            return $request?->session()?->getId();
        } catch (\Throwable $e) {
            return null;
        }
    }
}