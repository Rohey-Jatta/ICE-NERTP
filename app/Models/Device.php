<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    const ROLES_REQUIRING_DEVICE_BINDING = [
        'polling-officer',
        'ward-approver',
        'constituency-approver',
        'admin-area-approver',
        'iec-chairman',
        'iec-administrator',
    ];

    protected $fillable = [
        'user_id', 'device_fingerprint', 'device_name', 'device_type',
        'os', 'browser', 'token_id',
        'verified_at', 'verified_by_ip',
        'last_used_at', 'last_used_ip',
        'is_trusted', 'is_revoked', 'revoked_at',
    ];

    protected $casts = [
        'is_trusted' => 'boolean',
        'is_revoked' => 'boolean',
        'verified_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && !$this->is_revoked;
    }

    public static function roleRequiresBinding(string $role): bool
    {
        return in_array($role, self::ROLES_REQUIRING_DEVICE_BINDING);
    }

    public function verify(string $ip): void
    {
        $this->update([
            'verified_at' => now(),
            'verified_by_ip' => $ip,
            'is_trusted' => true,
        ]);
    }

    public function recordUsage(string $ip): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }

    public function revoke(): void
    {
        $this->update([
            'is_trusted' => false,
            'is_revoked' => true,
            'revoked_at' => now(),
        ]);
    }

    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true)->where('is_revoked', false);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at')->where('is_revoked', false);
    }
}
