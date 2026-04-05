import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function AdminAreaDashboard({ auth, adminArea, pendingResults, statistics }) {
    const progress      = statistics?.progress    || 0;
    const awaitingBelow = statistics?.awaitingBelow || 0;

    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-white">Administrative Area Approver Dashboard</h1>
                    {adminArea?.name && (
                        <p className="text-teal-300 mt-1 text-lg">{adminArea.name}</p>
                    )}
                    <p className="text-gray-400 text-sm mt-1">
                        Review and certify constituency-level results at the administrative area level
                    </p>
                </div>

                {/* Pending alert — only show when results are actually in THIS queue */}
                {pendingResults > 0 && (
                    <div className="mb-4 p-4 bg-amber-500/10 border border-amber-500/40 rounded-xl flex items-center gap-3">
                        <div className="w-3 h-3 bg-amber-400 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-amber-300">
                            <strong>{pendingResults} result{pendingResults !== 1 ? 's' : ''}</strong> are ready and awaiting your admin-area certification
                        </p>
                        <Link
                            href="/admin-area/approval-queue"
                            className="ml-auto px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-lg whitespace-nowrap"
                        >
                            Review Now →
                        </Link>
                    </div>
                )}

                {/* Pipeline notice — results still progressing through ward/constituency */}
                {awaitingBelow > 0 && pendingResults === 0 && (
                    <div className="mb-4 p-4 bg-blue-500/10 border border-blue-500/30 rounded-xl flex items-center gap-3">
                        <div className="w-3 h-3 bg-blue-400 rounded-full flex-shrink-0" />
                        <p className="text-blue-300">
                            <strong>{awaitingBelow} result{awaitingBelow !== 1 ? 's' : ''}</strong> still progressing through ward/constituency certification — they will appear here once constituency-certified
                        </p>
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-amber-300">{pendingResults || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Ready for Review</div>
                        <div className="text-gray-500 text-xs mt-0.5">Pending your decision</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-teal-300">{statistics?.approved || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Area Certified</div>
                        <div className="text-gray-500 text-xs mt-0.5">Sent to Chairman</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-blue-300">{awaitingBelow || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">In Pipeline</div>
                        <div className="text-gray-500 text-xs mt-0.5">At ward/constituency level</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-white">{statistics?.constituencies || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Constituencies</div>
                        <div className="text-gray-500 text-xs mt-0.5">In your area</div>
                    </div>
                </div>

                {/* Progress bar */}
                {((statistics?.approved || 0) + (pendingResults || 0)) > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-8">
                        <div className="flex justify-between items-center mb-3">
                            <span className="text-gray-300 font-semibold">Admin-Area Certification Progress</span>
                            <span className="text-white font-bold">{progress}%</span>
                        </div>
                        <div className="w-full bg-slate-700 rounded-full h-4">
                            <div
                                className="bg-gradient-to-r from-teal-500 to-teal-400 h-4 rounded-full transition-all duration-700"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <p className="text-gray-500 text-xs mt-2">
                            {statistics?.approved || 0} results certified at admin-area level
                        </p>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <h2 className="text-xl font-bold text-white mb-5">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/admin-area/approval-queue"
                            className="group p-5 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 rounded-xl transition-all"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-amber-500/20 rounded-lg flex items-center justify-center text-amber-400">⏳</div>
                                <div className="text-lg font-bold text-white">Approval Queue</div>
                            </div>
                            <div className="text-amber-300 text-sm">
                                {pendingResults > 0
                                    ? `${pendingResults} result${pendingResults !== 1 ? 's' : ''} ready for review`
                                    : awaitingBelow > 0
                                    ? `${awaitingBelow} still in ward/constituency pipeline`
                                    : 'No results pending'}
                            </div>
                        </Link>

                        <Link
                            href="/admin-area/constituency-breakdowns"
                            className="group p-5 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 rounded-xl transition-all"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center text-blue-400">🗂</div>
                                <div className="text-lg font-bold text-white">Constituency Breakdowns</div>
                            </div>
                            <div className="text-blue-300 text-sm">
                                View full pipeline status by constituency
                            </div>
                        </Link>

                        <Link
                            href="/admin-area/analytics"
                            className="group p-5 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 rounded-xl transition-all"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-purple-500/20 rounded-lg flex items-center justify-center text-purple-400">📊</div>
                                <div className="text-lg font-bold text-white">Analytics</div>
                            </div>
                            <div className="text-purple-300 text-sm">Full admin-area statistics</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}