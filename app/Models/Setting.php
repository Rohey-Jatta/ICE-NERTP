<?php

namespace App\Models;

use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Bust the cached settings snapshot any time a row changes,
     * so the very next request (including the current one) sees
     * fresh values without waiting for a TTL to expire.
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(SettingsService::class)->forget());
        static::deleted(fn () => app(SettingsService::class)->forget());
    }
}