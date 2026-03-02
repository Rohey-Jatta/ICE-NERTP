<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Result extends Model
{
    use HasFactory;

    const STATUS_SUBMITTED                = 'submitted';
    const STATUS_PENDING_PARTY_ACCEPTANCE = 'pending_party_acceptance';
    const STATUS_PENDING_WARD             = 'pending_ward';
    const STATUS_WARD_CERTIFIED           = 'ward_certified';
    const STATUS_PENDING_CONSTITUENCY     = 'pending_constituency';
    const STATUS_CONSTITUENCY_CERTIFIED   = 'constituency_certified';
    const STATUS_PENDING_ADMIN_AREA       = 'pending_admin_area';
    const STATUS_ADMIN_AREA_CERTIFIED     = 'admin_area_certified';
    const STATUS_PENDING_NATIONAL         = 'pending_national';
    const STATUS_NATIONALLY_CERTIFIED     = 'nationally_certified';
    const STATUS_REJECTED                 = 'rejected';

    protected $fillable = [
        'polling_station_id', 'election_id', 'submission_uuid',
        'total_registered_voters', 'total_votes_cast', 'valid_votes',
        'rejected_votes', 'disputed_votes', 'result_sheet_photo_path',
        'result_sheet_photo_hash', 'submitted_latitude', 'submitted_longitude',
        'gps_accuracy_meters', 'gps_validated', 'certification_status',
        'rejection_count', 'last_rejection_reason', 'last_rejected_by',
        'last_rejected_at', 'submitted_offline', 'offline_queued_at',
        'submitted_by', 'submitted_at', 'version', 'nationally_certified_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime', 'offline_queued_at' => 'datetime',
        'last_rejected_at' => 'datetime', 'nationally_certified_at' => 'datetime',
        'gps_validated' => 'boolean', 'submitted_offline' => 'boolean',
    ];

    public function pollingStation(): BelongsTo { return $this->belongsTo(PollingStation::class); }
    public function election(): BelongsTo { return $this->belongsTo(Election::class); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function candidateVotes(): HasMany { return $this->hasMany(ResultCandidateVote::class); }
    public function certifications(): HasMany { return $this->hasMany(ResultCertification::class); }
    public function versions(): HasMany { return $this->hasMany(ResultVersion::class); }
    public function partyAcceptances(): HasMany { return $this->hasMany(PartyAcceptance::class); }
    public function latestCertification(): HasOne { return $this->hasOne(ResultCertification::class)->latestOfMany(); }

    public function scopePendingWard($q) { return $q->where('certification_status', self::STATUS_PENDING_WARD); }
    public function scopePendingConstituency($q) { return $q->where('certification_status', self::STATUS_PENDING_CONSTITUENCY); }
    public function scopePendingNational($q) { return $q->where('certification_status', self::STATUS_PENDING_NATIONAL); }
    public function scopeNationallyCertified($q) { return $q->where('certification_status', self::STATUS_NATIONALLY_CERTIFIED); }
    public function scopeForElection($q, int $id) { return $q->where('election_id', $id); }

    public function isEditable(): bool {
        return in_array($this->certification_status, [self::STATUS_SUBMITTED, self::STATUS_REJECTED]);
    }
    public function isNationallyCertified(): bool { return $this->certification_status === self::STATUS_NATIONALLY_CERTIFIED; }
    public function isRejected(): bool { return $this->certification_status === self::STATUS_REJECTED; }
    public function isPubliclyVisible(): bool {
        return !in_array($this->certification_status, [self::STATUS_SUBMITTED, self::STATUS_REJECTED]);
    }
    public function getNextStatus(): ?string {
        $map = [
            self::STATUS_PENDING_PARTY_ACCEPTANCE => self::STATUS_PENDING_WARD,
            self::STATUS_PENDING_WARD             => self::STATUS_WARD_CERTIFIED,
            self::STATUS_WARD_CERTIFIED           => self::STATUS_PENDING_CONSTITUENCY,
            self::STATUS_PENDING_CONSTITUENCY     => self::STATUS_CONSTITUENCY_CERTIFIED,
            self::STATUS_CONSTITUENCY_CERTIFIED   => self::STATUS_PENDING_ADMIN_AREA,
            self::STATUS_PENDING_ADMIN_AREA       => self::STATUS_ADMIN_AREA_CERTIFIED,
            self::STATUS_ADMIN_AREA_CERTIFIED     => self::STATUS_PENDING_NATIONAL,
            self::STATUS_PENDING_NATIONAL         => self::STATUS_NATIONALLY_CERTIFIED,
        ];
        return $map[$this->certification_status] ?? null;
    }
    public function getTurnoutPercentage(): float {
        if ($this->total_registered_voters === 0) return 0.0;
        return round(($this->total_votes_cast / $this->total_registered_voters) * 100, 2);
    }

}