import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';

const STATUS_CONFIG = {
    nationally_certified:     { label: 'Certified',      bg: 'bg-teal-500/20',   border: 'border-teal-500/50',   text: 'text-teal-300' },
    admin_area_certified:     { label: 'Area Certified', bg: 'bg-blue-500/20',   border: 'border-blue-500/50',   text: 'text-blue-300' },
    constituency_certified:   { label: 'Const. Cert.',   bg: 'bg-cyan-500/20',   border: 'border-cyan-500/50',   text: 'text-cyan-300' },
    ward_certified:           { label: 'Ward Cert.',     bg: 'bg-indigo-500/20', border: 'border-indigo-500/50', text: 'text-indigo-300' },
    pending_national:         { label: 'Pending',        bg: 'bg-amber-500/20',  border: 'border-amber-500/50',  text: 'text-amber-300' },
    pending_admin_area:       { label: 'Pending',        bg: 'bg-amber-500/20',  border: 'border-amber-500/50',  text: 'text-amber-300' },
    pending_constituency:     { label: 'Pending',        bg: 'bg-amber-500/20',  border: 'border-amber-500/50',  text: 'text-amber-300' },
    pending_ward:             { label: 'Pending',        bg: 'bg-amber-500/20',  border: 'border-amber-500/50',  text: 'text-amber-300' },
    pending_party_acceptance: { label: 'Pending',        bg: 'bg-amber-500/20',  border: 'border-amber-500/50',  text: 'text-amber-300' },
    submitted:                { label: 'Pending',        bg: 'bg-amber-500/20',  border: 'border-amber-500/50',  text: 'text-amber-300' },
    not_reported:             { label: 'Not Reported',   bg: 'bg-slate-500/20',  border: 'border-slate-500/50',  text: 'text-slate-300' },
};

