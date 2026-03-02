<?php
// filepath: c:\Users\DELL\Desktop\Cayor\ice-nertp\app\Models\ResultVersion.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'result_id',
        'version',
        'data',            // whatever fields you need
        'created_by',
        'created_at',
    ];

    /**
     * back‑reference to the parent Result
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    // …add any other helpers/relations you need for your versioning…
}
