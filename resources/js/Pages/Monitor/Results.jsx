import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_CONFIG = {
    not_reported:           { label: 'Not Reported',         color: 'bg-slate-500/20 text-slate-300' },
    submitted:              { label: 'Submitted',             color: 'bg-amber-500/20 text-amber-300' },
    pending_ward:           { label: 'Pending Ward',          color: 'bg-orange-500/20 text-orange-300' },
    ward_certified:         { label: 'Ward Certified',        color: 'bg-blue-500/20 text-blue-300' },
    pending_constituency:   { label: 'Pending Constituency',  color: 'bg-purple-500/20 text-purple-300' },
    constituency_certified: { label: 'Constituency Certified',color: 'bg-indigo-500/20 text-indigo-300' },
    pending_admin_area:     { label: 'Pending Admin Area',    color: 'bg-pink-500/20 text-pink-300' },
    admin_area_certified:   { label: 'Admin Area Certified',  color: 'bg-teal-500/20 text-teal-300' },
    pending_national:       { label: 'Pending National',      color: 'bg-cyan-500/20 text-cyan-300' },
    nationally_certified:   { label: 'Nationally Certified',  color: 'bg-green-500/20 text-green-300' },
};

export default function MonitorResults({ auth, monitor, results = [] }) {
    const [expandedId, setExpandedId] = useState(null);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/monitor/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Back to Monitor Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Station Results</h1>
                    <p className="text-gray-400 mt-1">
                        Read-only view of results for your assigned polling stations
                    </p>
                    <div className="mt-2 inline-flex items-center gap-2 px-3 py-1 bg-blue-500/10 border border-blue-500/30 rounded-lg text-blue-300 text-xs">
                        ℹ️ View only — you cannot modify results
                    </div>
                </div>

                {/* Summary */}
                {results.length > 0 && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-white">{results.length}</div>
                            <div className="text-gray-400 text-sm">Total Results</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-green-300">
                                {results.filter(r => r.status === 'nationally_certified').length}
                            </div>
                            <div className="text-gray-400 text-sm">Nationally Certified</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-white">
                                {results.reduce((s, r) => s + (r.total_votes_cast || 0), 0).toLocaleString()}
                            </div>
                            <div className="text-gray-400 text-sm">Total Votes Cast</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50">
                            <div className="text-2xl font-bold text-teal-300">
                                {results.length > 0
                                    ? (results.reduce((s, r) => s + r.turnout, 0) / results.length).toFixed(1)
                                    : 0}%
                            </div>
                            <div className="text-gray-400 text-sm">Avg Turnout</div>
                        </div>
                    </div>
                )}

                {/* Results */}
                {results.length === 0 ? (
                    <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                        <div className="text-5xl mb-4">📊</div>
                        <p className="text-gray-400 text-lg">No results available for your assigned stations yet.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {results.map((result) => {
                            const statusCfg  = STATUS_CONFIG[result.status] || STATUS_CONFIG.not_reported;
                            const isExpanded = expandedId === result.id;

                            return (
                                <div key={result.id} className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
                                    <button
                                        onClick={() => setExpandedId(isExpanded ? null : result.id)}
                                        className="w-full p-5 text-left flex flex-wrap gap-4 justify-between items-start hover:bg-slate-700/20 transition-colors"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-3 flex-wrap mb-1">
                                                <h3 className="text-white font-bold">{result.station_name}</h3>
                                                <span className="text-xs font-mono text-gray-500">{result.station_code}</span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${statusCfg.color}`}>
                                                    {statusCfg.label}
                                                </span>
                                            </div>
                                            <div className="text-sm text-gray-400">
                                                Ward: {result.ward} — Submitted: {result.submitted_at || 'Not yet submitted'}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-4 text-sm">
                                            <div className="text-right">
                                                <div className="text-white font-bold">{result.total_votes_cast?.toLocaleString() || '—'}</div>
                                                <div className="text-gray-400 text-xs">Votes Cast</div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-teal-300 font-bold">{result.turnout}%</div>
                                                <div className="text-gray-400 text-xs">Turnout</div>
                                            </div>
                                            <span className="text-gray-400 text-lg">{isExpanded ? '▲' : '▼'}</span>
                                        </div>
                                    </button>

                                    {isExpanded && (
                                        <div className="border-t border-slate-700/50 p-5">
                                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                                {[
                                                    { label: 'Valid Votes',    value: result.valid_votes?.toLocaleString(), color: 'text-teal-300' },
                                                    { label: 'Rejected Votes', value: result.rejected_votes?.toLocaleString(), color: 'text-amber-300' },
                                                    { label: 'Votes Cast',     value: result.total_votes_cast?.toLocaleString(), color: 'text-white' },
                                                    { label: 'Turnout',        value: `${result.turnout}%`, color: 'text-blue-300' },
                                                ].map(stat => (
                                                    <div key={stat.label} className="bg-slate-900/50 p-3 rounded-lg">
                                                        <div className="text-xs text-gray-400 mb-1">{stat.label}</div>
                                                        <div className={`font-bold ${stat.color}`}>{stat.value || '—'}</div>
                                                    </div>
                                                ))}
                                            </div>

                                            {result.candidate_votes?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-3">Candidate Votes</div>
                                                    <div className="space-y-2">
                                                        {result.candidate_votes.map((cv, idx) => (
                                                            <div key={idx} className="flex items-center gap-3">
                                                                <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: cv.party_color }} />
                                                                <span className="text-gray-300 text-sm w-40 truncate">{cv.candidate}</span>
                                                                <span className="text-xs text-gray-500 w-10">{cv.party}</span>
                                                                <div className="flex-1 bg-slate-700 rounded-full h-2">
                                                                    <div
                                                                        className="h-2 rounded-full"
                                                                        style={{
                                                                            width: result.valid_votes > 0
                                                                                ? `${(cv.votes / result.valid_votes) * 100}%`
                                                                                : '0%',
                                                                            backgroundColor: cv.party_color,
                                                                        }}
                                                                    />
                                                                </div>
                                                                <span className="text-white text-sm font-semibold w-16 text-right">
                                                                    {cv.votes?.toLocaleString()}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Submit observation for this station */}
                                            <div className="mt-4 pt-4 border-t border-slate-700/30">
                                                <Link
                                                    href={`/monitor/submit-observation?station_id=${result.id}`}
                                                    className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600/20 hover:bg-amber-600/30 border border-amber-500/30 text-amber-300 rounded-lg text-sm font-semibold transition-colors"
                                                >
                                                    📝 Submit Observation for this Station
                                                </Link>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Navigation */}
                <div className="mt-8 flex flex-wrap gap-4">
                    <Link href="/monitor/stations" className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg">
                        📍 View My Stations
                    </Link>
                    <Link href="/monitor/submit-observation" className="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                        📝 Submit Observation
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}