const PARTY_CFG = {
    accepted:                  { label: 'Accepted',     color: 'bg-teal-500/20 text-teal-300 border-teal-500/30',    icon: '✓' },
    accepted_with_reservation: { label: 'Reserved',     color: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30', icon: '⚠' },
    rejected:                  { label: 'Disputed',     color: 'bg-red-500/20 text-red-300 border-red-500/30',       icon: '✗' },
    pending:                   { label: 'Pending',      color: 'bg-gray-500/20 text-gray-400 border-gray-500/30',    icon: '○' },
};

function StationCard({ station, isPublished }) {
    const statusInfo  = STATUS_CONFIG[station.status] || STATUS_CONFIG.not_reported;
    const hasResult   = station.total_votes_cast != null;
    const totalVotes  = station.valid_votes || 0;
    const turnout     = hasResult && station.registered_voters > 0
        ? ((station.total_votes_cast / station.registered_voters) * 100).toFixed(1)
        : null;

    return (
        <div className="bg-slate-900/50 rounded-lg border border-slate-700/30 overflow-hidden">
            {/* Header */}
            <div className="p-4 flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                    <div className="font-bold text-white text-base leading-tight">{station.name}</div>
                    <div className="text-gray-400 text-xs mt-0.5">
                        Code: <span className="font-mono">{station.code}</span>
                        {' · '}{station.registered_voters?.toLocaleString()} registered voters
                    </div>
                </div>
                <span className={`flex-shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold border ${statusInfo.bg} ${statusInfo.border} ${statusInfo.text}`}>
                    {statusInfo.label}
                </span>
            </div>

            {hasResult ? (
                <div className="px-4 pb-4 space-y-3">
                    {/* Vote summary */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        {[
                            { label: 'Votes Cast', value: station.total_votes_cast?.toLocaleString(), color: 'text-white' },
                            { label: 'Valid',       value: station.valid_votes?.toLocaleString(),      color: 'text-teal-300' },
                            { label: 'Rejected',    value: station.rejected_votes?.toLocaleString(),  color: 'text-amber-300' },
                            { label: 'Turnout',     value: turnout ? `${turnout}%` : '—',             color: 'text-blue-300' },
                        ].map(s => (
                            <div key={s.label} className="bg-slate-800/60 rounded-md p-2 text-center">
                                <div className={`text-sm font-bold ${s.color}`}>{s.value}</div>
                                <div className="text-gray-500 text-xs">{s.label}</div>
                            </div>
                        ))}
                    </div>

                    {/* Candidate votes — always shown */}
                    {station.candidate_votes?.length > 0 && (
                        <div>
                            <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Candidate Results</div>
                            <div className="space-y-2">
                                {station.candidate_votes.map((cv, idx) => {
                                    const pct   = totalVotes > 0 ? ((cv.votes / totalVotes) * 100).toFixed(1) : '0.0';
                                    const color = (cv.party_color || '#6b7280').split(',')[0].trim();
                                    return (
                                        <div key={idx}>
                                            <div className="flex items-center justify-between mb-1">
                                                <div className="flex items-center gap-2 flex-1 min-w-0">
                                                    <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: color }} />
                                                    <span className="text-sm text-gray-200 truncate">{cv.candidate_name}</span>
                                                    <span className="text-xs text-gray-500 flex-shrink-0">{cv.party_abbr}</span>
                                                </div>
                                                <div className="flex items-baseline gap-1.5 ml-2 flex-shrink-0">
                                                    <span className="text-sm font-bold text-white">{Number(cv.votes).toLocaleString()}</span>
                                                    <span className="text-xs text-gray-500">{pct}%</span>
                                                </div>
                                            </div>
                                            <div className="h-1.5 rounded-full bg-slate-700 ml-4 overflow-hidden">
                                                <div className="h-1.5 rounded-full" style={{ width: `${pct}%`, backgroundColor: color }} />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Published-only: party status + photo */}
                    {isPublished && (
                        <>
                            {station.party_acceptances?.length > 0 && (
                                <div className="border-t border-slate-700/30 pt-3">
                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Party Representative Status</div>
                                    <div className="space-y-1.5">
                                        {station.party_acceptances.map((pa, idx) => {
                                            const cfg = PARTY_CFG[pa.status] || PARTY_CFG.pending;
                                            return (
                                                <div key={idx}>
                                                    <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold border ${cfg.color}`}>
                                                        {cfg.icon} {pa.party_abbr} — {cfg.label}
                                                    </span>
                                                    {pa.comments && (
                                                        <p className="text-xs text-gray-400 italic mt-0.5 ml-2 pl-2 border-l border-gray-600/50">
                                                            "{pa.comments}"
                                                        </p>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {station.photo_url && (
                                <div className="border-t border-slate-700/30 pt-3">

                                     <a   href={station.photo_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 text-sm text-blue-400 hover:text-blue-300 transition-colors"
                                    >
                                        📄 View Result Sheet Photo
                                    </a>
                                </div>
                            )}
                        </>
                    )}
                </div>
            ) : (
                <div className="px-4 pb-4">
                    <div className="bg-slate-800/40 rounded-md p-3 text-center">
                        <p className="text-gray-500 text-xs">No results submitted yet</p>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function ResultsStations({ election, stations, isPublished = false }) {
    useInertiaPrefetch(['/results', '/results/map']);

    if (!election) {
        return (
            <AppLayout>
                <div className="container mx-auto px-4 py-12">
                    <div className="text-center p-12 bg-slate-800/40 rounded-xl border border-slate-700/50">
                        <h1 className="text-3xl font-bold text-white mb-4">No Results Available</h1>
                        <p className="text-gray-400 mb-6">There is no active election at this time.</p>
                        <Link href="/" className="px-6 py-3 bg-pink-600 text-white rounded-lg font-semibold">Back Home</Link>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const stationList     = stations || [];
    const certifiedCount  = stationList.filter(s => s.status === 'nationally_certified').length;
    const submittedCount  = stationList.filter(s => s.status !== 'not_reported').length;
    const notReportedCount = stationList.filter(s => s.status === 'not_reported').length;

    return (
        <AppLayout>
            <div className="container mx-auto px-4 py-8">
                <div className="max-w-7xl mx-auto">
                    {/* Page header */}
                    <div className="text-center mb-8">
                        <h1 className="text-4xl font-bold text-white mb-3">{election.name}</h1>
                        {isPublished ? (
                            <div className="inline-flex items-center gap-2 px-4 py-1.5 bg-teal-500/20 border border-teal-500/40 rounded-full text-teal-300 text-sm font-semibold mb-4">
                                ✓ Results Officially Published
                            </div>
                        ) : (
                            <div className="inline-flex items-center gap-2 px-4 py-1.5 bg-amber-500/20 border border-amber-500/40 rounded-full text-amber-300 text-sm font-semibold mb-4">
                                ⏳ Certification in Progress
                            </div>
                        )}

                        {/* Nav tabs */}
                        <div className="flex justify-center gap-3 flex-wrap">
                            <Link href="/results" prefetch className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all">
                                Summary
                            </Link>
                            <Link href="/results/map" prefetch className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all">
                                Map
                            </Link>
                            <Link href="/results/stations" prefetch className="px-6 py-3 bg-slate-700 text-white rounded-lg font-semibold shadow-lg">
                                Stations
                            </Link>
                        </div>
                    </div>

                    {/* Summary stats */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                        {[
                            { label: 'Total Stations',       value: stationList.length,     color: 'text-white' },
                            { label: 'Results Submitted',    value: submittedCount,          color: 'text-teal-300' },
                            { label: 'Nationally Certified', value: certifiedCount,          color: 'text-green-300' },
                            { label: 'Not Yet Reported',     value: notReportedCount,        color: 'text-amber-300' },
                        ].map(s => (
                            <div key={s.label} className="bg-slate-800/40 rounded-xl p-4 border border-slate-700/50 text-center">
                                <div className={`text-2xl font-bold ${s.color}`}>{s.value}</div>
                                <div className="text-gray-400 text-xs mt-0.5">{s.label}</div>
                            </div>
                        ))}
                    </div>

                    {/* Info banner for unpublished */}
                    {!isPublished && (
                        <div className="mb-4 p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-300 text-sm text-center">
                            ℹ️ Displaying provisional results. Full details (photos, party representative decisions) will be visible once officially published by the IEC Chairman.
                        </div>
                    )}

                    {/* Stations list */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">
                            All Polling Stations ({stationList.length})
                        </h2>
                        <div className="space-y-4 max-h-[700px] overflow-y-auto pr-1">
                            {stationList.map((station) => (
                                <StationCard key={station.id} station={station} isPublished={isPublished} />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
