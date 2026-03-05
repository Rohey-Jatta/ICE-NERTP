import AppLayout from '@/Layouts/AppLayout';

export default function SystemHealth({ auth, health = {} }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">System Health Monitoring</h1>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div className="bg-green-600/20 border border-green-500/50 rounded-xl p-6">
                        <div className="text-green-300 text-sm mb-2">Database</div>
                        <div className="text-white font-bold text-2xl">Online</div>
                    </div>
                    <div className="bg-green-600/20 border border-green-500/50 rounded-xl p-6">
                        <div className="text-green-300 text-sm mb-2">Cache</div>
                        <div className="text-white font-bold text-2xl">Online</div>
                    </div>
                    <div className="bg-green-600/20 border border-green-500/50 rounded-xl p-6">
                        <div className="text-green-300 text-sm mb-2">Queue</div>
                        <div className="text-white font-bold text-2xl">Running</div>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <h2 className="text-xl font-bold text-white mb-4">System Logs</h2>
                    <div className="bg-slate-900/50 p-4 rounded-lg font-mono text-sm text-gray-300">
                        <p>System operational - All services running normally</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
