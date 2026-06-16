import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';
import { useNotifications, ToastContainer } from '@/Components/Notifications';
import { RESULT_STATUS, RESULT_STATUS_LABELS } from '@/Utils/resultStatus';

const PIPELINE_LABELS = {
    ...RESULT_STATUS_LABELS,
};

const LEGACY_PIPELINE_LABELS = {
    legacy_party_gate: 'Legacy Party Gate',
};

export default function ChairmanDashboard({ auth, pendingNational, statistics = {}, recentActivity = [] }) {
    const pipeline = statistics.pipelineCounts || {};
    const [refreshing, setRefreshing] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    const { toasts, removeNotification, notify } = useNotifications();

    // Auto-refresh every 30 seconds
    useAutoRefreshWithVisibility({
        url: '/chairman/dashboard',
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
                    <h1 className="text-3xl font-bold text-iec-navy">IEC Chairman Dashboard</h1>
                    <p className="text-slate-500 mt-1 text-sm">
                        Your role: <strong className="text-iec-navy">Final National Certification</strong> — the highest authority in the election results pipeline.
                    </p>
                </div>

                {/* Pending alert */}
                {pendingNational > 0 && (
                    <div className="mb-6 p-4 bg-pink-500/10 border border-pink-500/40 rounded-xl flex items-center gap-3">
                        <span className="w-3 h-3 bg-pink-400 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-pink-500 flex-1 font-semibold">
                            {pendingNational} result{pendingNational > 1 ? 's' : ''} awaiting your final national certification.
                        </p>
                        <Link href="/chairman/national-queue"
                              className="px-4 py-2 bg-pink-500 hover:bg-pink-400 text-white text-sm font-bold rounded-lg whitespace-nowrap">
                            Certify Now →
                        </Link>
                    </div>
                )}

                {/* Key stats */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    {[
                        { label: 'Pending Your Approval', value: pendingNational || 0,                         color: 'text-amber-300' },
                        { label: 'Nationally Certified',  value: statistics.nationallyCertified || 0,           color: 'text-green-300' },
                        { label: 'Total Stations',        value: statistics.totalStations || 0,                 color: 'text-iec-navy' },
                        { label: 'Registered Voters',     value: (statistics.totalVoters || 0).toLocaleString(),color: 'text-iec-navy' },
                        { label: 'National Progress',     value: `${statistics.nationalProgress || 0}%`,        color: 'text-iec-pink-600' },
                    ].map((card) => (
                        <div key={card.label} className="bg-white rounded-xl p-5 border border-slate-200">
                            <div className={`text-2xl font-bold mb-1 ${card.color}`}>{card.value}</div>
                            <div className="text-slate-500 text-xs">{card.label}</div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                    {/* Quick Actions */}
                    <div className="lg:col-span-1 space-y-3">
                        <h2 className="text-iec-navy font-bold text-lg mb-4">Actions</h2>
                        {[
                            { href: '/chairman/national-queue', icon: '🏛️', label: 'National Certification Queue',
                              desc: `${pendingNational} pending final approval`, highlight: pendingNational > 0 },
                            { href: '/chairman/all-results',   icon: '📊', label: 'View All Results',
                              desc: 'Complete national overview of all results' },
                            { href: '/chairman/analytics',     icon: '📈', label: 'Full Analytics',
                              desc: 'National turnout, party performance, regions' },
                            { href: '/chairman/publish',       icon: '📢', label: 'Publish Results',
                              desc: 'Make final results public after certification' },
                        ].map((action) => (
                            <Link
                                key={action.href}
                                href={action.href}
                                className={`block p-4 rounded-xl border transition-all ${
                                    action.highlight
                                        ? 'bg-amber-500/10 border-amber-500/40 hover:bg-amber-500/20'
                                        : 'bg-white border-slate-200 hover:bg-slate-100'
                                }`}
                            >
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl">{action.icon}</span>
                                    <div>
                                        <div className="font-bold text-iec-navy text-sm">{action.label}</div>
                                        <div className={`text-xs ${action.highlight ? 'text-amber-300' : 'text-slate-500'}`}>
                                            {action.desc}
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>

                    {/* Pipeline Overview */}
                    <div className="lg:col-span-2">
                        <h2 className="text-iec-navy font-bold text-lg mb-4">National Pipeline Overview</h2>
                        <div className="bg-white rounded-xl border border-slate-200 p-5">
                            <div className="space-y-2">
                                {Object.entries(PIPELINE_LABELS).map(([key, label]) => {
                                    const count = pipeline[key] || 0;
                                    const isChairmanStage = key === RESULT_STATUS.PENDING_NATIONAL;
                                    const isFinal = key === RESULT_STATUS.NATIONALLY_CERTIFIED;
                                    return (
                                        <div key={key}
                                             className={`flex items-center gap-3 p-2.5 rounded-lg ${
                                                 isChairmanStage ? 'bg-amber-500/10 border border-amber-500/30' :
                                                 isFinal ? 'bg-green-500/10 border border-green-500/20' :
                                                 'bg-slate-50'
                                             }`}>
                                            <span className={`text-xs font-semibold w-36 ${
                                                isChairmanStage ? 'text-amber-300' :
                                                isFinal ? 'text-green-300' :
                                                'text-slate-500'
                                            }`}>
                                                {label}
                                            </span>
                                            <div className="flex-1 bg-white rounded-full h-2">
                                                <div
                                                    className="h-2 rounded-full transition-all"
                                                    style={{
                                                        width: `${statistics.totalStations > 0 ? Math.min(100, (count / statistics.totalStations) * 100) : 0}%`,
                                                        background: isChairmanStage ? '#f59e0b' :
                                                                    isFinal ? '#10b981' : '#3b82f6',
                                                    }}
                                                />
                                            </div>
                                            <span className={`text-sm font-bold w-8 text-right ${
                                                isChairmanStage ? 'text-amber-300' :
                                                isFinal ? 'text-green-300' : 'text-slate-600'
                                            }`}>
                                                {count}
                                            </span>
                                        </div>
                                    );
                                })}
                                {Object.entries(LEGACY_PIPELINE_LABELS).map(([key, label]) => {
                                    const count = pipeline[key] || 0;
                                    if (count === 0) return null;

                                    return (
                                        <div key={key}
                                             className="flex items-center gap-3 p-2.5 rounded-lg bg-amber-50 border border-amber-200">
                                            <span className="text-xs font-semibold w-36 text-amber-700">
                                                {label}
                                            </span>
                                            <div className="flex-1 bg-white rounded-full h-2">
                                                <div
                                                    className="h-2 rounded-full transition-all bg-amber-500"
                                                    style={{
                                                        width: `${statistics.totalStations > 0 ? Math.min(100, (count / statistics.totalStations) * 100) : 0}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm font-bold w-8 text-right text-amber-700">
                                                {count}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Recent Activity */}
                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <h2 className="text-iec-navy font-bold text-lg mb-4">Recent Certification Activity</h2>
                    {recentActivity.length > 0 ? (
                        <div className="space-y-2">
                            {recentActivity.map((activity, i) => (
                                <div key={i}
                                     className="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100">
                                    <span className={`w-2 h-2 rounded-full flex-shrink-0 ${
                                        activity.outcome === 'success' ? 'bg-teal-400' :
                                        activity.outcome === 'rejected' ? 'bg-red-400' : 'bg-gray-400'
                                    }`} />
                                    <div className="flex-1 min-w-0">
                                        <span className="text-slate-600 text-sm">
                                            {activity.action.replace(/\./g, ' › ')}
                                        </span>
                                        <span className="text-slate-600 text-xs ml-2">by {activity.user}</span>
                                    </div>
                                    <span className="text-slate-600 text-xs whitespace-nowrap">{activity.time}</span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-slate-500 text-sm text-center py-4">No recent activity</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
