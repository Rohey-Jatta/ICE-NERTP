import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { CERTIFIED_RESULT_STATUSES, RESULT_STATUS, getResultStatusMeta } from '@/Utils/resultStatus';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';

export default function MonitorStations({ auth, monitor, stations = [] }) {
    const [refreshing, setRefreshing] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());

    // Auto-refresh every 30 seconds
    useAutoRefreshWithVisibility({
        url: '/monitor/stations',
        interval: 30000,
        preserveScroll: true,
        preserveState: true,
        onBeforeRefresh: () => setRefreshing(true),
        onAfterRefresh: () => {
            setRefreshing(false);
            setLastRefreshTime(new Date());
        },
    });

    const certified = stations.filter(s =>
        CERTIFIED_RESULT_STATUSES.includes(s.result_status)
    ).length;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6 flex justify-between items-start">
                    <div>
                        <Link href="/monitor/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                            Back to Monitor Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-iec-navy">Assigned Polling Stations</h1>
                        <p className="text-slate-500 mt-1">
                            {stations.length} station{stations.length !== 1 ? 's' : ''} assigned for monitoring
                        </p>
                    </div>

                    {/* Refresh Status */}
                    <div className={`text-xs flex items-center justify-end gap-2 px-3 py-2 rounded-lg ${refreshing ? 'bg-amber-500/20 text-amber-600' : 'bg-green-500/20 text-green-600'}`}>
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
                                <span>✓ Auto-refresh</span>
                                <span className="text-xs opacity-75">{lastRefreshTime.toLocaleTimeString()}</span>
                            </>
                        )}
                    </div>
                </div>

                {/* Summary */}
                {stations.length > 0 && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-navy">{stations.length}</div>
                            <div className="text-slate-500 text-sm">Total Assigned</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-pink-600">{certified}</div>
                            <div className="text-slate-500 text-sm">Results Certified</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-amber-300">
                                {stations.filter(s => s.result_status === RESULT_STATUS.SUBMITTED).length}
                            </div>
                            <div className="text-slate-500 text-sm">Results Submitted</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-pink-600">
                                {stations.reduce((s, st) => s + (st.observations_count || 0), 0)}
                            </div>
                            <div className="text-slate-500 text-sm">Total Observations</div>
                        </div>
                    </div>
                )}

                {/* Stations list */}
                <div className="space-y-4">
                    {stations.length > 0 ? (
                        stations.map((station) => {
                            const statusCfg = getResultStatusMeta(station.result_status);
                            return (
                                <div key={station.id} className="bg-white rounded-xl p-5 border border-slate-200">
                                    <div className="flex flex-wrap gap-4 justify-between items-start">
                                        <div className="flex-1 min-w-0">
                                            {/* Station info */}
                                            <div className="flex items-center gap-3 flex-wrap mb-1">
                                                <h3 className="text-lg font-bold text-iec-navy">{station.name}</h3>
                                                <span className="text-xs font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                                                    {station.code}
                                                </span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${statusCfg.borderedBadgeClass}`}>
                                                    {statusCfg.label}
                                                </span>
                                            </div>

                                            <div className="text-sm text-slate-500 space-y-0.5">
                                                {station.ward && <div>Ward: <span className="text-slate-600">{station.ward}</span></div>}
                                                {station.address && <div>Address: <span className="text-slate-600">{station.address}</span></div>}
                                                <div>
                                                    Registered Voters: <span className="text-slate-600">{station.registered_voters?.toLocaleString()}</span>
                                                </div>
                                            </div>

                                            {/* Result data */}
                                            {station.total_votes_cast != null && (
                                                <div className="mt-3 flex flex-wrap gap-4 text-sm">
                                                    <span className="text-slate-500">
                                                        Votes Cast: <strong className="text-iec-navy">{station.total_votes_cast?.toLocaleString()}</strong>
                                                    </span>
                                                    {station.turnout != null && (
                                                        <span className="text-slate-500">
                                                            Turnout: <strong className="text-iec-pink-600">{station.turnout}%</strong>
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {/* Observation count */}
                                            <div className="mt-2 text-xs text-slate-500">
                                                {station.observations_count > 0
                                                    ? `${station.observations_count} observation${station.observations_count > 1 ? 's' : ''} submitted`
                                                    : 'No observations yet'}
                                            </div>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex flex-col gap-2 flex-shrink-0">
                                            <Link
                                                href={`/monitor/submit-observation?station_id=${station.id}`}
                                                className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg text-center"
                                            >
                                                📝 Submit Observation
                                            </Link>
                                            <Link
                                                href="/monitor/observations"
                                                className="px-4 py-2 bg-white hover:bg-slate-100 text-iec-navy text-sm font-semibold rounded-lg text-center"
                                            >
                                                🗂 View Observations
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <div className="bg-white rounded-xl p-12 border border-slate-200 text-center">
                            <div className="text-5xl mb-4">📍</div>
                            <p className="text-slate-500 text-lg">No polling stations assigned for monitoring.</p>
                            <p className="text-slate-500 text-sm mt-1">Contact the IEC Administrator to assign stations to your account.</p>
                        </div>
                    )}
                </div>

                {/* Navigation */}
                <div className="mt-6 flex gap-4">
                    <Link href="/monitor/submit-observation" className="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                        📝 Submit New Observation
                    </Link>
                    <Link href="/monitor/observations" className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
                        🗂 View All Observations
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
