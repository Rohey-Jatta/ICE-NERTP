<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings
    ) {}

    /**
     * GET /admin/settings
     */
    public function edit()
    {
        return Inertia::render('Admin/Settings', [
            'auth'     => ['user' => Auth::user()],
            'settings' => $this->settings->all(),
        ]);
    }

    /**
     * POST /admin/settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'system_name'                      => 'required|string|max:255',
            'system_email'                      => 'required|email',
            'timezone'                          => 'required|string',
            'require_2fa'                       => 'boolean',
            'gps_validation_enabled'            => 'boolean',
            'max_file_size'                     => 'required|integer|min:1024|max:51200',
            'sms_enabled'                        => 'boolean',
            'public_results_enabled'            => 'boolean',
            'provisional_banner'                => 'boolean',
            'audit_retention_days'              => 'required|integer|min:30|max:2555',
            'session_timeout_minutes'           => 'required|integer|min:5|max:240',
            'background_sync_interval_minutes'  => 'required|integer|min:1|max:1440',
            'max_offline_retries'               => 'required|integer|min:1|max:20',
        ]);

        try {
            $this->settings->setMany($validated);

            AuditLog::record(
                action: 'settings.updated',
                event:  'updated',
                module: 'System',
                extra:  ['outcome' => 'success', 'new_values' => $validated]
            );

            return back()->with('success', 'Settings saved successfully!');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Failed to save settings: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /admin/settings/clear-cache
     */
    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            $this->settings->forget();

            AuditLog::record(
                action: 'settings.cache_cleared',
                event:  'action',
                module: 'System',
                extra:  ['outcome' => 'success']
            );

            return back()->with('success', 'Application cache cleared successfully!');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Failed to clear cache: ' . $e->getMessage()]);
        }
    }
}