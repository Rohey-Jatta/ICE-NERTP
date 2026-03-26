<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultVersion extends Model
{
    use HasFactory;

    /**
     * result_versions has only created_at (no updated_at — append-only snapshots).
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'result_id',
        'version_number',
        'result_snapshot',
        'votes_snapshot',
        'changed_by',
        'change_reason',
        'change_notes',
        'certification_status_at_version',
    ];

    protected $casts = [
        'result_snapshot' => 'array',
        'votes_snapshot'  => 'array',
        'created_at'      => 'datetime',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
