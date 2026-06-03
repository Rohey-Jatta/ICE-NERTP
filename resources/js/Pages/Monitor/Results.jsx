import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { RESULT_STATUS, getResultStatusMeta } from '@/Utils/resultStatus';

export default function MonitorResults({ auth, monitor, results = [] }) {
    const [expandedId, setExpandedId] = useState(null);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/monitor/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                        Back to Monitor Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Station Results</h1>
                    <p className="text-slate-500 mt-1">
                        Read-only view of results for your assigned polling stations
                    </p>
                    <div className="mt-2 inline-flex items-center gap-2 px-3 py-1 bg-iec-pink-500/10 border border-blue-500/30 rounded-lg text-iec-pink-600 text-xs">
                        ℹ️ View only — you cannot modify results
                    </div>
                </div>

                {/* Summary */}
                {results.length > 0 && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-navy">{results.length}</div>
                            <div className="text-slate-500 text-sm">Total Results</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-green-300">
                                {results.filter(r => r.status === RESULT_STATUS.NATIONALLY_CERTIFIED).length}
                            </div>
                            <div className="text-slate-500 text-sm">Nationally Certified</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-navy">
                                {results.reduce((s, r) => s + (r.total_votes_cast || 0), 0).toLocaleString()}
                            </div>
                            <div className="text-slate-500 text-sm">Total Votes Cast</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-pink-600">
                                {results.length > 0
                                    ? (results.reduce((s, r) => s + r.turnout, 0) / results.length).toFixed(1)
                                    : 0}%
                            </div>
                            <div className="text-slate-500 text-sm">Avg Turnout</div>
                        </div>
                    </div>
                )}

                {/* Results */}
                {results.length === 0 ? (
                    <div className="bg-white rounded-xl p-12 border border-slate-200 text-center">
                        <div className="text-5xl mb-4">📊</div>
                        <p className="text-slate-500 text-lg">No results available for your assigned stations yet.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {results.map((result) => {
                            const statusCfg  = getResultStatusMeta(result.status);
                            const isExpanded = expandedId === result.id;

                            return (
                                <div key={result.id} className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                                    <button
                                        onClick={() => setExpandedId(isExpanded ? null : result.id)}
                                        className="w-full p-5 text-left flex flex-wrap gap-4 justify-between items-start hover:bg-slate-100 transition-colors"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-3 flex-wrap mb-1">
                                                <h3 className="text-iec-navy font-bold">{result.station_name}</h3>
                                                <span className="text-xs font-mono text-slate-500">{result.station_code}</span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${statusCfg.badgeClass}`}>
                                                    {statusCfg.label}
                                                </span>
                                            </div>
                                            <div className="text-sm text-slate-500">
                                                Ward: {result.ward} — Submitted: {result.submitted_at || 'Not yet submitted'}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-4 text-sm">
                                            <div className="text-right">
                                                <div className="text-iec-navy font-bold">{result.total_votes_cast?.toLocaleString() || '—'}</div>
                                                <div className="text-slate-500 text-xs">Votes Cast</div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-iec-pink-600 font-bold">{result.turnout}%</div>
                                                <div className="text-slate-500 text-xs">Turnout</div>
                                            </div>
                                            <span className="text-slate-500 text-lg">{isExpanded ? '▲' : '▼'}</span>
                                        </div>
                                    </button>

                                    {isExpanded && (
                                        <div className="border-t border-slate-200 p-5">
                                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                                {[
                                                    { label: 'Valid Votes',    value: result.valid_votes?.toLocaleString(), color: 'text-iec-pink-600' },
                                                    { label: 'Rejected Votes', value: result.rejected_votes?.toLocaleString(), color: 'text-amber-300' },
                                                    { label: 'Votes Cast',     value: result.total_votes_cast?.toLocaleString(), color: 'text-iec-navy' },
                                                    { label: 'Turnout',        value: `${result.turnout}%`, color: 'text-iec-pink-600' },
                                                ].map(stat => (
                                                    <div key={stat.label} className="bg-white p-3 rounded-lg">
                                                        <div className="text-xs text-slate-500 mb-1">{stat.label}</div>
                                                        <div className={`font-bold ${stat.color}`}>{stat.value || '—'}</div>
                                                    </div>
                                                ))}
                                            </div>

                                            {result.candidate_votes?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-slate-500 uppercase tracking-wide mb-3">Candidate Votes</div>
                                                    <div className="space-y-2">
                                                        {result.candidate_votes.map((cv, idx) => (
                                                            <div key={idx} className="flex items-center gap-3">
                                                                <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: cv.party_color }} />
                                                                <span className="text-slate-600 text-sm w-40 truncate">{cv.candidate}</span>
                                                                <span className="text-xs text-slate-500 w-10">{cv.party}</span>
                                                                <div className="flex-1 bg-white rounded-full h-2">
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
                                                                <span className="text-iec-navy text-sm font-semibold w-16 text-right">
                                                                    {cv.votes?.toLocaleString()}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Submit observation for this station */}
                                            <div className="mt-4 pt-4 border-t border-slate-200">
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
                    <Link href="/monitor/stations" className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
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
