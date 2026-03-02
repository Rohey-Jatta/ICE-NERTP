<?php

namespace App\Models;

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
        'election_id', 'ward_id', 'code', 'name', 'address',
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

    public function isWithinGpsRadius(float $officerLat, float $officerLng, ?int $radiusMeters = null): bool {
        $radius = $radiusMeters ?? $this->election->gps_validation_radius_meters ?? 100;
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
}
