<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AggregatedResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'candidate_id',
        'political_party_id',
        'hierarchy_node_id',
        'level',
        'total_votes',
        'total_registered_voters',
        'total_votes_cast',
        'valid_votes',
        'rejected_votes',
        'total_polling_stations',
        'stations_reported',
        'stations_certified',
        'based_on_certification_level',
        'last_computed_at',
    ];

    protected $casts = [
        'total_votes' => 'integer',
        'total_registered_voters' => 'integer',
        'total_votes_cast' => 'integer',
        'valid_votes' => 'integer',
        'rejected_votes' => 'integer',
        'total_polling_stations' => 'integer',
        'stations_reported' => 'integer',
        'stations_certified' => 'integer',
        'last_computed_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function politicalParty(): BelongsTo
    {
        return $this->belongsTo(PoliticalParty::class);
    }

    public function hierarchyNode(): BelongsTo
    {
        return $this->belongsTo(AdministrativeHierarchy::class, 'hierarchy_node_id');
    }
}
