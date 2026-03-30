import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';

export default function Settings({ auth, settings = {} }) {
    // Use ?? (nullish coalescing) instead of || to correctly handle false values
    const { data, setData, post, processing, errors } = useForm({
        system_name:            settings.system_name            ?? 'IEC NERTP',
        system_email:           settings.system_email           ?? 'admin@iec.gm',
        timezone:               settings.timezone               ?? 'UTC',
        require_2fa:            settings.require_2fa            ?? false,
        gps_validation_enabled: settings.gps_validation_enabled ?? true,
        max_file_size:          settings.max_file_size           ?? 10240,
        sms_enabled:            settings.sms_enabled            ?? false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/settings');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <div className="mb-6">
                    <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                       Back to Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">System Settings</h1>
                </div>

                {/* Success/error flash */}
                {errors && Object.keys(errors).length > 0 && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
                        {Object.values(errors).map((err, i) => (
                            <p key={i} className="text-red-300 text-sm">{err}</p>
                        ))}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* General Settings */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">General Settings</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">System Name</label>
                                <input
                                    type="text"
                                    value={data.system_name}
                                    onChange={(e) => setData('system_name', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                />
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">System Email</label>
                                <input
                                    type="email"
                                    value={data.system_email}
                                    onChange={(e) => setData('system_email', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                />
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Timezone</label>
                                <select
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                >
                                    <option value="UTC">UTC</option>
                                    <option value="Africa/Dakar">Africa/Dakar (GMT+0)</option>
                                    <option value="Africa/Banjul">Africa/Banjul (GMT+0)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Security */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">Security & Validation</h2>
                        <div className="space-y-4">
                            {[
                                { key: 'require_2fa',            label: 'Require 2FA for all users',                     desc: 'All users must verify via SMS' },
                                { key: 'gps_validation_enabled', label: 'Enable GPS validation for result submissions',  desc: 'Officers must be at their station' },
                                { key: 'sms_enabled',            label: 'Enable SMS notifications',                      desc: 'Send SMS alerts for key events' },
                            ].map(({ key, label, desc }) => (
                                <label key={key} className="flex items-center gap-4 p-4 bg-slate-900/30 rounded-lg cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={!!data[key]}
                                        onChange={(e) => setData(key, e.target.checked)}
                                        className="w-5 h-5 text-teal-600 bg-slate-900 border-slate-600 rounded"
                                    />
                                    <div>
                                        <div className="text-white font-medium">{label}</div>
                                        <div className="text-gray-400 text-sm">{desc}</div>
                                    </div>
                                </label>
                            ))}
                        </div>
                    </div>

                    {/* File Upload */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">File Upload Settings</h2>
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Maximum File Size (KB)
                            </label>
                            <input
                                type="number"
                                value={data.max_file_size}
                                onChange={(e) => setData('max_file_size', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                min="1024"
                                max="51200"
                            />
                            <p className="text-gray-400 text-sm mt-1">
                                Current: {(data.max_file_size / 1024).toFixed(0)} MB — Range: 1 MB to 50 MB
                            </p>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full px-8 py-4 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg"
                    >
                        {processing ? 'Saving…' : 'Save Settings'}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}