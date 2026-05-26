<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

class AuditLog extends Model
{
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
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Static helpers ────────────────────────────────────────────────

    /**
     * Full audit record — supports the named-parameter calling style used
     * across all controllers.
     *
     * @param string      $action    e.g. 'result.submitted'
     * @param string      $event     e.g. 'created' | 'updated' | 'deleted' | 'blocked' | 'failure'
     * @param string      $module    e.g. 'Results' | 'Authentication'
     * @param mixed|null  $auditable Eloquent model instance (optional)
     * @param array       $extra     Additional context: outcome, failure_reason, election_id, …
     */
    public static function record(
        string $action,
        string $event     = 'action',
        string $module    = 'System',
               $auditable = null,
        array  $extra     = []
    ): ?self {
        try {
           /** @var \App\Models\User|null $user */
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
            ];

            if ($auditable !== null && is_object($auditable) && isset($auditable->id)) {
                $payload['auditable_type'] = get_class($auditable);
                $payload['auditable_id']   = $auditable->id;
            }

            if (array_key_exists('old_values', $extra)) {
                $payload['old_values'] = $extra['old_values'];
            }
            if (array_key_exists('new_values', $extra)) {
                $payload['new_values'] = $extra['new_values'];
            }

            return static::create($payload);

        } catch (\Throwable $e) {
            Log::error('[AuditLog] record() failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lightweight one-liner for route closures.
     * AuditLog::log('user.created', 'UserManagement');
     */
    public static function log(
        string $action,
        string $module = 'System',
        array  $extra  = []
    ): void {
        static::record(action: $action, module: $module, extra: $extra);
    }
}
