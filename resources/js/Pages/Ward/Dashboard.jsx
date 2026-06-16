import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';
import { useNotifications, ToastContainer } from '@/Components/Notifications';

export default function WardDashboard({ auth, ward, pendingResults, statistics }) {
    const progress = statistics?.progress || 0;
    const [refreshing, setRefreshing] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    const { toasts, removeNotification, notify } = useNotifications();

    // Auto-refresh every 30 seconds
    useAutoRefreshWithVisibility({
        url: '/ward/dashboard',
        interval: 30000,
        preserveScroll: true,
        preserveState: true,
        onBeforeRefresh: () => setRefreshing(true),
        onAfterRefresh: () => {
            setRefreshing(false);
            setLastRefreshTime(new Date());
            notify.info('Dashboard updated');
        },
    });

    return (
        <AppLayout user={auth.user}>
            <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-iec-navy">Ward Approver Dashboard</h1>
                    {ward?.name && (
                        <p className="text-iec-pink-600 mt-1 text-lg">{ward.name}</p>
                    )}
                    <p className="text-slate-500 text-sm mt-1">
                        Review and certify ward-level election results
                    </p>
                    {/* <div className="mt-2 inline-flex items-center gap-2 px-3 py-1 bg-iec-pink-500/10 border border-iec-pink-500/20 rounded-lg">
                        <span className="text-iec-pink-600 text-xs font-semibold">
                            ⚡ Parallel Workflow — You can certify results immediately. Party representative responses are informational.
                        </span>
                    </div> */}
                </div>

                {/* Alert if there are pending results */}
                {pendingResults > 0 && (
                    <div className="mb-6 p-4 bg-iec-pink-500/10 border border-iec-pink-500/40 rounded-xl flex items-center gap-3">
                        <div className="w-3 h-3 bg-iec-pink-500 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-iec-pink-600">
                            <strong>{pendingResults} result{pendingResults !== 1 ? 's' : ''}</strong> ready for your ward certification
                        </p>
                        <Link
                            href="/ward/approval-queue"
                            prefetch
                            className="ml-auto px-4 py-2 bg-iec-pink-500 hover:bg-iec-pink-600 text-white text-sm font-bold rounded-lg"
                        >
                            Review Now →
                        </Link>
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-iec-pink-600">{pendingResults || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Pending Certification</div>
                    </div>
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-green-600">{statistics?.approved || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Ward Certified</div>
                    </div>
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-red-500">{statistics?.rejected || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Rejected / Returned</div>
                    </div>
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-iec-navy">{statistics?.totalStations || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Total Stations</div>
                    </div>
                </div>

                {/* Progress bar */}
                {statistics?.totalStations > 0 && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200 mb-8">
                        <div className="flex justify-between items-center mb-3">
                            <span className="text-slate-700 font-semibold">Certification Progress</span>
                            <span className="text-iec-navy font-bold">{progress}%</span>
                        </div>
                        <div className="w-full bg-white rounded-full h-4">
                            <div
                                className="bg-gradient-to-r from-iec-pink-600 to-iec-pink-400 h-4 rounded-full transition-all duration-700"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <p className="text-slate-600 text-xs mt-2">
                            {statistics.approved} of {statistics.totalStations} stations certified at ward level
                        </p>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <h2 className="text-xl font-bold text-iec-navy mb-5">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Link
                            href="/ward/approval-queue?filter=pending"
                            prefetch
                            className="group p-5 bg-iec-pink-500/10 hover:bg-iec-pink-500/20 border border-iec-pink-500/30 rounded-xl transition-all"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-iec-pink-500/20 rounded-lg flex items-center justify-center text-iec-pink-600">⏳</div>
                                <div className="text-lg font-bold text-iec-navy">Approval Queue</div>
                            </div>
                            <div className="text-iec-pink-600 text-sm">
                                {pendingResults > 0 ? `${pendingResults} result${pendingResults !== 1 ? 's' : ''} ready for certification` : 'No results pending'}
                            </div>
                        </Link>

                        <Link
                            href="/ward/approval-queue?filter=approved"
                            prefetch
                            className="group p-5 bg-iec-pink-500/10 hover:bg-iec-pink-700/20 border border-teal-500/30 rounded-xl transition-all"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-iec-pink-500/20 rounded-lg flex items-center justify-center text-iec-pink-600">✓</div>
                                <div className="text-lg font-bold text-iec-navy">Certified Results</div>
                            </div>
                            <div className="text-iec-pink-600 text-sm">{statistics?.approved || 0} results certified</div>
                        </Link>

                        <Link
                            href="/ward/approval-queue?filter=rejected"
                            className="group p-5 bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 rounded-xl transition-all"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-red-500/20 rounded-lg flex items-center justify-center text-red-400">✗</div>
                                <div className="text-lg font-bold text-iec-navy">Rejected Results</div>
                            </div>
                            <div className="text-red-300 text-sm">{statistics?.rejected || 0} results returned to officers</div>
                        </Link>

                        <Link
                            href="/ward/analytics"
                            className="group p-5 bg-iec-pink-500/10 hover:bg-iec-pink-500/20 border border-blue-500/30 rounded-xl transition-all"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-iec-pink-500/20 rounded-lg flex items-center justify-center text-iec-pink-600">📊</div>
                                <div className="text-lg font-bold text-iec-navy">Analytics</div>
                            </div>
                            <div className="text-iec-pink-600 text-sm">Ward-level statistics and reports</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
