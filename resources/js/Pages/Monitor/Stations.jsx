import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

const STATUS_CONFIG = {
    not_reported:           { label: 'Not Reported',           color: 'bg-slate-500/20 text-slate-300 border-slate-500/30' },
    submitted:              { label: 'Submitted',               color: 'bg-amber-500/20 text-amber-300 border-amber-500/30' },
    pending_party_acceptance:{ label: 'Party Acceptance',       color: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30' },
    pending_ward:           { label: 'Pending Ward',            color: 'bg-orange-500/20 text-orange-300 border-orange-500/30' },
    ward_certified:         { label: 'Ward Certified',          color: 'bg-blue-500/20 text-blue-300 border-blue-500/30' },
    pending_constituency:   { label: 'Pending Constituency',    color: 'bg-purple-500/20 text-purple-300 border-purple-500/30' },
    constituency_certified: { label: 'Constituency Certified',  color: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30' },
    pending_admin_area:     { label: 'Pending Admin Area',      color: 'bg-pink-500/20 text-pink-300 border-pink-500/30' },
    admin_area_certified:   { label: 'Admin Area Certified',    color: 'bg-teal-500/20 text-teal-300 border-teal-500/30' },
    pending_national:       { label: 'Pending National',        color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' },
    nationally_certified:   { label: 'Nationally Certified',    color: 'bg-green-500/20 text-green-300 border-green-500/30' },
};

export default function MonitorStations({ auth, monitor, stations = [] }) {
    const certified = stations.filter(s =>
        s.result_status === 'nationally_certified' || s.result_status === 'ward_certified' || s.result_status === 'constituency_certified'
    ).length;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/monitor/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Back to Monitor Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Assigned Polling Stations</h1>
                    <p className="text-gray-400 mt-1">
                        {stations.length} station{stations.length !== 1 ? 's' : ''} assigned for monitoring
                    </p>
                </div>

                {/* Summary */}
                {stations.length > 0 && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-white">{stations.length}</div>
                            <div className="text-gray-400 text-sm">Total Assigned</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-teal-300">{certified}</div>
                            <div className="text-gray-400 text-sm">Results Certified</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-amber-300">
                                {stations.filter(s => s.result_status === 'submitted').length}
                            </div>
                            <div className="text-gray-400 text-sm">Results Submitted</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-blue-300">
                                {stations.reduce((s, st) => s + (st.observations_count || 0), 0)}
                            </div>
                            <div className="text-gray-400 text-sm">Total Observations</div>
                        </div>
                    </div>
                )}

                {/* Stations list */}
                <div className="space-y-4">
                    {stations.length > 0 ? (
                        stations.map((station) => {
                            const statusCfg = STATUS_CONFIG[station.result_status] || STATUS_CONFIG.not_reported;
                            return (
                                <div key={station.id} className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                                    <div className="flex flex-wrap gap-4 justify-between items-start">
                                        <div className="flex-1 min-w-0">
                                            {/* Station info */}
                                            <div className="flex items-center gap-3 flex-wrap mb-1">
                                                <h3 className="text-lg font-bold text-white">{station.name}</h3>
                                                <span className="text-xs font-mono text-gray-500 bg-slate-700/50 px-2 py-0.5 rounded">
                                                    {station.code}
                                                </span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${statusCfg.color}`}>
                                                    {statusCfg.label}
                                                </span>
                                            </div>

                                            <div className="text-sm text-gray-400 space-y-0.5">
                                                {station.ward && <div>Ward: <span className="text-gray-300">{station.ward}</span></div>}
                                                {station.address && <div>Address: <span className="text-gray-300">{station.address}</span></div>}
                                                <div>
                                                    Registered Voters: <span className="text-gray-300">{station.registered_voters?.toLocaleString()}</span>
                                                </div>
                                            </div>

                                            {/* Result data */}
                                            {station.total_votes_cast != null && (
                                                <div className="mt-3 flex flex-wrap gap-4 text-sm">
                                                    <span className="text-gray-400">
                                                        Votes Cast: <strong className="text-white">{station.total_votes_cast?.toLocaleString()}</strong>
                                                    </span>
                                                    {station.turnout != null && (
                                                        <span className="text-gray-400">
                                                            Turnout: <strong className="text-teal-300">{station.turnout}%</strong>
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {/* Observation count */}
                                            <div className="mt-2 text-xs text-gray-500">
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
                                                className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-sm font-semibold rounded-lg text-center"
                                            >
                                                🗂 View Observations
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <div className="text-5xl mb-4">📍</div>
                            <p className="text-gray-400 text-lg">No polling stations assigned for monitoring.</p>
                            <p className="text-gray-500 text-sm mt-1">Contact the IEC Administrator to assign stations to your account.</p>
                        </div>
                    )}
                </div>

                {/* Navigation */}
                <div className="mt-6 flex gap-4">
                    <Link href="/monitor/submit-observation" className="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                        📝 Submit New Observation
                    </Link>
                    <Link href="/monitor/observations" className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        🗂 View All Observations
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}