<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyAcceptance extends Model
{
    protected $fillable = [
        'result_id',
        'political_party_id',
        'representative_id',
        'status', // accepted, accepted_with_reservation, rejected
        'comments',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'representative_id');
    }
}
