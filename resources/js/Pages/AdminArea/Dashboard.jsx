import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function AdminAreaDashboard({ auth, adminArea, pendingResults, statistics }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Administrative Area Approver Dashboard</h1>

                <div className="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                    <div className="bg-slate-500/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-2xl font-bold text-white">{adminArea?.name || 'N/A'}</div>
                        <div className="text-gray-400 text-sm">Admin Area</div>
                    </div>

                     <div className="bg-slate-500/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-2xl font-bold text-white">{pendingResults || 0}</div>
                        <div className="text-gray-400 text-sm">Pending</div>
                    </div>

                     <div className="bg-slate-500/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-2xl font-bold text-slate-300">{statistics?.approved || 0}</div>
                        <div className="text-gray-400 text-sm">Approved</div>
                    </div>

                    <div className="bg-slate-500/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-2xl font-bold text-white">{statistics?.constituencies || 0}</div>
                        <div className="text-gray-400 text-sm">Constituencies</div>
                    </div>

                    <div className="bg-slate-500/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-2xl font-bold text-white">{statistics?.progress || 0}%</div>
                        <div className="text-gray-400 text-sm">Progress</div>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                    <h2 className="text-2xl font-bold text-white mb-6">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/admin-area/approval-queue"
                            className="p-6 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                        >
                            <div className="text-xl font-bold text-white">Approval Queue</div>
                            <div className="text-teal-200 text-sm">Constituency-certified</div>
                        </Link>

                        <Link
                            href="/admin-area/constituency-breakdowns"
                            className="p-6 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                        >
                            <div className="text-xl font-bold text-white">Breakdowns</div>
                            <div className="text-gray-300 text-sm">Constituency data</div>
                        </Link>

                        <Link
                            href="/admin-area/analytics"
                            className="p-6 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                        >
                            <div className="text-xl font-bold text-white">Analytics</div>
                            <div className="text-gray-300 text-sm">Full analytics</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
