<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Election extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'description', 'legal_instrument',
        'nomination_start_date', 'nomination_end_date',
        'start_date', 'end_date', 'results_deadline', 'status',
        'requires_party_acceptance', 'allow_provisional_public_display',
        'gps_validation_radius_meters', 'created_by', 'configured_by',
        'activated_at', 'activated_by',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date',
        'nomination_start_date' => 'date', 'nomination_end_date' => 'date',
        'results_deadline' => 'date', 'activated_at' => 'datetime',
        'requires_party_acceptance' => 'boolean',
        'allow_provisional_public_display' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Election $election) {
            if (empty($election->slug)) {
                $election->slug = Str::slug($election->name);
            }
        });
    }

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function administrativeHierarchy(): HasMany { return $this->hasMany(AdministrativeHierarchy::class); }
    public function pollingStations(): HasMany { return $this->hasMany(PollingStation::class); }
    public function politicalParties(): HasMany { return $this->hasMany(PoliticalParty::class); }
    public function participatingParties(): BelongsToMany {
        return $this->belongsToMany(PoliticalParty::class, 'election_political_party')
                    ->withTimestamps();
    }
    public function candidates(): HasMany { return $this->hasMany(Candidate::class); }
    public function results(): HasMany { return $this->hasMany(Result::class); }
    public function partyRepresentatives(): HasMany { return $this->hasMany(PartyRepresentative::class); }
    public function electionMonitors(): HasMany { return $this->hasMany(ElectionMonitor::class); }
    public function aggregatedResults(): HasMany { return $this->hasMany(AggregatedResult::class); }

    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function isActive(): bool { return $this->status === 'active'; }
    public function isCertifying(): bool { return in_array($this->status, ['results_pending', 'certifying']); }
    public function isNationallyCertified(): bool { return $this->status === 'certified'; }
    public function allowsResultSubmission(): bool { return in_array($this->status, ['active', 'results_pending']); }
    public function allowsPublicDisplay(): bool {
        return $this->allow_provisional_public_display
            && in_array($this->status, ['results_pending', 'certifying', 'certified']);
    }
}