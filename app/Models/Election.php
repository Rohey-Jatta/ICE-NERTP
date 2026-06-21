<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\CurrentElectionResolver;

class Election extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Statuses considered "operational" / current — see CurrentElectionResolver.
     * Mirrored here as a convenience constant so model-level scopes/helpers
     * don't need to import the service just to reference these strings.
     */
    public const CURRENT_STATUSES = CurrentElectionResolver::CURRENT_STATUSES;

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

        $bust = function (Election $election): void {
            self::forgetPublicCaches($election->id, $election->status);

            // Status transitions (activation, moving into submitting/
            // certifying/certified, etc.) must invalidate the current-election
            // resolver cache immediately — otherwise polling stations,
            // submissions, and dashboards could briefly resolve against a
            // stale "current" election for up to the cache TTL.
            if ($election->wasChanged('status') || $election->wasRecentlyCreated) {
                CurrentElectionResolver::forgetCache();
            }
        };

        static::saved($bust);
        static::deleted($bust);
        if (method_exists(static::class, 'restored')) {
            static::restored($bust);
        }
    }

    public static function forgetPublicCaches(int $electionId, ?string $status = null): void
    {
        $statuses = array_unique(array_filter([
            $status,
            'active',
            'submitting',
            'certifying',
            'results_pending',
            'certified',
        ]));

        foreach ($statuses as $publicStatus) {
            Cache::forget("results_summary_v7_{$electionId}_{$publicStatus}");
            Cache::forget("results_summary_v3_{$electionId}_{$publicStatus}");
        }

        Cache::forget("results_summary_v2_{$electionId}");
        Cache::forget("results_map_{$electionId}");
        Cache::forget("results_map_v2_{$electionId}");
        Cache::forget("results_map_agg_v3_{$electionId}");
        Cache::forget("results_map_agg_v4_{$electionId}");
        Cache::forget("results_stations_{$electionId}_pub");
        Cache::forget("results_stations_{$electionId}_prov");
        Cache::forget("results_stations_v2_{$electionId}");
        Cache::forget("stations_filters_{$electionId}");
    }

    public function getYearAttribute(): ?int
    {
        return $this->start_date?->year;
    }

    public function bustPublicCaches(): void
    {
        foreach (['draft', 'active', 'submitting', 'certifying', 'results_pending', 'certified', 'archived'] as $status) {
            Cache::forget("results_summary_v7_{$this->id}_{$status}");
            Cache::forget("results_summary_v3_{$this->id}_{$status}");
        }
        Cache::forget("results_map_{$this->id}");
        Cache::forget("results_map_v2_{$this->id}");
        Cache::forget("results_map_agg_v3_{$this->id}");
        Cache::forget("results_map_agg_v4_{$this->id}");
        Cache::forget("results_stations_{$this->id}_pub");
        Cache::forget("results_stations_{$this->id}_prov");
        Cache::forget("results_stations_v2_{$this->id}");
        Cache::forget("stations_filters_{$this->id}");
        Cache::forget('public_results_data');
        Cache::forget('chairman_dashboard_stats');
    }

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function administrativeHierarchy(): HasMany { return $this->hasMany(AdministrativeHierarchy::class); }

    /**
     * Stations historically "last seen" under this election. This is NOT
     * the authoritative set of stations for the election — that is always
     * resolved dynamically via CurrentElectionResolver against ALL active
     * polling stations. This relation exists only for historical/reporting
     * lookups (e.g. "what was this station's last known election").
     */
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

    /**
     * Elections currently in an operational status (active, submitting,
     * certifying). Prefer CurrentElectionResolver::current() for actually
     * resolving THE current election — this scope is for cases that need
     * the raw query builder (e.g. composing with other scopes).
     */
    public function scopeCurrent($query) { return $query->whereIn('status', self::CURRENT_STATUSES); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isSubmitting(): bool { return $this->status === 'submitting'; }
    public function isCurrent(): bool { return in_array($this->status, self::CURRENT_STATUSES, true); }
    public function isCertifying(): bool { return in_array($this->status, ['results_pending', 'certifying']); }
    public function isNationallyCertified(): bool { return $this->status === 'certified'; }
    public function isClosed(): bool { return in_array($this->status, ['certified', 'archived']); }

    public function allowsResultSubmission(): bool
    {
        return in_array($this->status, ['active', 'submitting', 'results_pending', 'certifying']);
    }

    public function allowsPublicDisplay(): bool {
        return $this->allow_provisional_public_display
            && in_array($this->status, ['submitting', 'results_pending', 'certifying', 'certified']);
    }
}