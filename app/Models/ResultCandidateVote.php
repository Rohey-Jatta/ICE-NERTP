<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultCandidateVote extends Model
{
    protected $fillable = [
        'result_id',
        'candidate_id',
        'election_id',
        'votes',
    ];

    protected $casts = [
        'votes' => 'integer',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }
}
