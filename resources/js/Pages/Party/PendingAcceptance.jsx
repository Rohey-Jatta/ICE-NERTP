import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_BADGE = {
    pending_party_acceptance: { label: 'Awaiting Parties',  color: 'bg-yellow-500/20 text-yellow-300' },
    pending_ward:             { label: 'At Ward Level',     color: 'bg-amber-500/20 text-amber-300' },
    ward_certified:           { label: 'Ward Certified',    color: 'bg-teal-500/20 text-teal-300' },
    pending_constituency:     { label: 'At Constituency',   color: 'bg-blue-500/20 text-blue-300' },
    nationally_certified:     { label: 'Nationally Certified', color: 'bg-green-500/20 text-green-300' },
};

export default function PendingAcceptance({ auth, pendingResults = [], party }) {
    const partyColor = party?.color?.split(',')[0] || '#6b7280';

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/party/dashboard" className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        ← Party Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Pending Result Review</h1>
                    {party?.name && (
                        <p className="text-gray-400 mt-1 flex items-center gap-2">
                            <span className="w-3 h-3 rounded-full inline-block" style={{ background: partyColor }} />
                            {party.name} — results awaiting your decision
                        </p>
                    )}
                </div>

                {pendingResults.length === 0 ? (
                    <div className="bg-slate-800/40 rounded-xl p-16 border border-slate-700/50 text-center">
                        <div className="text-5xl mb-4">✅</div>
                        <h2 className="text-xl font-bold text-white mb-2">All Caught Up!</h2>
                        <p className="text-gray-400 text-sm">
                            No results are currently pending your review. You will be notified when new results are ready.
                        </p>
                        <Link href="/party/stations" className="inline-block mt-4 px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">
                            View All Stations →
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {pendingResults.map((result) => {
                            const statusCfg = STATUS_BADGE[result.certification_status]
                                || { label: result.certification_status, color: 'bg-gray-500/20 text-gray-300' };

                            const totalVotes = result.valid_votes || 0;
                            const topCandidate = [...(result.candidate_votes || [])]
                                .sort((a, b) => b.votes - a.votes)[0];

                            return (
                                <div key={result.id}
                                     className="bg-slate-800/40 rounded-xl border border-amber-500/30 overflow-hidden">

                                    {/* Station header */}
                                    <div className="p-5 border-b border-slate-700/50 flex flex-wrap gap-3 justify-between items-start">
                                        <div>
                                            <div className="flex items-center gap-3 flex-wrap">
                                                <h2 className="text-xl font-bold text-white">{result.polling_station_name}</h2>
                                                <span className="text-xs font-mono text-gray-500 bg-slate-900/60 px-2 py-0.5 rounded">
                                                    {result.polling_station_code}
                                                </span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${statusCfg.color}`}>
                                                    {statusCfg.label}
                                                </span>
                                            </div>
                                            <p className="text-gray-500 text-xs mt-1">Submitted: {result.submitted_at}</p>
                                        </div>
                                        <Link
                                            href={`/party/result/${result.id}`}
                                            className="px-5 py-2.5 bg-amber-500 hover:bg-amber-400 text-white font-bold rounded-lg text-sm transition-colors"
                                        >
                                            Review & Decide →
                                        </Link>
                                    </div>

                                    {/* Quick stats */}
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-5">
                                        <div className="bg-slate-900/50 rounded-lg p-3 text-center">
                                            <div className="text-xs text-gray-400 mb-1">Registered</div>
                                            <div className="text-white font-bold">{result.total_registered_voters?.toLocaleString()}</div>
                                        </div>
                                        <div className="bg-slate-900/50 rounded-lg p-3 text-center">
                                            <div className="text-xs text-gray-400 mb-1">Votes Cast</div>
                                            <div className="text-white font-bold">{result.total_votes_cast?.toLocaleString()}</div>
                                        </div>
                                        <div className="bg-slate-900/50 rounded-lg p-3 text-center">
                                            <div className="text-xs text-gray-400 mb-1">Turnout</div>
                                            <div className="text-amber-300 font-bold">{result.turnout_percentage}%</div>
                                        </div>
                                        <div className="bg-slate-900/50 rounded-lg p-3 text-center">
                                            <div className="text-xs text-gray-400 mb-1">Leading</div>
                                            <div className="text-teal-300 font-bold text-xs">
                                                {topCandidate ? `${topCandidate.party_abbr}: ${topCandidate.votes?.toLocaleString()}` : '—'}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Candidate mini-breakdown */}
                                    {result.candidate_votes?.length > 0 && (
                                        <div className="px-5 pb-4">
                                            <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Candidate Results</div>
                                            <div className="space-y-2">
                                                {result.candidate_votes.map((cv, idx) => {
                                                    const pct = totalVotes > 0
                                                        ? ((cv.votes / totalVotes) * 100).toFixed(1)
                                                        : 0;
                                                    return (
                                                        <div key={idx} className="flex items-center gap-3">
                                                            <div className="w-2 h-2 rounded-full flex-shrink-0"
                                                                 style={{ backgroundColor: cv.party_color }} />
                                                            <span className="text-gray-300 text-sm w-32 truncate">{cv.candidate_name}</span>
                                                            <span className="text-gray-500 text-xs w-10">{cv.party_abbr}</span>
                                                            <div className="flex-1 bg-slate-700 rounded-full h-2">
                                                                <div className="h-2 rounded-full"
                                                                     style={{ width: `${pct}%`, backgroundColor: cv.party_color }} />
                                                            </div>
                                                            <span className="text-white text-sm font-semibold w-16 text-right">
                                                                {cv.votes?.toLocaleString()}
                                                            </span>
                                                            <span className="text-gray-500 text-xs w-10 text-right">{pct}%</span>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    {/* Other parties' decisions */}
                                    {result.other_party_acceptances?.length > 0 && (
                                        <div className="px-5 pb-5">
                                            <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Other Parties' Decisions</div>
                                            <div className="flex flex-wrap gap-2">
                                                {result.other_party_acceptances.map((pa, idx) => (
                                                    <span key={idx} className={`px-2 py-1 rounded-full text-xs font-semibold border ${
                                                        pa.status === 'accepted' ? 'bg-teal-500/20 text-teal-300 border-teal-500/30' :
                                                        pa.status === 'rejected' ? 'bg-red-500/20 text-red-300 border-red-500/30' :
                                                        'bg-yellow-500/20 text-yellow-300 border-yellow-500/30'
                                                    }`}>
                                                        {pa.abbr}: {pa.status === 'accepted_with_reservation' ? 'Reserved' : pa.status}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}