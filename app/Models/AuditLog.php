<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    /**
     * Audit logs are append-only — we never UPDATE a record.
     * Disabling updated_at saves one column write per insert.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'election_id',
        'action',
        'event',
        'module',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'user_role',
        'permission_used',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'latitude',
        'longitude',
        'outcome',
        'failure_reason',
        // Forensic / chain-of-custody fields (2026_07_01_000001 migration)
        'correlation_id',
        'polling_station_id',
        'ward_id',
        'constituency_id',
        'admin_area_id',
        'result_id',
        'previous_custodian_role',
        'current_custodian_role',
        'next_custodian_role',
        'workflow_stage',
        'decision',
        'previous_status',
        'new_status',
        'comment_text',
        'description',
        'session_id',
        'request_id',
        'url',
        'http_method',
        'os',
        'browser',
        'device_type',
        'mac_address',
        'timezone',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Query Scopes ───────────────────────────────────────────────────────────

    /** Filter by module name. */
    public function scopeModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /** Filter by workflow stage. */
    public function scopeStage(Builder $query, string $stage): Builder
    {
        return $query->where('workflow_stage', $stage);
    }

    /** Filter by decision outcome. */
    public function scopeDecision(Builder $query, string $decision): Builder
    {
        return $query->where('decision', $decision);
    }

    /** Filter to a specific result's chain of custody. */
    public function scopeChainForResult(Builder $query, int $resultId): Builder
    {
        return $query->where('result_id', $resultId)->orderBy('created_at');
    }

    /** Filter by election scope. */
    public function scopeForElection(Builder $query, int $electionId): Builder
    {
        return $query->where('election_id', $electionId);
    }

    /** Filter by polling station. */
    public function scopeForStation(Builder $query, int $stationId): Builder
    {
        return $query->where('polling_station_id', $stationId);
    }

    /** Filter by outcome. */
    public function scopeOutcome(Builder $query, string $outcome): Builder
    {
        return $query->where('outcome', $outcome);
    }

    /** Date range filter. */
    public function scopeInDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);
        return $query;
    }

    /**
     * Free-text search across description, action, and ip_address.
     * Uses FULLTEXT index on MySQL when available; falls back to LIKE.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        if (config('database.default') === 'mysql') {
            return $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('MATCH(description) AGAINST (? IN BOOLEAN MODE)', [$term . '*'])
                  ->orWhere('action', 'like', "%{$term}%")
                  ->orWhere('ip_address', 'like', "%{$term}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
            });
        }

        // SQLite / fallback
        return $query->where(function (Builder $q) use ($term) {
            $q->where('description', 'like', "%{$term}%")
              ->orWhere('action', 'like', "%{$term}%")
              ->orWhere('ip_address', 'like', "%{$term}%")
              ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * Apply all Phase 3 filter params from a request array in one call.
     * Keeps the export controller and the route closure DRY.
     */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['user_id']))            $query->where('user_id', $filters['user_id']);
        if (!empty($filters['module']))             $query->module($filters['module']);
        if (!empty($filters['action']))             $query->where('action', 'like', '%' . $filters['action'] . '%');
        if (!empty($filters['workflow_stage']))     $query->stage($filters['workflow_stage']);
        if (!empty($filters['decision']))           $query->decision($filters['decision']);
        if (!empty($filters['election_id']))        $query->forElection((int) $filters['election_id']);
        if (!empty($filters['polling_station_id'])) $query->forStation((int) $filters['polling_station_id']);
        if (!empty($filters['outcome']))            $query->outcome($filters['outcome']);
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        }
        if (!empty($filters['search']))             $query->search($filters['search']);

        return $query;
    }

    // ── Static helpers (backward-compatible) ──────────────────────────────────

    /**
     * Legacy static recorder kept for backward compatibility.
     * New code should inject and call AuditTrailService directly.
     */
    public static function record(
        string $action,
        string $event     = 'action',
        string $module    = 'System',
               $auditable = null,
        array  $extra     = []
    ): ?self {
        try {
            $user = Auth::user();

            $payload = [
                'user_id'        => Auth::id(),
                'action'         => $action,
                'event'          => $event,
                'module'         => $module,
                'ip_address'     => Request::ip(),
                'user_agent'     => Request::userAgent(),
                'user_role'      => $user?->getRoleNames()->first(),
                'outcome'        => $extra['outcome']        ?? 'success',
                'failure_reason' => $extra['failure_reason'] ?? null,
                'election_id'    => $extra['election_id']    ?? null,
                'latitude'       => $extra['latitude']       ?? null,
                'longitude'      => $extra['longitude']      ?? null,
                'description'    => $extra['description']    ?? null,
            ];

            if ($auditable !== null && is_object($auditable) && isset($auditable->id)) {
                $payload['auditable_type'] = get_class($auditable);
                $payload['auditable_id']   = $auditable->id;
            }

            if (array_key_exists('old_values', $extra)) $payload['old_values'] = $extra['old_values'];
            if (array_key_exists('new_values', $extra)) $payload['new_values'] = $extra['new_values'];

            return static::create($payload);

        } catch (\Throwable $e) {
            Log::error('[AuditLog] record() failed: ' . $e->getMessage());
            return null;
        }
    }

    /** One-liner for route closures. */
    public static function log(string $action, string $module = 'System', array $extra = []): void
    {
        static::record(action: $action, module: $module, extra: $extra);
    }
}