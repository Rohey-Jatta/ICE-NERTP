import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function ConstituencyDashboard({ auth, constituency, pendingResults, statistics }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Constituency Approver Dashboard</h1>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-white">{constituency?.name || 'N/A'}</div>
                        <div className="text-gray-400 text-sm">Your Constituency</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-pink-300">{pendingResults || 0}</div>
                        <div className="text-gray-400 text-sm">Pending Approval</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-pink-300">{statistics?.approved || 0}</div>
                        <div className="text-gray-400 text-sm">Approved</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-pink-300">{statistics?.totalWards || 0}</div>
                        <div className="text-gray-400 text-sm">Wards</div>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                    <h2 className="text-2xl font-bold text-white mb-6">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link href="/constituency/approval-queue" className="p-6 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors block">
                            <div className="text-xl font-bold text-white">Approval Queue</div>
                            <div className="text-blue-200 text-sm">Ward-certified results</div>
                        </Link>
                        <Link href="/constituency/ward-breakdowns" className="p-6 bg-pink-600 hover:bg-pink-700 rounded-lg transition-colors block">
                            <div className="text-xl font-bold text-white">Ward Breakdowns</div>
                            <div className="text-pink-200 text-sm">Detailed ward view</div>
                        </Link>
                        <Link href="/constituency/reports" className="p-6 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors block">
                            <div className="text-xl font-bold text-white">Reports</div>
                            <div className="text-blue-200 text-sm">Generate reports</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}