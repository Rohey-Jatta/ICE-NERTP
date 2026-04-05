import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

const TYPE_COLORS = {
    general:         'bg-blue-500/20 text-blue-300',
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
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-white">Election Monitor Dashboard</h1>
                    <p className="text-gray-400 mt-1">
                        {monitor?.organization
                            ? `${monitor.organization} — ${monitor.type?.replace('_', ' ')}`
                            : 'Independent Election Monitoring'}
                    </p>
                    {monitor?.accreditation_number && (
                        <p className="text-teal-400 text-sm mt-1 font-mono">
                            Accreditation: {monitor.accreditation_number}
                        </p>
                    )}
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                        <div className="text-3xl font-bold text-white">{stats.assigned_stations || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Assigned Stations</div>
                    </div>
                    <div className="bg-teal-900/40 rounded-xl p-5 border border-teal-700/50">
                        <div className="text-3xl font-bold text-teal-300">{stats.observations || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Observations Submitted</div>
                    </div>
                    <div className="bg-amber-900/40 rounded-xl p-5 border border-amber-700/50">
                        <div className="text-3xl font-bold text-amber-300">{stats.flagged || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Issues Flagged</div>
                    </div>
                    <div className="bg-blue-900/40 rounded-xl p-5 border border-blue-700/50">
                        <div className="text-3xl font-bold text-blue-300">{stats.visited || 0}</div>
                        <div className="text-gray-400 text-sm mt-1">Stations Visited</div>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-8">
                    <h2 className="text-xl font-bold text-white mb-5">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <Link href="/monitor/stations"
                            className="p-5 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">📍</div>
                            <div className="text-lg font-bold text-white">My Stations</div>
                            <div className="text-blue-300 text-sm mt-1">
                                {stats.assigned_stations || 0} assigned stations
                            </div>
                        </Link>

                        <Link href="/monitor/submit-observation"
                            className="p-5 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">📝</div>
                            <div className="text-lg font-bold text-white">Submit Observation</div>
                            <div className="text-amber-300 text-sm mt-1">Report findings from a station</div>
                        </Link>

                        <Link href="/monitor/observations"
                            className="p-5 bg-teal-500/10 hover:bg-teal-500/20 border border-teal-500/30 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">🗂</div>
                            <div className="text-lg font-bold text-white">My Observations</div>
                            <div className="text-teal-300 text-sm mt-1">
                                {stats.observations || 0} submitted
                            </div>
                        </Link>

                        <Link href="/monitor/results"
                            className="p-5 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 rounded-xl transition-all block">
                            <div className="text-2xl mb-2">📊</div>
                            <div className="text-lg font-bold text-white">View Results</div>
                            <div className="text-purple-300 text-sm mt-1">Station results (read-only)</div>
                        </Link>
                    </div>
                </div>

                {/* Recent Observations */}
                {recentObservations.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-xl font-bold text-white">Recent Observations</h2>
                            <Link href="/monitor/observations" className="text-teal-400 hover:text-teal-300 text-sm">
                                View All →
                            </Link>
                        </div>
                        <div className="space-y-3">
                            {recentObservations.map((obs, i) => (
                                <div key={i} className="flex items-start gap-4 p-4 bg-slate-900/50 rounded-lg border border-slate-700/30">
                                    <div className={`w-2 h-2 rounded-full flex-shrink-0 mt-2 ${SEVERITY_DOT[obs.severity] || 'bg-gray-400'}`} />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-white font-semibold text-sm">{obs.title}</span>
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${TYPE_COLORS[obs.observation_type] || 'bg-gray-500/20 text-gray-300'}`}>
                                                {obs.observation_type?.replace('_', ' ')}
                                            </span>
                                        </div>
                                        <div className="text-gray-400 text-xs mt-1">
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