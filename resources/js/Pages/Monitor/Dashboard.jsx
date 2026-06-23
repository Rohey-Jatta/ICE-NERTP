import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';
import { useNotifications, ToastContainer } from '@/Components/Notifications';
import { useMonitorNotifications } from '@/Hooks/useMonitorNotifications';

const TYPE_COLORS = {
    general:         'bg-blue-100 text-blue-800',
    irregularity:    'bg-red-100 text-red-800',
    process_concern: 'bg-amber-100 text-amber-800',
    positive:        'bg-green-100 text-green-800',
    incident:        'bg-orange-100 text-orange-800',
};

const SEVERITY_DOT = {
    low:      'bg-green-500',
    medium:   'bg-amber-500',
    high:     'bg-orange-500',
    critical: 'bg-red-500',
};

export default function MonitorDashboard({ auth, monitor, stats = {}, recentObservations = [] }) {
    const [refreshing, setRefreshing] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    const { toasts, removeNotification, notify } = useNotifications();

    // Monitor observations for new submissions
    useMonitorNotifications({
        observations: recentObservations,
        onNotify: (message, type) => notify[type](message),
    });

    // Auto-refresh every 30 seconds, pause when tab not visible
    useAutoRefreshWithVisibility({
        url: '/monitor/dashboard',
        interval: 30000,
        preserveScroll: true,
        preserveState: true,
        onBeforeRefresh: () => setRefreshing(true),
        onAfterRefresh: () => {
            setRefreshing(false);
            setLastRefreshTime(new Date());
        },
    });

    return (
        <AppLayout user={auth.user}>
            <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />

            <div className="ws-container">

                {/* Header */}
                <div className="ws-page-header">
                    <div className="min-w-0">
                        <h1 className="ws-page-title">Election Monitor Dashboard</h1>
                        <p className="ws-page-desc">
                            {monitor?.organization
                                ? `${monitor.organization} — ${monitor.type?.replace('_', ' ')}`
                                : 'Independent Election Monitoring'}
                        </p>
                        {monitor?.accreditation_number && (
                            <p className="text-iec-pink-600 text-sm mt-1 font-mono">
                                Accreditation: {monitor.accreditation_number}
                            </p>
                        )}
                    </div>

                    {/* Auto-refresh Status */}
                    <div className="flex items-center gap-2">
                        <div className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold ${
                            refreshing ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200'
                        }`}>
                            {refreshing ? (
                                <>
                                    <svg className="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span>Refreshing...</span>
                                </>
                            ) : (
                                <>
                                    <span className="w-2 h-2 rounded-full bg-green-500" />
                                    <span>Updated {lastRefreshTime.toLocaleTimeString()}</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div className="ws-panel p-5">
                        <div className="text-3xl font-bold text-slate-900">{stats.assigned_stations || 0}</div>
                        <div className="text-slate-500 text-sm mt-1 font-medium">Assigned Stations</div>
                    </div>
                    <div className="ws-panel p-5 border-t-4 border-t-iec-pink-500">
                        <div className="text-3xl font-bold text-iec-pink-600">{stats.observations || 0}</div>
                        <div className="text-slate-500 text-sm mt-1 font-medium">Observations Submitted</div>
                    </div>
                    <div className="ws-panel p-5 border-t-4 border-t-amber-500">
                        <div className="text-3xl font-bold text-amber-600">{stats.visited || 0}</div>
                        <div className="text-slate-500 text-sm mt-1 font-medium">Stations Visited</div>
                    </div>
                    <div className="ws-panel p-5 border-t-4 border-t-red-500">
                        <div className="text-3xl font-bold text-red-600">{stats.flagged || 0}</div>
                        <div className="text-slate-500 text-sm mt-1 font-medium">Issues Flagged</div>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="ws-panel p-6 mb-6">
                    <h2 className="ws-section-title mb-4">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <Link href="/monitor/stations" className="action-tile">
                            <div className="text-2xl mb-2">📍</div>
                            <div className="action-tile-title">My Stations</div>
                            <div className="action-tile-desc">
                                {stats.assigned_stations || 0} assigned stations
                            </div>
                        </Link>

                        <Link href="/monitor/submit-observation" className="action-tile">
                            <div className="text-2xl mb-2">📝</div>
                            <div className="action-tile-title">Submit Observation</div>
                            <div className="action-tile-desc">Report findings from a station</div>
                        </Link>

                        <Link href="/monitor/observations" className="action-tile">
                            <div className="text-2xl mb-2">🗂</div>
                            <div className="action-tile-title">My Observations</div>
                            <div className="action-tile-desc">
                                {stats.observations || 0} submitted
                            </div>
                        </Link>

                        <Link href="/monitor/results" className="action-tile">
                            <div className="text-2xl mb-2">📊</div>
                            <div className="action-tile-title">View Results</div>
                            <div className="action-tile-desc">Station results (read-only)</div>
                        </Link>
                    </div>
                </div>

                {/* Recent Observations */}
                {recentObservations.length > 0 && (
                    <div className="ws-panel p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="ws-section-title">Recent Observations</h2>
                            <Link href="/monitor/observations" className="text-sm font-medium text-iec-pink-600 hover:text-iec-pink-700">
                                View All →
                            </Link>
                        </div>
                        <div className="space-y-3">
                            {recentObservations.map((obs, i) => (
                                <div key={i} className="flex items-start gap-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                                    <div className={`w-2 h-2 rounded-full flex-shrink-0 mt-2 ${SEVERITY_DOT[obs.severity] || 'bg-gray-400'}`} />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-slate-900 font-semibold text-sm">{obs.title}</span>
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${TYPE_COLORS[obs.observation_type] || 'bg-slate-100 text-slate-600'}`}>
                                                {obs.observation_type?.replace('_', ' ')}
                                            </span>
                                        </div>
                                        <div className="text-slate-500 text-xs mt-1">
                                            {obs.station_name} ({obs.station_code}) — {obs.observed_at
                                                ? new Date(obs.observed_at).toLocaleString()
                                                : '—'}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}