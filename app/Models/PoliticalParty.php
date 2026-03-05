<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Str;

class PoliticalParty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'name',
        'abbreviation',
        'slug',
        'color',
        'logo_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (PoliticalParty $party) {
            if (empty($party->slug)) {
                $party->slug = Str::slug($party->name);
            }
        });
    }

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
