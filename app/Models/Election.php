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

    /**
     * Statuses considered "in flight" — there is active certification
     * workflow happening for the election in this state.
     */
    const IN_PROGRESS_STATUSES = ['active', 'certifying', 'results_pending'];

    protected static function booted(): void
    {
        static::creating(function (Election $election) {
            if (empty($election->slug)) {
                $election->slug = Str::slug($election->name);
            }
        });

        $bust = function (Election $election): void {
            self::forgetPublicCaches($election->id, $election->status);
        };

        static::saved($bust);
        static::deleted($bust);
        if (method_exists(static::class, 'restored')) {
            static::restored($bust);
        }
    }

    /**
     * The election currently "in flight" for active certification
     * workflow.
     *
     * IMPORTANT: this is the single source of truth for "which election
     * is current". Every dashboard / approval queue / analytics page
     * MUST resolve the election through this method (or
     * currentOrLatestCertified() below) rather than writing its own
     * Election::where(...) query. Resolving the "current" election
     * differently on different pages — e.g. ordering by created_at on
     * one page and start_date on another, or restricting to only
     * status = 'active' on one page while another accepts
     * 'active'/'certifying'/'results_pending' — is what causes
     * different pages to silently disagree about which election is
     * current and display stale/inconsistent figures.
     */
    public static function current(): ?self
    {
        return static::whereIn('status', self::IN_PROGRESS_STATUSES)
            ->latest('start_date')
            ->first();
    }

    /**
     * The election that should be treated as "the current one" for
     * display purposes: the in-flight election if one exists, otherwise
     * the most recently closed (certified) election. Used by views that
     * need to keep showing final results after an election has been
     * closed (e.g. the Chairman's dashboard/analytics/publish pages).
     */
    public static function currentOrLatestCertified(): ?self
    {
        return static::current()
            ?? static::where('status', 'certified')->latest('start_date')->first();
    }

    public static function forgetPublicCaches(int $electionId, ?string $status = null): void
    {
        $statuses = array_unique(array_filter([
            $status,
            'active',
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
        foreach (['draft', 'active', 'certifying', 'results_pending', 'certified', 'archived'] as $status) {
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
    public function isClosed(): bool { return in_array($this->status, ['certified', 'archived']); }

    public function allowsResultSubmission(): bool
    {
        return in_array($this->status, ['active', 'results_pending', 'certifying']);
    }

    public function allowsPublicDisplay(): bool {
        return $this->allow_provisional_public_display
            && in_array($this->status, ['results_pending', 'certifying', 'certified']);
    }
}