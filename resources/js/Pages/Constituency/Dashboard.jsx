import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function ConstituencyDashboard({ auth, constituency, statistics }) {
    const progress = statistics?.progress || 0;

    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-white">Constituency Approver Dashboard</h1>
                    {constituency?.name && (
                        <p className="text-teal-300 mt-1 text-lg">{constituency.name}</p>
                    )}
                    <p className="text-gray-400 text-sm mt-1">
                        Review and certify ward-certified results at constituency level
                    </p>
                </div>

                {/* Alert if pending */}
                {statistics?.pending > 0 && (
                    <div className="mb-6 p-4 bg-amber-500/10 border border-amber-500/40 rounded-xl flex items-center gap-3">
                        <div className="w-3 h-3 bg-amber-400 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-amber-300">
                            <strong>{statistics.pending} result{statistics.pending !== 1 ? 's' : ''}</strong> awaiting your constituency certification
                        </p>
                        <Link
                            href="/constituency/approval-queue"
                            className="ml-auto px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-lg"
                        >
                            Review Now →
                        </Link>
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-amber-300">{statistics?.pending || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Pending Certification</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-teal-300">{statistics?.certified || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Constituency Certified</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-red-300">{statistics?.rejected || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Returned to Ward</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-white">{statistics?.totalWards || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Wards</div>
                    </div>
                </div>

                {/* Progress bar */}
                {statistics?.certified > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-8">
                        <div className="flex justify-between items-center mb-3">
                            <span className="text-gray-300 font-semibold">Certification Progress</span>
                            <span className="text-white font-bold">{progress}%</span>
                        </div>
                        <div className="w-full bg-slate-700 rounded-full h-4">
                            <div
                                className="bg-gradient-to-r from-teal-500 to-teal-400 h-4 rounded-full transition-all duration-700"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <h2 className="text-xl font-bold text-white mb-5">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/constituency/approval-queue?filter=pending"
                            className="group p-5 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 rounded-xl transition-all block"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-amber-500/20 rounded-lg flex items-center justify-center text-amber-400">⏳</div>
                                <div className="text-lg font-bold text-white">Approval Queue</div>
                            </div>
                            <div className="text-amber-300 text-sm">
                                {statistics?.pending > 0
                                    ? `${statistics.pending} ward-certified result${statistics.pending !== 1 ? 's' : ''} pending`
                                    : 'No results pending'}
                            </div>
                        </Link>

                        <Link
                            href="/constituency/ward-breakdowns"
                            className="group p-5 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 rounded-xl transition-all block"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center text-blue-400">📊</div>
                                <div className="text-lg font-bold text-white">Ward Breakdowns</div>
                            </div>
                            <div className="text-blue-300 text-sm">
                                {statistics?.totalWards || 0} wards in your constituency
                            </div>
                        </Link>

                        <Link
                            href="/constituency/reports"
                            className="group p-5 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 rounded-xl transition-all block"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-purple-500/20 rounded-lg flex items-center justify-center text-purple-400">📄</div>
                                <div className="text-lg font-bold text-white">Reports</div>
                            </div>
                            <div className="text-purple-300 text-sm">Generate constituency reports</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
