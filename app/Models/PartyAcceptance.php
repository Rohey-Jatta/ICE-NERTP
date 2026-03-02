<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyAcceptance extends Model
{
    protected $fillable = [
        'result_id',
        'political_party_id',
        'party_representative_id',
        'election_id',
        'status',
        'comments',
        'decided_at',
        'is_final',
    ];

    protected $casts = [
        'is_final' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function politicalParty(): BelongsTo
    {
        return $this->belongsTo(PoliticalParty::class);
    }

    public function partyRepresentative(): BelongsTo
    {
        return $this->belongsTo(PartyRepresentative::class);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }
}
