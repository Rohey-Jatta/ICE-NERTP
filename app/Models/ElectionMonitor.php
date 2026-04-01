<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionMonitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'election_id',
        'organization',
        'accreditation_number',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function pollingStations(): BelongsToMany
    {
        // Pivot table has no created_at/updated_at — do NOT call withTimestamps()
        return $this->belongsToMany(PollingStation::class, 'election_monitor_polling_station');
    }
}
