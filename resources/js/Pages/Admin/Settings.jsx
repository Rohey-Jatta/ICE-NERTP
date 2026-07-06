import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button, Field, PageHeader, Panel, inputClass } from '@/Components/AdminUI';
import SearchableSelect from '@/Components/SearchableSelect';

export default function Settings({ auth, settings = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        system_name:            settings.system_name            ?? 'IEC NERTP',
        system_email:           settings.system_email           ?? 'admin@iec.gm',
        timezone:               settings.timezone               ?? 'UTC',
        require_2fa:            settings.require_2fa            ?? false,
        gps_validation_enabled: settings.gps_validation_enabled ?? true,
        max_file_size:          settings.max_file_size           ?? 10240,
        sms_enabled:            settings.sms_enabled            ?? false,
        public_results_enabled: settings.public_results_enabled ?? true,
        provisional_banner:     settings.provisional_banner     ?? true,
        audit_retention_days:   settings.audit_retention_days   ?? 365,
        session_timeout_minutes: settings.session_timeout_minutes ?? 30,
        // PWA & Offline Sync
        background_sync_interval_minutes: settings.background_sync_interval_minutes ?? 5,
        max_offline_retries:              settings.max_offline_retries              ?? 3,
    });

    const [clearingCache, setClearingCache] = useState(false);
    const [cacheMessage, setCacheMessage] = useState(null);

    // Keep provisional_banner consistent with public_results_enabled:
    // if public results get turned off, the "show provisional labels"
    // toggle is meaningless (there's nothing public to label), so we
    // force it off too rather than letting it silently stay "on" in
    // the background.
    useEffect(() => {
        if (!data.public_results_enabled && data.provisional_banner) {
            setData('provisional_banner', false);
        }
    }, [data.public_results_enabled]);

    const handleSubmit = (event) => {
        event.preventDefault();
        post('/admin/settings');
    };

    const handleClearCache = () => {
        if (clearingCache) return;
        if (!window.confirm('Clear the application cache? This will reset cached dashboards, election resolution, and settings.')) {
            return;
        }

        setClearingCache(true);
        setCacheMessage(null);

        router.post('/admin/settings/clear-cache', {}, {
            preserveScroll: true,
            onSuccess: () => setCacheMessage({ type: 'success', text: 'Application cache cleared successfully!' }),
            onError: () => setCacheMessage({ type: 'error', text: 'Failed to clear cache. Please try again.' }),
            onFinish: () => setClearingCache(false),
        });
    };

    const securityToggles = [
        { key: 'require_2fa', label: 'Require 2FA for all users', desc: 'All users must verify with a one-time code before accessing their workspace.' },
        { key: 'gps_validation_enabled', label: 'Enable GPS validation', desc: 'Polling officers must be within the expected station area for result submissions.' },
        { key: 'sms_enabled', label: 'Enable SMS notifications', desc: 'Send SMS alerts for authentication and critical workflow events.' },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-5xl">
                <PageHeader title="System Settings" description="Configure platform identity, authentication, public result behaviour, audit policy, PWA sync, and upload limits." />

                {Object.keys(errors || {}).length > 0 && (
                    <Panel className="mb-5 border-rose-500/40 p-4">
                        {Object.values(errors).map((error, index) => (
                            <p key={index} className="text-sm text-rose-300">{error}</p>
                        ))}
                    </Panel>
                )}

                <form onSubmit={handleSubmit} className="space-y-5">
                    <Panel className="p-5">
                        <div className="mb-5">
                            <h2 className="ws-section-title">General</h2>
                            <p className="ws-section-desc">Public-facing platform metadata.</p>
                        </div>
                        <div className="ws-form-grid">
                            <Field label="System Name">
                                <input value={data.system_name} onChange={(event) => setData('system_name', event.target.value)} className={inputClass} />
                            </Field>
                            <Field label="System Email">
                                <input type="email" value={data.system_email} onChange={(event) => setData('system_email', event.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Timezone">
                                <SearchableSelect
                                    value={data.timezone}
                                    onChange={(val) => setData('timezone', val)}
                                    options={[
                                        { value: 'UTC', label: 'UTC' },
                                        { value: 'Africa/Dakar', label: 'Africa/Dakar (GMT+0)' },
                                        { value: 'Africa/Banjul', label: 'Africa/Banjul (GMT+0)' }
                                    ]}
                                    placeholder="Select timezone"
                                    className="w-full"
                                />
                            </Field>
                            <Field label="Maximum File Size (KB)">
                                <input
                                    type="number"
                                    value={data.max_file_size}
                                    onChange={(event) => setData('max_file_size', event.target.value)}
                                    className={inputClass}
                                    min="1024"
                                    max="51200"
                                />
                            </Field>
                        </div>
                    </Panel>

                    <Panel className="p-5">
                        <div className="mb-5">
                            <h2 className="ws-section-title">Security & Validation</h2>
                            <p className="ws-section-desc">Controls that affect authentication, result capture, and notifications.</p>
                        </div>
                        <div className="space-y-3">
                            {securityToggles.map(({ key, label, desc }) => (
                                <label key={key} className="ws-toggle-card">
                                    <span>
                                        <span className="block font-semibold text-slate-900">{label}</span>
                                        <span className="mt-1 block text-sm text-slate-500">{desc}</span>
                                    </span>
                                    <input
                                        type="checkbox"
                                        checked={!!data[key]}
                                        onChange={(event) => setData(key, event.target.checked)}
                                    />
                                </label>
                            ))}
                        </div>
                    </Panel>

                    <Panel className="p-5">
                        <div className="mb-5">
                            <h2 className="ws-section-title">Public Results</h2>
                            <p className="ws-section-desc">Publication settings for the homepage, map, station pages, and results screens.</p>
                        </div>
                        <div className="space-y-3">
                            {/* Master toggle — controls whether the second toggle can be used at all */}
                            <label className="ws-toggle-card">
                                <span>
                                    <span className="block font-semibold text-slate-900">Enable public results pages</span>
                                    <span className="mt-1 block text-sm text-slate-500">Allow certified figures to appear on the public website.</span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={!!data.public_results_enabled}
                                    onChange={(event) => setData('public_results_enabled', event.target.checked)}
                                />
                            </label>

                            {/* Dependent toggle — locked off and disabled unless the master toggle is on */}
                            <label
                                className={`ws-toggle-card ${!data.public_results_enabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                                title={!data.public_results_enabled ? 'Enable public results pages first' : undefined}
                            >
                                <span>
                                    <span className="block font-semibold text-slate-900">Show provisional results labels</span>
                                    <span className="mt-1 block text-sm text-slate-500">
                                        Keep public pages clearly marked while certification is still in progress.
                                        {!data.public_results_enabled && (
                                            <span className="block text-amber-600 mt-1">Requires "Enable public results pages" to be on.</span>
                                        )}
                                    </span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={!!data.provisional_banner}
                                    disabled={!data.public_results_enabled}
                                    onChange={(event) => setData('provisional_banner', event.target.checked)}
                                />
                            </label>
                        </div>
                    </Panel>

                    <Panel className="p-5">
                        <div className="mb-5">
                            <h2 className="ws-section-title">PWA & Offline Sync</h2>
                            <p className="ws-section-desc">Controls how the polling-officer PWA queues and retries result submissions while offline.</p>
                        </div>
                        <div className="ws-form-grid">
                            <Field label="Background Sync Interval (Minutes)">
                                <input
                                    type="number"
                                    value={data.background_sync_interval_minutes}
                                    onChange={(event) => setData('background_sync_interval_minutes', event.target.value)}
                                    className={inputClass}
                                    min="1"
                                    max="1440"
                                />
                            </Field>
                            <Field label="Max Offline Retries">
                                <input
                                    type="number"
                                    value={data.max_offline_retries}
                                    onChange={(event) => setData('max_offline_retries', event.target.value)}
                                    className={inputClass}
                                    min="1"
                                    max="20"
                                />
                            </Field>
                        </div>
                    </Panel>

                    <Panel className="p-5">
                        <div className="mb-5">
                            <h2 className="ws-section-title">Operations</h2>
                            <p className="ws-section-desc">Administrative limits that help control evidence retention and workspace sessions.</p>
                        </div>
                        <div className="ws-form-grid">
                            <Field label="Audit Retention (Days)">
                                <input
                                    type="number"
                                    value={data.audit_retention_days}
                                    onChange={(event) => setData('audit_retention_days', event.target.value)}
                                    className={inputClass}
                                    min="30"
                                    max="2555"
                                />
                            </Field>
                            <Field label="Session Timeout (Minutes)">
                                <input
                                    type="number"
                                    value={data.session_timeout_minutes}
                                    onChange={(event) => setData('session_timeout_minutes', event.target.value)}
                                    className={inputClass}
                                    min="5"
                                    max="240"
                                />
                            </Field>
                        </div>
                    </Panel>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>{processing ? 'Saving...' : 'Save Settings'}</Button>
                    </div>
                </form>

                {/* System Maintenance is intentionally outside the settings <form> —
                    clearing cache is an immediate action, not a field to save. */}
                <Panel className="mt-5 p-5">
                    <div className="mb-5">
                        <h2 className="ws-section-title">System Maintenance</h2>
                        <p className="ws-section-desc">Force-clear all cached application data (dashboards, election resolution, settings snapshot).</p>
                    </div>

                    {cacheMessage && (
                        <div className={`mb-4 rounded-md border px-4 py-3 text-sm ${
                            cacheMessage.type === 'success'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-rose-200 bg-rose-50 text-rose-700'
                        }`}>
                            {cacheMessage.text}
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4">
                        <div>
                            <p className="font-semibold text-slate-900">Clear Application Cache</p>
                            <p className="mt-1 text-sm text-slate-500">
                                Use this if dashboards or public pages appear to be showing stale data after a settings or election change.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="warning"
                            onClick={handleClearCache}
                            disabled={clearingCache}
                        >
                            {clearingCache ? 'Clearing…' : 'Clear Cache'}
                        </Button>
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}