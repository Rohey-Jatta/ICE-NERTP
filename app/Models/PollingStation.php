<?php

namespace App\Models;

use App\Services\CurrentElectionResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PollingStation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // election_id intentionally REMOVED from fillable. Polling stations
        // are no longer assigned to an election at creation time — they are
        // resolved dynamically against the current operational election
        // (see CurrentElectionResolver). The column still exists on the
        // table as a nullable "last seen election" marker for historical
        // reporting, updated via markSeenUnder(), never set directly by
        // admin input.
        'ward_id', 'code', 'name', 'address',
        'latitude', 'longitude', 'registered_voters',
        'assigned_officer_id', 'is_active', 'is_test_station', 'station_photo_path',
    ];

    protected $casts = [
        'latitude' => 'decimal:8', 'longitude' => 'decimal:8',
        'is_active' => 'boolean', 'is_test_station' => 'boolean',
        'registered_voters' => 'integer',
    ];

    public function election(): BelongsTo { return $this->belongsTo(Election::class); }
    public function ward(): BelongsTo { return $this->belongsTo(AdministrativeHierarchy::class, 'ward_id'); }
    public function assignedOfficer(): BelongsTo { return $this->belongsTo(User::class, 'assigned_officer_id'); }
    public function results(): HasMany { return $this->hasMany(Result::class); }
    public function latestResult(): HasOne { return $this->hasOne(Result::class)->latestOfMany(); }

    /**
     * All active polling stations, scoped to the CURRENT operational
     * election (active/submitting/certifying). This is now the standard
     * way to get "the stations for the current election" — replaces the
     * old pattern of filtering by a hardcoded election_id.
     *
     * Note: this does NOT filter by polling_stations.election_id at all.
     * Every active station is considered part of whichever election is
     * currently operational; that column is historical metadata only.
     */
    public static function currentElectionStations()
    {
        $resolver = app(CurrentElectionResolver::class);
        $current = $resolver->current(); // throws NoCurrentElectionException if none

        return static::query()
            ->active()
            ->tap(fn($q) => $q->currentElectionContext = $current);
    }

    /**
     * Record that this station was used/touched under the given election.
     * Updates the historical "last seen election" marker. Safe to call
     * repeatedly — it's a no-op write if already pointing at this election.
     */
    public function markSeenUnder(Election $election): void
    {
        if ($this->election_id !== $election->id) {
            $this->forceFill(['election_id' => $election->id])->saveQuietly();
        }
    }

    public function isWithinGpsRadius(float $officerLat, float $officerLng, ?int $radiusMeters = null): bool {
        $radius = $radiusMeters ?? $this->resolveGpsRadiusMeters();
        $result = DB::selectOne(
            "SELECT ST_Distance(
                ST_SetSRID(ST_MakePoint(:slng, :slat), 4326)::geography,
                ST_SetSRID(ST_MakePoint(:olng, :olat), 4326)::geography
            ) as distance",
            ['slat' => $this->latitude, 'slng' => $this->longitude, 'olat' => $officerLat, 'olng' => $officerLng]
        );
        return $result->distance <= $radius;
    }

    public function scopeActive($q) { return $q->where('is_active', true); }

    /**
     * @deprecated Polling stations are no longer statically scoped to an
     * election_id. Use currentElectionStations() to get stations for the
     * current operational election. Kept temporarily for any historical
     * reporting code path that explicitly needs "stations last seen under
     * election X" — NOT for live/current-election queries.
     */
    public function scopeForElection($q, int $electionId) { return $q->where('election_id', $electionId); }

    public function scopeAssignedTo($q, int $officerId) { return $q->where('assigned_officer_id', $officerId); }

    public function getDistanceInMeters(float $lat, float $lng): float
    {
        $result = DB::selectOne(
            "SELECT ST_Distance(
                ST_SetSRID(ST_MakePoint(:slng, :slat), 4326)::geography,
                ST_SetSRID(ST_MakePoint(:olng, :olat), 4326)::geography
            ) as distance",
            ["slat" => $this->latitude, "slng" => $this->longitude, "olat" => $lat, "olng" => $lng]
        );
        return $result->distance;
    }

    /**
     * GPS validation radius now comes from the current operational
     * election rather than this station's (possibly stale) historical
     * election relation, since that relation may point at an election
     * that is no longer current.
     */
    private function resolveGpsRadiusMeters(): int
    {
        $current = app(CurrentElectionResolver::class)->currentOrNull();
        return $current?->gps_validation_radius_meters ?? 100;
    }
}