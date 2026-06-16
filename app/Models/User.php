<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'employee_id',
        'status',
        'two_factor_enabled',
        'two_factor_secret',
        'must_change_password',
        'bound_device_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'             => 'hashed',
        'two_factor_enabled'   => 'boolean',
        'must_change_password' => 'boolean',
    ];

    // Relationships

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function boundDevice()
    {
        return $this->belongsTo(Device::class, 'bound_device_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function partyRepresentative()
    {
        return $this->hasOne(\App\Models\PartyRepresentative::class);
    }

    public function electionMonitor()
    {
        return $this->hasOne(ElectionMonitor::class);
    }

    /**
     * The polling station this user is assigned to as an officer.
     */
    public function assignedStation()
    {
        return $this->hasOne(PollingStation::class, 'assigned_officer_id');
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if (auth()->check() && auth()->id() === $this->id) {
            $role = $this->getRoleNames()->first();

            $array['roles'] = $role ? [['name' => $role]] : [];
            $array['permission_names'] = $this->getPermissionNamesAttribute();
            $array['dashboard_url'] = $this->getDashboardUrlAttribute();
        }

        return $array;
    }

    public function getPermissionNamesAttribute(): array
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->values()
            ->all();
    }

    public function getDashboardUrlAttribute(): string
    {
        return match ($this->getRoleNames()->first()) {
            'polling-officer'       => '/officer/dashboard',
            'ward-approver'         => '/ward/dashboard',
            'constituency-approver' => '/constituency/dashboard',
            'admin-area-approver'   => '/admin-area/dashboard',
            'iec-chairman'          => '/chairman/dashboard',
            'iec-administrator'     => '/admin/dashboard',
            'party-representative'  => '/party/dashboard',
            'election-monitor'      => '/monitor/dashboard',
            default                 => '/',
        };
    }
}