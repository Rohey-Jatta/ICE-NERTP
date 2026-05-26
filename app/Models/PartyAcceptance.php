<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyAcceptance extends Model
{
    protected $fillable = [
        'result_id',
        'political_party_id',
        'party_representative_id',   // FIXED: was 'representative_id'
        'election_id',               // ADDED: required NOT NULL column
        'status',
        'comments',
        'decided_at',                // ADDED
        'is_final',                  // ADDED
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'decided_at'  => 'datetime',
        'is_final'    => 'boolean',
    ];

    public function result()
    {
        return $this->belongsTo(Result::class);
    }

    public function party()
    {
        return $this->belongsTo(PoliticalParty::class, 'political_party_id');
    }

    public function representative()
    {
        return $this->belongsTo(User::class, 'party_representative_id');
    }

    public function politicalParty()
    {
        return $this->belongsTo(PoliticalParty::class, 'political_party_id');
    }
}