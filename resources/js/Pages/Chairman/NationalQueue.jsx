import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const PARTY_STATUS_CFG = {
    accepted:                  { color: 'bg-teal-500/20 text-teal-300 border-teal-500/30',    icon: '✓', label: 'Accepted' },
    accepted_with_reservation: { color: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30', icon: '⚠', label: 'Reserved' },
    rejected:                  { color: 'bg-red-500/20 text-red-300 border-red-500/30',       icon: '✗', label: 'Rejected' },
};

export default function NationalQueue({ auth, pendingResults = [], pendingCount = 0 }) {
    const [expandedId, setExpandedId]     = useState(null);
    const [actionResult, setActionResult] = useState(null);
    const [comment, setComment]           = useState('');
    const [processing, setProcessing]     = useState(false);
    const [photoOpen, setPhotoOpen]       = useState(null);

    const submitAction = (resultId, type) => {
        if (type === 'reject' && !comment.trim()) return;
        setProcessing(true);
        const url  = type === 'certify' ? `/chairman/certify/${resultId}` : `/chairman/reject/${resultId}`;
        const body = type === 'certify' ? { comments: comment } : { reason: comment };
        router.post(url, body, {
            onSuccess: () => { setActionResult(null); setComment(''); },
            onFinish:  () => setProcessing(false),
        });
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="mb-6">
                    <Link href="/chairman/dashboard" className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        ← Chairman Dashboard
                    </Link>
                    <div className="flex flex-wrap gap-4 items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-white">National Certification Queue</h1>
                            <p className="text-gray-400 mt-1 text-sm">Final approval authority — results that have passed all lower certification levels</p>
                        </div>
                        <div className={`px-5 py-2.5 rounded-xl font-bold text-lg border ${
                            pendingCount > 0
                                ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                                : 'bg-teal-500/20 text-teal-300 border-teal-500/30'
                        }`}>
                            {pendingCount > 0 ? `${pendingCount} Awaiting Certification` : '✓ Queue Clear'}
                        </div>
                    </div>
                </div>

                {pendingResults.length === 0 ? (
                    <div className="bg-slate-800/40 rounded-xl p-16 border border-teal-500/20 text-center">
                        <div className="text-5xl mb-4">✅</div>
                        <h2 className="text-xl font-bold text-white mb-2">All Clear</h2>
                        <p className="text-gray-400 text-sm mb-4">No results pending national certification.</p>
                        <Link href="/chairman/all-results" className="inline-block px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">
                            View All Results →
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-5">
                        {pendingResults.map((result) => {
                            const isExpanded = expandedId === result.id;
                            const totalVotes = result.valid_votes || 0;

                            return (
                                <div key={result.id} className="bg-slate-800/40 rounded-xl border border-amber-500/30 overflow-hidden">
                                    {/* Header */}
                                    <div className="p-5 border-b border-slate-700/50">
                                        <div className="flex flex-wrap gap-4 items-start justify-between">
                                            <div>
                                                <div className="flex items-center gap-3 flex-wrap">
                                                    <h2 className="text-xl font-bold text-white">{result.polling_station_name}</h2>
                                                    <span className="font-mono text-xs text-gray-500 bg-slate-900/60 px-2 py-0.5 rounded">
                                                        {result.polling_station_code}
                                                    </span>
                                                    <span className="px-2 py-0.5 rounded-full text-xs bg-amber-500/20 text-amber-300 font-semibold">
                                                        Pending National
                                                    </span>
                                                </div>
                                                <p className="text-gray-400 text-xs mt-1">
                                                    Ward: {result.ward_name} · Submitted by {result.submitted_by} · {result.submitted_at}
                                                </p>
                                            </div>
                                            <button
                                                onClick={() => setExpandedId(isExpanded ? null : result.id)}
                                                className="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-gray-300 text-xs rounded-lg transition-colors"
                                            >
                                                {isExpanded ? 'Collapse ▲' : 'Expand Details ▼'}
                                            </button>
                                        </div>
                                    </div>

                                    {/* Vote summary */}
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 p-5">
                                        {[
                                            { label: 'Registered',  value: result.total_registered_voters?.toLocaleString(), color: 'text-gray-300' },
                                            { label: 'Votes Cast',  value: result.total_votes_cast?.toLocaleString(),         color: 'text-white' },
                                            { label: 'Turnout',     value: `${result.turnout_percentage}%`,                   color: 'text-blue-300' },
                                            { label: 'Valid Votes', value: result.valid_votes?.toLocaleString(),              color: 'text-teal-300' },
                                        ].map(s => (
                                            <div key={s.label} className="bg-slate-900/50 rounded-lg p-3 text-center">
                                                <div className={`text-lg font-bold ${s.color}`}>{s.value}</div>
                                                <div className="text-gray-500 text-xs">{s.label}</div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Expanded details */}
                                    {isExpanded && (
                                        <div className="px-5 pb-5 space-y-5 border-t border-slate-700/30 pt-4">

                                            {/* Candidate results */}
                                            {result.candidate_votes?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Candidate Results</div>
                                                    <div className="space-y-2">
                                                        {[...result.candidate_votes]
                                                            .sort((a, b) => b.votes - a.votes)
                                                            .map((cv, idx) => {
                                                                const pct = totalVotes > 0 ? ((cv.votes / totalVotes) * 100).toFixed(1) : 0;
                                                                return (
                                                                    <div key={idx} className="flex items-center gap-3">
                                                                        <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: cv.party_color }} />
                                                                        <span className="text-gray-300 text-sm w-36 truncate">
                                                                            {cv.candidate_name}
                                                                            {idx === 0 && <span className="ml-1 text-teal-400 text-xs">🏆</span>}
                                                                        </span>
                                                                        <span className="text-gray-500 text-xs w-10">{cv.party_abbr}</span>
                                                                        <div className="flex-1 bg-slate-700 rounded-full h-1.5">
                                                                            <div className="h-1.5 rounded-full" style={{ width: `${pct}%`, backgroundColor: cv.party_color }} />
                                                                        </div>
                                                                        <span className="text-white font-semibold text-sm w-16 text-right">{cv.votes?.toLocaleString()}</span>
                                                                        <span className="text-gray-500 text-xs w-10 text-right">{pct}%</span>
                                                                    </div>
                                                                );
                                                            })}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Party decisions WITH comments */}
                                            {result.party_acceptances?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Party Decisions</div>
                                                    <div className="space-y-2">
                                                        {result.party_acceptances.map((pa, idx) => {
                                                            const cfg = PARTY_STATUS_CFG[pa.status] || { color: 'bg-gray-500/20 text-gray-300 border-gray-500/30', icon: '○', label: pa.status };
                                                            return (
                                                                <div key={idx}>
                                                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${cfg.color}`}>
                                                                        {cfg.icon} {pa.abbr} — {cfg.label}
                                                                    </span>
                                                                    {pa.comments && (
                                                                        <p className="text-xs text-gray-400 italic mt-1 ml-3 pl-2 border-l-2 border-slate-600">
                                                                            "{pa.comments}"
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            )}

                                            {/* ── ALL PREVIOUS APPROVER NOTES ── */}
                                            {(result.ward_comments || result.constituency_comments || result.admin_area_comments) && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Previous Approver Notes</div>
                                                    <div className="space-y-2">
                                                        {result.ward_comments && (
                                                            <div className="p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                                                                <div className="text-xs text-blue-400 font-semibold mb-1">📋 Ward Approver Note</div>
                                                                <div className="text-blue-200 text-sm">{result.ward_comments}</div>
                                                            </div>
                                                        )}
                                                        {result.constituency_comments && (
                                                            <div className="p-3 bg-cyan-500/10 border border-cyan-500/30 rounded-lg">
                                                                <div className="text-xs text-cyan-400 font-semibold mb-1">📋 Constituency Approver Note</div>
                                                                <div className="text-cyan-200 text-sm">{result.constituency_comments}</div>
                                                            </div>
                                                        )}
                                                        {result.admin_area_comments && (
                                                            <div className="p-3 bg-violet-500/10 border border-violet-500/30 rounded-lg">
                                                                <div className="text-xs text-violet-400 font-semibold mb-1">📋 Admin-Area Approver Note</div>
                                                                <div className="text-violet-200 text-sm">{result.admin_area_comments}</div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Certification chain */}
                                            {result.certification_chain?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Certification Chain</div>
                                                    <div className="flex flex-wrap gap-2">
                                                        {result.certification_chain.map((c, idx) => (
                                                            <div key={idx} className={`px-3 py-1.5 rounded-lg text-xs font-semibold ${
                                                                c.status === 'approved' ? 'bg-teal-500/20 text-teal-300' : 'bg-red-500/20 text-red-300'
                                                            }`}>
                                                                {c.level}: {c.status === 'approved' ? '✓' : '✗'}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {result.photo_url && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Result Sheet</div>
                                                    <button onClick={() => setPhotoOpen(result.photo_url)}
                                                        className="inline-flex items-center gap-2 text-blue-400 hover:text-blue-300 text-sm transition-colors">
                                                        📄 View Result Sheet Photo
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Action Buttons */}
                                    <div className="p-5 border-t border-slate-700/50 flex flex-wrap gap-3">
                                        <button
                                            onClick={() => { setActionResult({ id: result.id, type: 'certify' }); setComment(''); }}
                                            className="flex-1 min-w-[160px] py-3 bg-green-600 hover:bg-green-500 text-white font-bold rounded-xl transition-colors"
                                        >
                                            🏛️ Certify Nationally
                                        </button>
                                        <button
                                            onClick={() => { setActionResult({ id: result.id, type: 'reject' }); setComment(''); }}
                                            className="flex-1 min-w-[160px] py-3 bg-red-700 hover:bg-red-600 text-white font-bold rounded-xl transition-colors"
                                        >
                                            ↩ Return to Admin Area
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* ── Action Modal ── */}
            {actionResult && (
                <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
                    <div
                        className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-lg shadow-2xl flex flex-col"
                        style={{ maxHeight: 'min(92vh, 560px)' }}
                    >
                        {/* Header */}
                        <div className="px-6 pt-6 pb-4 flex-shrink-0 border-b border-slate-800">
                            <div className="flex items-start justify-between gap-4">
                                <h2 className="text-lg font-bold text-white">
                                    {actionResult.type === 'certify' ? '🏛️ Confirm National Certification' : '↩ Return to Admin Area'}
                                </h2>
                                <button onClick={() => setActionResult(null)}
                                    className="text-gray-400 hover:text-white text-2xl w-8 h-8 flex items-center justify-center flex-shrink-0">
                                    ×
                                </button>
                            </div>
                        </div>

                        {/* Scrollable Content */}
                        <div className="px-6 py-5 overflow-y-auto flex-1 space-y-4">
                            <div className={`p-4 rounded-xl text-sm ${
                                actionResult.type === 'certify'
                                    ? 'bg-green-500/10 border border-green-500/30 text-green-300'
                                    : 'bg-red-500/10 border border-red-500/30 text-red-300'
                            }`}>
                                {actionResult.type === 'certify'
                                    ? 'This result will be officially NATIONALLY CERTIFIED. This is the final step in the certification pipeline.'
                                    : 'This result will be returned to the Admin Area level for further review. Please provide a clear reason.'}
                            </div>

                            <div className="bg-slate-800/60 border border-slate-600/60 rounded-xl p-4">
                                <label className="block text-gray-200 font-semibold mb-2 text-sm">
                                    {actionResult.type === 'certify' ? 'Certification Notes (optional)' : 'Reason for Return (required)'}
                                    {actionResult.type === 'reject' && <span className="text-red-400 ml-1">*</span>}
                                </label>
                                <textarea
                                    value={comment}
                                    onChange={(e) => setComment(e.target.value)}
                                    rows={4}
                                    required={actionResult.type === 'reject'}
                                    className="w-full px-4 py-3 bg-slate-900/80 border-2 border-slate-600 focus:border-teal-500 rounded-xl text-white resize-none focus:outline-none transition-colors text-sm"
                                    placeholder={actionResult.type === 'certify'
                                        ? 'Optional certification notes for the audit trail…'
                                        : 'Clearly explain why this result is being returned…'}
                                />
                            </div>
                        </div>

                        {/* Sticky Footer */}
                        <div className="px-6 py-4 border-t border-slate-800 flex-shrink-0 flex gap-3">
                            <button
                                onClick={() => submitAction(actionResult.id, actionResult.type)}
                                disabled={processing || (actionResult.type === 'reject' && !comment.trim())}
                                className={`flex-1 py-3 font-bold text-white rounded-xl disabled:opacity-40 disabled:cursor-not-allowed transition-colors ${
                                    actionResult.type === 'certify' ? 'bg-green-600 hover:bg-green-500' : 'bg-red-700 hover:bg-red-600'
                                }`}
                            >
                                {processing ? 'Processing…' : actionResult.type === 'certify' ? 'Confirm Certification' : 'Confirm Return'}
                            </button>
                            <button onClick={() => setActionResult(null)} disabled={processing}
                                className="px-5 py-3 bg-slate-700 hover:bg-slate-600 text-white rounded-xl font-semibold transition-colors">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Photo lightbox */}
            {photoOpen && (
                <div className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4" onClick={() => setPhotoOpen(null)}>
                    <div className="relative max-w-5xl w-full" onClick={(e) => e.stopPropagation()}>
                        <button onClick={() => setPhotoOpen(null)} className="absolute -top-10 right-0 text-white text-3xl hover:text-gray-300">×</button>
                        <img src={photoOpen} alt="Result sheet" className="w-full rounded-xl shadow-2xl" />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
