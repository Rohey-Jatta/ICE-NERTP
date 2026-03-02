<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdministrativeHierarchy extends Model
{
    use HasFactory;

    protected $table = 'administrative_hierarchy';

    protected $fillable = [
        'election_id', 'level', 'parent_id', 'name', 'code', 'slug',
        'path', 'depth', 'center_latitude', 'center_longitude',
        'registered_voters', 'assigned_approver_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean', 'depth' => 'integer',
        'registered_voters' => 'integer',
        'center_latitude' => 'decimal:8', 'center_longitude' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::creating(fn($n) => $n->slug = $n->slug ?? Str::slug($n->name));
        static::created(function (AdministrativeHierarchy $node) {
            $parentPath = $node->parent?->path ?? '/';
            $node->path = $parentPath . $node->id . '/';
            $node->depth = $node->parent ? $node->parent->depth + 1 : 0;
            $node->saveQuietly();
        });
    }

    public function election(): BelongsTo { return $this->belongsTo(Election::class); }
    public function parent(): BelongsTo { return $this->belongsTo(AdministrativeHierarchy::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(AdministrativeHierarchy::class, 'parent_id'); }
    public function assignedApprover(): BelongsTo { return $this->belongsTo(User::class, 'assigned_approver_id'); }
    public function pollingStations(): HasMany { return $this->hasMany(PollingStation::class, 'ward_id'); }
    public function resultCertifications(): HasMany { return $this->hasMany(ResultCertification::class, 'hierarchy_node_id'); }

    public function scopeDescendantsOf($query, AdministrativeHierarchy $node) {
        return $query->where('path', 'like', $node->path . '%')->where('id', '!=', $node->id);
    }

    public function isWard(): bool { return $this->level === 'ward'; }
    public function isConstituency(): bool { return $this->level === 'constituency'; }
    public function isAdminArea(): bool { return $this->level === 'admin_area'; }
    public function isNational(): bool { return $this->level === 'national'; }
    public function getAncestorIds(): array { return array_filter(explode('/', trim($this->path, '/'))); }
}
