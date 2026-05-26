import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { Button, Field, PageHeader, Panel, inputClass } from '@/Components/AdminUI';

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
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post('/admin/settings');
    };

    const securityToggles = [
        { key: 'require_2fa', label: 'Require 2FA for all users', desc: 'All users must verify with a one-time code before accessing their workspace.' },
        { key: 'gps_validation_enabled', label: 'Enable GPS validation', desc: 'Polling officers must be within the expected station area for result submissions.' },
        { key: 'sms_enabled', label: 'Enable SMS notifications', desc: 'Send SMS alerts for authentication and critical workflow events.' },
    ];

    const publicationToggles = [
        { key: 'public_results_enabled', label: 'Enable public results pages', desc: 'Allow certified or provisional figures to appear on the public website.' },
        { key: 'provisional_banner', label: 'Show provisional results labels', desc: 'Keep public pages clearly marked while certification is still in progress.' },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-5xl">
                <PageHeader title="System Settings" description="Configure platform identity, authentication, public result behaviour, audit policy, and upload limits." />

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
                                <select value={data.timezone} onChange={(event) => setData('timezone', event.target.value)} className={inputClass}>
                                    <option value="UTC">UTC</option>
                                    <option value="Africa/Dakar">Africa/Dakar (GMT+0)</option>
                                    <option value="Africa/Banjul">Africa/Banjul (GMT+0)</option>
                                </select>
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
                            {publicationToggles.map(({ key, label, desc }) => (
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
            </div>
        </AppLayout>
    );
}
