<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultCertification extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'result_id', 'certification_level', 'hierarchy_node_id',
        'approver_id', 'status', 'comments',
        'assigned_at', 'decided_at', 'next_certification_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime', 'decided_at' => 'datetime', 'created_at' => 'datetime',
    ];

    public function result(): BelongsTo { return $this->belongsTo(Result::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
    public function hierarchyNode(): BelongsTo { return $this->belongsTo(AdministrativeHierarchy::class, 'hierarchy_node_id'); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeAtLevel($q, string $level) { return $q->where('certification_level', $level); }
    public function scopeForApprover($q, int $id) { return $q->where('approver_id', $id); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function getHoursPending(): float {
        return round($this->assigned_at->diffInMinutes($this->decided_at ?? now()) / 60, 1);
    }
    public function isExceedingSLA(): bool { return $this->isPending() && $this->getHoursPending() > 24; }
}
