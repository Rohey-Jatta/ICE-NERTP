import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';

export default function Settings({ auth, settings = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        system_name: settings.system_name || 'IEC NERTP',
        system_email: settings.system_email || 'admin@iec.gm',
        timezone: settings.timezone || 'UTC',
        require_2fa: settings.require_2fa || false,
        gps_validation_enabled: settings.gps_validation_enabled || true,
        max_file_size: settings.max_file_size || 10240,
        sms_enabled: settings.sms_enabled || false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/settings');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <h1 className="text-3xl font-bold text-white mb-6">System Settings</h1>

                <form onSubmit={handleSubmit} className="space-y-6">
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
                                {errors.system_name && <p className="text-red-400 text-sm mt-1">{errors.system_name}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">System Email</label>
                                <input
                                    type="email"
                                    value={data.system_email}
                                    onChange={(e) => setData('system_email', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                />
                                {errors.system_email && <p className="text-red-400 text-sm mt-1">{errors.system_email}</p>}
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
                                {errors.timezone && <p className="text-red-400 text-sm mt-1">{errors.timezone}</p>}
                            </div>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">Security & Validation</h2>
                        <div className="space-y-4">
                            <label className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.require_2fa}
                                    onChange={(e) => setData('require_2fa', e.target.checked)}
                                    className="w-5 h-5 text-teal-600 bg-slate-900 border-slate-600 rounded"
                                />
                                <span className="text-white">Require 2FA for all users</span>
                            </label>
                            <label className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.gps_validation_enabled}
                                    onChange={(e) => setData('gps_validation_enabled', e.target.checked)}
                                    className="w-5 h-5 text-teal-600 bg-slate-900 border-slate-600 rounded"
                                />
                                <span className="text-white">Enable GPS validation for result submissions</span>
                            </label>
                            <label className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.sms_enabled}
                                    onChange={(e) => setData('sms_enabled', e.target.checked)}
                                    className="w-5 h-5 text-teal-600 bg-slate-900 border-slate-600 rounded"
                                />
                                <span className="text-white">Enable SMS notifications</span>
                            </label>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">File Upload Settings</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Maximum File Size (KB)</label>
                                <input
                                    type="number"
                                    value={data.max_file_size}
                                    onChange={(e) => setData('max_file_size', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    min="1024"
                                    max="51200"
                                />
                                <p className="text-gray-400 text-sm mt-1">Maximum file size for photo uploads (1024-51200 KB)</p>
                                {errors.max_file_size && <p className="text-red-400 text-sm mt-1">{errors.max_file_size}</p>}
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full px-8 py-4 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600 text-white font-bold rounded-lg"
                    >
                        {processing ? 'Saving...' : 'Save Settings'}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
