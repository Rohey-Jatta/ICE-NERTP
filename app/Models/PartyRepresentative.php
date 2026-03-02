<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartyRepresentative extends Model
{
    protected $fillable = [
        'user_id',
        'political_party_id',
        'election_id',
        'representative_id',
        'full_name',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function politicalParty(): BelongsTo
    {
        return $this->belongsTo(PoliticalParty::class);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function pollingStations(): BelongsToMany
    {
        return $this->belongsToMany(
            PollingStation::class,
            'party_representative_polling_station',
            'party_representative_id',
            'polling_station_id'
        );
    }
}
