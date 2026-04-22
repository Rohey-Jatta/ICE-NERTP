import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

const STATUS_LABELS = {
    submitted:                  { label: 'Submitted',              color: 'bg-gray-500/20 text-gray-300' },
    pending_party_acceptance:   { label: 'Awaiting Party',         color: 'bg-yellow-500/20 text-yellow-300' },
    pending_ward:               { label: 'Pending Ward',           color: 'bg-amber-500/20 text-amber-300' },
    ward_certified:             { label: 'Ward Certified',         color: 'bg-teal-500/20 text-teal-300' },
    pending_constituency:       { label: 'At Constituency',        color: 'bg-blue-500/20 text-blue-300' },
    constituency_certified:     { label: 'Constituency Certified', color: 'bg-cyan-500/20 text-cyan-300' },
    pending_admin_area:         { label: 'At Admin Area',          color: 'bg-purple-500/20 text-purple-300' },
    admin_area_certified:       { label: 'Admin Area Certified',   color: 'bg-violet-500/20 text-violet-300' },
    pending_national:           { label: 'At National',            color: 'bg-pink-500/20 text-pink-300' },
    nationally_certified:       { label: 'Nationally Certified',   color: 'bg-green-500/20 text-green-300' },
};

const PARTY_STATUS_CONFIG = {
    accepted:                  { label: 'Accepted',             color: 'bg-teal-500/20 text-teal-300 border-teal-500/30',    icon: '✓' },
    accepted_with_reservation: { label: 'Accepted (Reserved)',  color: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30', icon: '⚠' },
    rejected:                  { label: 'Rejected',             color: 'bg-red-500/20 text-red-300 border-red-500/30',       icon: '✗' },
    pending:                   { label: 'Pending',              color: 'bg-gray-500/20 text-gray-300 border-gray-500/30',    icon: '…' },
};

const ACTION_CONFIG = {
    approve: {
        title:        'Certify at Ward Level',
        description:  'This result will be certified and automatically promoted to the Constituency approval queue.',
        confirmBtn:   'Certify & Promote to Constituency',
        confirmColor: 'bg-teal-600 hover:bg-teal-700',
        commentReq:   false,
        commentLabel: 'Additional Comments (optional)',
        placeholder:  'Add any notes or observations…',
    },
    approve_reservation: {
        title:        'Certify with Reservation',
        description:  'Result will be certified but flagged with your reservation note. It will be promoted to the Constituency queue.',
        confirmBtn:   'Certify with Reservation',
        confirmColor: 'bg-amber-600 hover:bg-amber-700',
        commentReq:   true,
        commentLabel: 'Reservation Note (required)',
        placeholder:  'Describe your reservation or concern…',
    },
    reject: {
        title:        'Reject & Return to Polling Station',
        description:  'This result will be returned to the polling officer for correction.',
        confirmBtn:   'Confirm Rejection',
        confirmColor: 'bg-red-600 hover:bg-red-700',
        commentReq:   true,
        commentLabel: 'Rejection Reason (required)',
        placeholder:  'Explain clearly why this result is being rejected and what needs to be corrected…',
    },
};

export default function WardApprovalQueue({ auth, ward, results = [], filter = 'pending', counts = {} }) {
    const [selectedResult, setSelectedResult] = useState(null);
    const [action, setAction]                 = useState(null);
    const [comment, setComment]               = useState('');
    const [processing, setProcessing]         = useState(false);
    const [flash, setFlash]                   = useState(null);

    const filterTabs = [
        { key: 'pending',        label: 'Pending',        count: counts.pending        || 0, color: 'amber'  },
        { key: 'awaiting_party', label: 'Awaiting Party', count: counts.awaiting_party || 0, color: 'yellow' },
        { key: 'approved',       label: 'Certified',      count: counts.approved       || 0, color: 'teal'   },
        { key: 'rejected',       label: 'Rejected',       count: counts.rejected       || 0, color: 'red'    },
        { key: 'all',            label: 'All',            count: counts.all            || 0, color: 'slate'  },
    ];

    const tabColors = {
        amber:  { active: 'bg-amber-500 text-white',  dot: 'bg-amber-400' },
        yellow: { active: 'bg-yellow-500 text-white', dot: 'bg-yellow-400' },
        teal:   { active: 'bg-teal-500 text-white',   dot: 'bg-teal-400'  },
        red:    { active: 'bg-red-500 text-white',    dot: 'bg-red-400'   },
        slate:  { active: 'bg-slate-500 text-white',  dot: 'bg-slate-400' },
    };

    const handleFilterChange = (key) => {
        router.get('/ward/approval-queue', { filter: key }, { preserveState: false });
    };

    const openAction = (result, actionType) => {
        setSelectedResult(result);
        setAction(actionType);
        setComment('');
        setFlash(null);
    };

    const closePanel = () => {
        setSelectedResult(null);
        setAction(null);
        setComment('');
        setFlash(null);
    };

    const submitAction = async () => {
        if (!selectedResult || !action) return;
        if (ACTION_CONFIG[action].commentReq && !comment.trim()) {
            setFlash({ type: 'error', text: 'A comment is required for this action.' });
            return;
        }
        setProcessing(true);
        setFlash(null);
        const endpoints = {
            approve:             `/ward/approve/${selectedResult.id}`,
            approve_reservation: `/ward/approve-with-reservation/${selectedResult.id}`,
            reject:              `/ward/reject/${selectedResult.id}`,
        };
        try {
            await new Promise((resolve, reject) => {
                router.post(endpoints[action], { comments: comment }, {
                    onSuccess: () => resolve(),
                    onError:   (e) => reject(e),
                    onFinish:  () => setProcessing(false),
                });
            });
            closePanel();
        } catch {
            setFlash({ type: 'error', text: 'An error occurred. Please try again.' });
            setProcessing(false);
        }
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/ward/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Ward Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Ward Approval Queue</h1>
                    {ward?.name && <p className="text-teal-300 mt-1">{ward.name}</p>}
                </div>

                {flash && !selectedResult && (
                    <div className={`mb-4 p-4 rounded-xl border ${
                        flash.type === 'error' ? 'bg-red-500/20 border-red-500/50 text-red-300' : 'bg-teal-500/20 border-teal-500/50 text-teal-300'
                    }`}>
                        {flash.text}
                    </div>
                )}

                {/* Filter Tabs */}
                <div className="flex gap-2 mb-6 flex-wrap">
                    {filterTabs.map(tab => {
                        const cfg    = tabColors[tab.color];
                        const active = filter === tab.key;
                        return (
                            <button
                                key={tab.key}
                                onClick={() => handleFilterChange(tab.key)}
                                className={`flex items-center gap-2 px-4 py-2 rounded-lg font-semibold text-sm transition-all ${
                                    active ? cfg.active : 'bg-slate-800/40 text-gray-400 hover:bg-slate-700 border border-slate-700/50'
                                }`}
                            >
                                {!active && <span className={`w-2 h-2 rounded-full ${cfg.dot}`} />}
                                {tab.label}
                                <span className={`text-xs px-2 py-0.5 rounded-full ${active ? 'bg-white/20' : 'bg-slate-700'}`}>
                                    {tab.count}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Awaiting party info banner */}
                {filter === 'awaiting_party' && (
                    <div className="mb-4 p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-xl">
                        <p className="text-yellow-300 text-sm">
                            ℹ️ These results are awaiting responses from party representatives. They will automatically move to your <strong>Pending</strong> queue once all assigned parties have responded.
                        </p>
                    </div>
                )}

                {/* Results List */}
                {results.length === 0 ? (
                    <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                        <div className="text-5xl mb-4">
                            {filter === 'pending' ? '⏳' : filter === 'awaiting_party' ? '🤝' : filter === 'approved' ? '✅' : filter === 'rejected' ? '↩' : '📋'}
                        </div>
                        <p className="text-gray-400 mt-4">
                            {filter === 'pending' ? 'No results pending certification'
                            : filter === 'awaiting_party' ? 'No results awaiting party acceptance'
                            : filter === 'approved' ? 'No certified results yet'
                            : filter === 'rejected' ? 'No rejected results'
                            : 'No results found'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {results.map(result => {
                            const statusCfg     = STATUS_LABELS[result.certification_status] || { label: result.certification_status, color: 'bg-gray-500/20 text-gray-300' };
                            const isPending     = result.certification_status === 'pending_ward';
                            const isAwaitingPty = result.certification_status === 'pending_party_acceptance';

                            return (
                                <div
                                    key={result.id}
                                    className={`bg-slate-800/40 rounded-xl border transition-all ${
                                        isPending ? 'border-amber-500/30' : isAwaitingPty ? 'border-yellow-500/20' : 'border-slate-700/50'
                                    }`}
                                >
                                    {/* Result Header */}
                                    <div className="p-5 flex flex-wrap gap-4 justify-between items-start">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-3 mb-1 flex-wrap">
                                                <h3 className="text-lg font-bold text-white">{result.polling_station}</h3>
                                                <span className="text-xs font-mono text-gray-500">{result.polling_station_code}</span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${statusCfg.color}`}>
                                                    {statusCfg.label}
                                                </span>
                                                {result.rejection_count > 0 && (
                                                    <span className="px-2 py-0.5 rounded-full text-xs bg-orange-500/20 text-orange-300">
                                                        Rejected {result.rejection_count}×
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-gray-400 text-sm">
                                                Submitted by <strong className="text-gray-300">{result.officer}</strong> · {result.submitted_at}
                                            </p>
                                        </div>
                                        <div className="text-right text-sm flex-shrink-0">
                                            <div className="text-gray-400">Party Status</div>
                                            <div className={`font-semibold ${
                                                result.party_total === 0 ? 'text-gray-500' :
                                                result.party_accepted === result.party_total ? 'text-green-300' : 'text-amber-300'
                                            }`}>
                                                {result.party_total === 0 ? 'N/A' : `${result.party_accepted}/${result.party_total} Accepted`}
                                            </div>
                                        </div>
                                    </div>

                                    {isAwaitingPty && (
                                        <div className="mx-5 mb-3 p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-lg">
                                            <p className="text-yellow-300 text-xs">
                                                ⏳ Awaiting party representative responses — {result.party_accepted}/{result.party_total} parties have responded.
                                            </p>
                                        </div>
                                    )}

                                    {/* Vote Summary */}
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 px-5 pb-4">
                                        {[
                                            { label: 'Registered', value: result.total_registered_voters?.toLocaleString(), color: 'text-white' },
                                            { label: 'Votes Cast', value: result.total_votes_cast?.toLocaleString(),         color: 'text-white' },
                                            { label: 'Valid',      value: result.valid_votes?.toLocaleString(),              color: 'text-teal-300' },
                                            { label: 'Turnout',    value: `${result.turnout}%`,                              color: 'text-white' },
                                        ].map(s => (
                                            <div key={s.label} className="bg-slate-900/50 p-3 rounded-lg">
                                                <p className="text-xs text-gray-400 mb-0.5">{s.label}</p>
                                                <p className={`font-bold ${s.color}`}>{s.value}</p>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Candidate votes */}
                                    {result.candidate_votes?.length > 0 && (
                                        <div className="px-5 pb-4">
                                            <div className="text-xs text-gray-500 mb-2 uppercase tracking-wide">Candidate Results</div>
                                            <div className="space-y-2">
                                                {result.candidate_votes.map((cv, idx) => {
                                                    const pct = result.valid_votes > 0 ? ((cv.votes / result.valid_votes) * 100).toFixed(1) : 0;
                                                    return (
                                                        <div key={idx} className="flex items-center gap-3">
                                                            <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: cv.party_color }} />
                                                            <span className="text-gray-300 text-sm w-36 truncate">{cv.candidate}</span>
                                                            <span className="text-xs text-gray-500 w-10">{cv.party}</span>
                                                            <div className="flex-1 bg-slate-700 rounded-full h-1.5">
                                                                <div className="h-1.5 rounded-full" style={{ width: `${pct}%`, backgroundColor: cv.party_color }} />
                                                            </div>
                                                            <span className="text-white text-sm font-semibold w-16 text-right">{cv.votes?.toLocaleString()}</span>
                                                            <span className="text-gray-400 text-xs w-10 text-right">{pct}%</span>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    {/* Party status WITH comments */}
                                    {result.party_acceptances?.length > 0 && (
                                        <div className="px-5 pb-4">
                                            <div className="text-xs text-gray-500 mb-2 uppercase tracking-wide">Party Representative Status</div>
                                            <div className="space-y-2">
                                                {result.party_acceptances.map((pa, idx) => {
                                                    const cfg = PARTY_STATUS_CONFIG[pa.status] || PARTY_STATUS_CONFIG.pending;
                                                    return (
                                                        <div key={idx}>
                                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${cfg.color}`}>
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

                                    {/* Photo */}
                                    {result.photo_url && (
                                        <div className="px-5 pb-4">
                                            <a href={result.photo_url} target="_blank" rel="noopener noreferrer"
                                               className="inline-flex items-center gap-2 text-sm text-blue-400 hover:text-blue-300">
                                                📄 View Result Sheet Photo
                                            </a>
                                        </div>
                                    )}

                                    {/* Prior rejection reason */}
                                    {result.last_rejection_reason && (
                                        <div className="mx-5 mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg">
                                            <div className="text-xs text-red-400 mb-1 font-semibold">Previous Rejection Reason</div>
                                            <div className="text-red-300 text-sm">{result.last_rejection_reason}</div>
                                        </div>
                                    )}

                                    {/* Ward cert comments (if already certified — shown in approved tab) */}
                                    {result.ward_comments && (
                                        <div className="mx-5 mb-4 p-3 bg-teal-500/10 border border-teal-500/30 rounded-lg">
                                            <div className="text-xs text-teal-400 mb-1 font-semibold">Ward Certification Note</div>
                                            <div className="text-teal-200 text-sm">{result.ward_comments}</div>
                                        </div>
                                    )}

                                    {/* Action Buttons */}
                                    {isPending && (
                                        <div className="px-5 pb-5 flex flex-wrap gap-3 border-t border-slate-700/50 pt-4 mt-2">
                                            <button onClick={() => openAction(result, 'approve')} className="flex-1 min-w-[140px] px-4 py-3 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-lg transition-colors">
                                                ✓ Certify
                                            </button>
                                            <button onClick={() => openAction(result, 'approve_reservation')} className="flex-1 min-w-[160px] px-4 py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-lg transition-colors">
                                                ⚠ Certify with Reservation
                                            </button>
                                            <button onClick={() => openAction(result, 'reject')} className="flex-1 min-w-[140px] px-4 py-3 bg-red-700 hover:bg-red-600 text-white font-bold rounded-lg transition-colors">
                                                ✗ Reject &amp; Return
                                            </button>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* ── Action Modal ── */}
            {selectedResult && action && (
                <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
                    <div
                        className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-lg shadow-2xl flex flex-col"
                        style={{ maxHeight: 'min(92vh, 580px)' }}
                    >
                        {/* Modal Header */}
                        <div className="px-6 pt-6 pb-4 flex-shrink-0 border-b border-slate-800">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-lg font-bold text-white">{ACTION_CONFIG[action].title}</h2>
                                    <p className="text-gray-400 text-xs mt-0.5">
                                        {selectedResult.polling_station} · {selectedResult.polling_station_code}
                                    </p>
                                </div>
                                <button
                                    onClick={closePanel}
                                    disabled={processing}
                                    className="text-gray-400 hover:text-white text-2xl leading-none flex-shrink-0 w-8 h-8 flex items-center justify-center"
                                >
                                    ×
                                </button>
                            </div>
                        </div>

                        {/* Scrollable Content */}
                        <div className="px-6 py-5 overflow-y-auto flex-1 space-y-4">
                            {/* Description */}
                            <div className="p-3 bg-slate-800/60 rounded-xl text-sm text-gray-300">
                                {ACTION_CONFIG[action].description}
                            </div>

                            {/* Stats */}
                            <div className="grid grid-cols-3 gap-3">
                                {[
                                    { label: 'Votes Cast',  value: selectedResult.total_votes_cast?.toLocaleString() },
                                    { label: 'Valid Votes', value: selectedResult.valid_votes?.toLocaleString() },
                                    { label: 'Turnout',     value: `${selectedResult.turnout}%` },
                                ].map(s => (
                                    <div key={s.label} className="bg-slate-800/50 p-3 rounded-xl text-center">
                                        <div className="text-xs text-gray-400 mb-0.5">{s.label}</div>
                                        <div className="text-white font-bold">{s.value}</div>
                                    </div>
                                ))}
                            </div>

                            {flash && (
                                <div className={`p-3 rounded-lg text-sm ${flash.type === 'error' ? 'bg-red-500/20 text-red-300' : 'bg-teal-500/20 text-teal-300'}`}>
                                    {flash.text}
                                </div>
                            )}

                            {/* Comment section */}
                            <div className="bg-slate-800/60 border border-slate-600/60 rounded-xl p-4">
                                <label className="block text-gray-200 font-semibold mb-2 text-sm">
                                    {ACTION_CONFIG[action].commentLabel}
                                    {ACTION_CONFIG[action].commentReq && <span className="text-red-400 ml-1">*</span>}
                                </label>
                                <textarea
                                    value={comment}
                                    onChange={(e) => setComment(e.target.value)}
                                    rows={4}
                                    placeholder={ACTION_CONFIG[action].placeholder}
                                    className="w-full px-4 py-3 bg-slate-900/80 border-2 border-slate-600 focus:border-teal-500 rounded-xl text-white resize-none focus:outline-none transition-colors text-sm"
                                />
                            </div>
                        </div>

                        {/* Sticky Footer Buttons */}
                        <div className="px-6 py-4 border-t border-slate-800 flex-shrink-0 flex gap-3">
                            <button
                                onClick={submitAction}
                                disabled={processing || (ACTION_CONFIG[action].commentReq && !comment.trim())}
                                className={`flex-1 py-3 rounded-xl font-bold text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors ${ACTION_CONFIG[action].confirmColor}`}
                            >
                                {processing ? 'Processing…' : ACTION_CONFIG[action].confirmBtn}
                            </button>
                            <button
                                onClick={closePanel}
                                disabled={processing}
                                className="px-5 py-3 bg-slate-700 hover:bg-slate-600 text-white rounded-xl font-semibold transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
