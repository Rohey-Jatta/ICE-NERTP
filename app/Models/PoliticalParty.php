<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PoliticalParty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'name',
        'abbreviation',
        'color',
        'logo_path',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function partyRepresentatives(): HasMany
    {
        return $this->hasMany(PartyRepresentative::class);
    }

    public function partyAcceptances(): HasMany
    {
        return $this->hasMany(PartyAcceptance::class);
    }
}
