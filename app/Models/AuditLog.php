<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

class AuditLog extends Model
{
    public $timestamps = false;

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
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public static function record(
        string $action,
        string $event = 'created',
        string $module = 'General',
        $auditable = null,
        ?array $extra = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ) {
        try {
            $user = Auth::user();

            return self::create([
                'user_id' => $user?->id,
                'action' => $action,
                'event' => $event,
                'module' => $module,
                'auditable_type' => $auditable ? get_class($auditable) : null,
                'auditable_id' => $auditable?->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'user_role' => $user?->roles?->first()?->name,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'outcome' => $extra['outcome'] ?? 'success',
                'failure_reason' => $extra['failure_reason'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Audit log failed: ' . $e->getMessage());
            return null;
        }
    }
}
