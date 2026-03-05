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
        'name',
        'ballot_number',
        'photo_path',
        'is_independent',
        'is_active',
        'is_withdrawn',
        'withdrawn_at',
    ];

    protected $casts = [
        'is_independent' => 'boolean',
    ];

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
