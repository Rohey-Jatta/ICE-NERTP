import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';
import { useNotifications, ToastContainer } from '@/Components/Notifications';
import { useMonitorNotifications } from '@/Hooks/useMonitorNotifications';

const TYPE_COLORS = {
    general:         'bg-iec-pink-500/20 text-iec-pink-600',
    irregularity:    'bg-red-500/20 text-red-300',
    process_concern: 'bg-amber-500/20 text-amber-300',
    positive:        'bg-green-500/20 text-green-300',
    incident:        'bg-orange-500/20 text-orange-300',
};

const SEVERITY_DOT = {
    low:      'bg-green-400',
    medium:   'bg-amber-400',
    high:     'bg-orange-400',
    critical: 'bg-red-400',
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
            
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8 flex justify-between items-start">
                    <div>
                        <h1 className="text-3xl font-bold text-iec-navy">Election Monitor Dashboard</h1>
                        <p className="text-slate-500 mt-1">
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
                    <div className="text-right text-xs">
                        <div className={`flex items-center justify-end gap-2 px-3 py-2 rounded-lg ${refreshing ? 'bg-amber-500/20 text-amber-600' : 'bg-green-500/20 text-green-600'}`}>
                            {refreshing ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span className="font-semibold">Refreshing...</span>
                                </>
                            ) : (
                                <>
                                    <span>✓</span>
                                    <div className="text-left">
                                        <div className="font-semibold">Auto-refresh active</div>
                                        <div className="text-xs opacity-75">
                                            Last updated: {lastRefreshTime.toLocaleTimeString()}
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-iec-navy">{stats.assigned_stations || 0}</div>
                        <div className="text-slate-600 text-sm mt-1">Assigned Stations</div>
                    </div>
                    <div className="bg-teal-600/40 rounded-xl p-5 border border-pink-700/50">
                        <div className="text-3xl font-bold text-iec-pink-500">{stats.observations || 0}</div>
                        <div className="text-pink-700 text-sm mt-1">Observations Submitted</div>
                    </div>
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-amber-600">{stats.visited || 0}</div>
                        <div className="text-slate-600 text-sm mt-1">Stations Visited</div>
                    </div>
                    <div className="bg-pink-400/40 rounded-xl p-5 border border-pink-700/50">
                        <div className="text-3xl font-bold text-red-700">{stats.flagged || 0}</div>
                        <div className="text-pink-700 text-sm mt-1">Issues Flagged</div>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="bg-white rounded-xl p-6 border border-slate-200 mb-8">
                    <h2 className="text-xl font-bold text-iec-navy mb-5">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <Link href="/monitor/stations"
                            className="p-5 bg-iec-pink-500/10 hover:bg-iec-pink-500/20 border border-blue-500/30 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">📍</div>
                            <div className="text-lg font-bold text-iec-navy">My Stations</div>
                            <div className="text-iec-pink-600 text-sm mt-1">
                                {stats.assigned_stations || 0} assigned stations
                            </div>
                        </Link>

                        <Link href="/monitor/submit-observation"
                            className="p-5 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">📝</div>
                            <div className="text-lg font-bold text-iec-navy">Submit Observation</div>
                            <div className="text-amber-300 text-sm mt-1">Report findings from a station</div>
                        </Link>

                        <Link href="/monitor/observations"
                            className="p-5 bg-iec-pink-500/10 hover:bg-iec-pink-700/20 border border-teal-500/30 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">🗂</div>
                            <div className="text-lg font-bold text-iec-navy">My Observations</div>
                            <div className="text-iec-pink-600 text-sm mt-1">
                                {stats.observations || 0} submitted
                            </div>
                        </Link>

                        <Link href="/monitor/results"
                            className="p-5 bg-iec-pink-50 hover:bg-iec-pink-50 border border-iec-pink-100 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">📊</div>
                            <div className="text-lg font-bold text-iec-navy">View Results</div>
                            <div className="text-iec-pink-600 text-sm mt-1">Station results (read-only)</div>
                        </Link>
                    </div>
                </div>

                {/* Recent Observations */}
                {recentObservations.length > 0 && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-xl font-bold text-iec-navy">Recent Observations</h2>
                            <Link href="/monitor/observations" className="text-iec-pink-600 hover:text-iec-pink-600 text-sm">
                                View All →
                            </Link>
                        </div>
                        <div className="space-y-3">
                            {recentObservations.map((obs, i) => (
                                <div key={i} className="flex items-start gap-4 p-4 bg-white rounded-lg border border-slate-200">
                                    <div className={`w-2 h-2 rounded-full flex-shrink-0 mt-2 ${SEVERITY_DOT[obs.severity] || 'bg-gray-400'}`} />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-iec-navy font-semibold text-sm">{obs.title}</span>
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