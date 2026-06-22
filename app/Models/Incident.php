<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable = [
        'election_id',
        'observation_id',
        'result_id',
        'type',
        'administrative_area_name',
        'administrative_area_id',
        'polling_station_name',
        'polling_station_id',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function scopeDisputes($query)
    {
        return $query->where('type', 'dispute');
    }

    public function scopeRejections($query)
    {
        return $query->where('type', 'rejection');
    }

    public function scopeResubmissions($query)
    {
        return $query->where('type', 'resubmission');
    }

    public function scopeForElection($query, ?int $electionId)
    {
        return $electionId ? $query->where('election_id', $electionId) : $query;
    }
}