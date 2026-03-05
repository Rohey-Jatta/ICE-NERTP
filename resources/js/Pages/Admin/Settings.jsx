import AppLayout from '@/Layouts/AppLayout';

export default function Settings({ auth, settings = {} }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <h1 className="text-3xl font-bold text-white mb-6">System Settings</h1>

                <div className="space-y-6">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">General Settings</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-gray-300 mb-2">System Name</label>
                                <input
                                    type="text"
                                    defaultValue={settings.name || 'IEC NERTP'}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                />
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2">System Email</label>
                                <input
                                    type="email"
                                    defaultValue={settings.email || 'admin@iec.gm'}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">Security Settings</h2>
                        <div className="space-y-4">
                            <label className="flex items-center gap-3">
                                <input type="checkbox" defaultChecked className="w-5 h-5" />
                                <span className="text-white">Require 2FA for all users</span>
                            </label>
                            <label className="flex items-center gap-3">
                                <input type="checkbox" defaultChecked className="w-5 h-5" />
                                <span className="text-white">Enable GPS validation</span>
                            </label>
                        </div>
                    </div>

                    <button className="w-full px-8 py-4 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        Save Settings
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
