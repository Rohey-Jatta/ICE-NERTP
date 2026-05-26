<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'political_party_id',
        'constituency_id',
        'name',           // DB column is `name` — was wrongly `full_name` before
        'ballot_number',  // DB column is `ballot_number` — was wrongly `candidate_number` before
        'photo_path',
        'is_independent',
        'is_active',
        'is_withdrawn',
        'withdrawn_at',
    ];

    protected $casts = [
        'is_independent' => 'boolean',
        'is_active'      => 'boolean',
        'is_withdrawn'   => 'boolean',
        'withdrawn_at'   => 'datetime',
    ];

    /**
     * Backward-compat accessor: some views reference $candidate->full_name.
     * Returns `name` so nothing breaks.
     */
    public function getFullNameAttribute(): ?string
    {
        return $this->name;
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function politicalParty(): BelongsTo
    {
        return $this->belongsTo(PoliticalParty::class);
    }

    public function resultCandidateVotes(): HasMany
    {
        return $this->hasMany(ResultCandidateVote::class);
    }
}