<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PoliticalParty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'election_id',
        'name',
        'abbreviation',
        'slug',
        'registration_number',
        'color',
        'logo_path',
        'leader_name',
        'leader_photo_path',
        'symbol_path',
        'motto',
        'headquarters',
        'website',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_active',
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

    public function elections(): BelongsToMany
    {
        return $this->belongsToMany(Election::class, 'election_political_party')
                    ->withTimestamps();
    }

    /**
     * Parse the comma-separated color string into an array of hex colors.
     */
    public function getColorsArrayAttribute(): array
    {
        if (!$this->color) return [];
        return array_filter(array_map('trim', explode(',', $this->color)), fn($c) => preg_match('/^#[0-9a-fA-F]{6}$/', $c));
    }

    /**
     * Get the first color or a default.
     */
    public function getPrimaryColorAttribute(): string
    {
        $colors = $this->colors_array;
        return $colors[0] ?? '#6b7280';
    }
}