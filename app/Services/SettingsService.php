<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * SettingsService — single source of truth for global application settings.
 *
 * Settings are cached FOREVER under one key. The cache is only rebuilt
 * when a Setting row is saved/deleted (see App\Models\Setting::booted())
 * or when forget()/setMany() is called explicitly. This means normal
 * requests never touch the settings table at all.
 */
class SettingsService
{
    public const CACHE_KEY = 'app_settings_v1';

    /**
     * Defaults used when no value has been persisted yet.
     */
    public const DEFAULTS = [
        // General
        'system_name'            => 'IEC NERTP',
        'system_email'           => 'admin@iec.gm',
        'timezone'               => 'UTC',
        'max_file_size'          => 10240,

        // Security & Validation
        'require_2fa'            => false,
        'gps_validation_enabled' => true,
        'sms_enabled'            => false,

        // Public Results
        'public_results_enabled' => true,
        'provisional_banner'     => true,

        // Operations
        'audit_retention_days'    => 365,
        'session_timeout_minutes' => 30,

        // PWA & Offline Sync
        'background_sync_interval_minutes' => 5,
        'max_offline_retries'              => 3,
    ];

    private const BOOLEAN_KEYS = [
        'require_2fa',
        'gps_validation_enabled',
        'sms_enabled',
        'public_results_enabled',
        'provisional_banner',
    ];

    private const INTEGER_KEYS = [
        'max_file_size',
        'audit_retention_days',
        'session_timeout_minutes',
        'background_sync_interval_minutes',
        'max_offline_retries',
    ];

    /**
     * All settings, merged with defaults, cached forever.
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return Setting::query()->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                // Table not migrated yet, DB unavailable, etc. — never let a
                // settings read take down every page on the site.
                \Illuminate\Support\Facades\Log::warning('[SettingsService] Failed to load settings, falling back to defaults: ' . $e->getMessage());
                return [];
            }
        });

        $merged = array_merge(self::DEFAULTS, $stored);

        foreach ($merged as $key => $value) {
            if (in_array($key, self::BOOLEAN_KEYS, true)) {
                $merged[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array($key, self::INTEGER_KEYS, true)) {
                $merged[$key] = (int) $value;
            }
        }

        return $merged;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Persist many settings at once (used by the Settings form) and
     * immediately rebuild the cache.
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue; // ignore unknown keys defensively
            }

            $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

            Setting::updateOrCreate(['key' => $key], ['value' => $stored]);
        }

        $this->forget();
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * Clear the cached settings snapshot so the next read rebuilds it
     * from the database.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}