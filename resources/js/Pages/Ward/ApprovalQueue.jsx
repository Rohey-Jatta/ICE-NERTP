import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { RESULT_STATUS, getResultStatusMeta } from '@/Utils/resultStatus';

const PARTY_STATUS_CONFIG = {
    accepted:                  { label: 'Accepted',             color: 'bg-iec-pink-500/20 text-iec-pink-600 border-teal-500/30',     icon: '✓' },
    accepted_with_reservation: { label: 'Accepted (Reserved)',  color: 'bg-iec-pink-50 text-iec-pink-600 border-iec-pink-200',         icon: '⚠' },
    rejected:                  { label: 'Rejected',             color: 'bg-red-500/20 text-red-300 border-red-500/30',                icon: '✗' },
    pending:                   { label: 'Pending',              color: 'bg-slate-100 text-slate-500 border-slate-200',               icon: '…' },
};

const ACTION_CONFIG = {
    approve: {
        title:        'Certify at Ward Level',
        description:  'This result will be certified and automatically promoted to the Constituency approval queue.',
        confirmBtn:   'Certify & Promote to Constituency',
        confirmColor: 'bg-iec-pink-600 hover:bg-iec-pink-700',
        commentReq:   false,
        commentLabel: 'Additional Comments (optional)',
        placeholder:  'Add any notes or observations…',
    },
    approve_reservation: {
        title:        'Certify with Reservation',
        description:  'Result will be certified but flagged with your reservation note. It will be promoted to the Constituency queue.',
        confirmBtn:   'Certify with Reservation',
        confirmColor: 'bg-iec-pink-600 hover:bg-iec-pink-700',
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

    // Parallel workflow: 3 tabs only — no more blocking "Awaiting Party" tab
    const filterTabs = [
        { key: 'pending',   label: 'Pending',   count: counts.pending   || 0, color: 'pink'  },
        { key: 'approved',  label: 'Certified', count: counts.approved  || 0, color: 'teal'  },
        { key: 'rejected',  label: 'Rejected',  count: counts.rejected  || 0, color: 'red'   },
        { key: 'all',       label: 'All',       count: counts.all       || 0, color: 'slate' },
    ];

    const tabColors = {
        pink:  { active: 'bg-iec-pink-600 text-white',      dot: 'bg-iec-pink-400' },
        teal:  { active: 'bg-iec-pink-500 text-white',      dot: 'bg-teal-400'     },
        red:   { active: 'bg-red-500 text-white',           dot: 'bg-red-400'      },
        slate: { active: 'bg-slate-500 text-iec-navy',      dot: 'bg-slate-400'    },
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

    const submitAction = () => {
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

        router.post(endpoints[action], { comments: comment }, {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => {
                closePanel();
            },
            onError: (errors) => {
                setFlash({ type: 'error', text: 'An error occurred. Please try again.' });
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    {/* <Link href="/ward/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                        ← Ward Dashboard
                    </Link> */}
                    <h1 className="text-3xl font-bold text-iec-navy">Ward Approval Queue</h1>
                    {ward?.name && <p className="text-iec-pink-600 mt-1">{ward.name}</p>}
                    {/* Parallel workflow info — shown once, not on every card */}
                    {/* <div className="mt-2 inline-flex items-center gap-2 px-3 py-1.5 bg-iec-pink-500/10 border border-iec-pink-500/20 rounded-lg">
                        <span className="text-iec-pink-600 text-xs font-semibold">
                            ⚡ Parallel Review — Ward Approvers and Party Representatives review simultaneously. Party responses are informational and do not block your actions.
                        </span>
                    </div> */}
                </div>

                {flash && !selectedResult && (
                    <div className={`mb-4 p-4 rounded-xl border ${
                        flash.type === 'error' ? 'bg-red-500/20 border-red-500/50 text-red-300' : 'bg-iec-pink-500/20 border-teal-500/50 text-iec-pink-600'
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
                                    active ? cfg.active : 'bg-white text-slate-700 hover:bg-white border border-slate-200'
                                }`}
                            >
                                {!active && <span className={`w-2 h-2 rounded-full ${cfg.dot}`} />}
                                {tab.label}
                                <span className={`text-xs px-2 py-0.5 rounded-full ${active ? 'bg-pink-500' : 'bg-white'}`}>
                                    {tab.count}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Results List */}
                {results.length === 0 ? (
                    <div className="bg-white rounded-xl p-12 border border-slate-200 text-center">
                        <div className="text-5xl mb-4">
                            {filter === 'pending' ? '⏳' : filter === 'approved' ? '✅' : filter === 'rejected' ? '↩' : '📋'}
                        </div>
                        <p className="text-slate-700 mt-4">
                            {filter === 'pending' ? 'No results pending your ward certification'
                            : filter === 'approved' ? 'No certified results yet'
                            : filter === 'rejected' ? 'No rejected results'
                            : 'No results found'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {results.map(result => {
                            const statusCfg = getResultStatusMeta(result.certification_status);

                            // Parallel workflow: both pending_ward and legacy pending_party_acceptance are actionable
                            const isPending = result.certification_status === RESULT_STATUS.PENDING_WARD
                                || result.certification_status === RESULT_STATUS.PENDING_PARTY_ACCEPTANCE;

                            return (
                                <div
                                    key={result.id}
                                    className={`bg-white rounded-xl border transition-all ${
                                        isPending ? 'border-iec-pink-500/30' : 'border-slate-200'
                                    }`}
                                >
                                    {/* Result Header */}
                                    <div className="p-5 flex flex-wrap gap-4 justify-between items-start">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-3 mb-1 flex-wrap">
                                                <h3 className="text-lg font-bold text-iec-navy">{result.polling_station}</h3>
                                                <span className="text-xs font-mono text-slate-500">{result.polling_station_code}</span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${statusCfg.badgeClass}`}>
                                                    {statusCfg.label}
                                                </span>
                                                {result.rejection_count > 0 && (
                                                    <span className="px-2 py-0.5 rounded-full text-xs bg-orange-500/20 text-orange-300">
                                                        Rejected {result.rejection_count}×
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-slate-500 text-sm">
                                                Submitted by <strong className="text-slate-600">{result.officer}</strong> · {result.submitted_at}
                                            </p>
                                        </div>
                                        {/* Party responses shown as informational only */}
                                        <div className="text-right text-sm flex-shrink-0">
                                            <div className="text-slate-400 text-xs">Party Responses</div>
                                            <div className={`font-semibold text-sm ${
                                                result.party_total === 0 ? 'text-slate-400' :
                                                result.party_accepted === result.party_total ? 'text-iec-pink-600' : 'text-slate-500'
                                            }`}>
                                                {result.party_total === 0 ? 'N/A' : `${result.party_accepted}/${result.party_total} Responded`}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Vote Summary */}
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 px-5 pb-4">
                                        {[
                                            { label: 'Registered', value: result.total_registered_voters?.toLocaleString(), color: 'text-iec-navy' },
                                            { label: 'Votes Cast', value: result.total_votes_cast?.toLocaleString(),         color: 'text-iec-navy' },
                                            { label: 'Valid',      value: result.valid_votes?.toLocaleString(),              color: 'text-iec-pink-600' },
                                            { label: 'Turnout',    value: `${result.turnout}%`,                              color: 'text-iec-navy' },
                                        ].map(s => (
                                            <div key={s.label} className="bg-white p-3 rounded-lg">
                                                <p className="text-xs text-slate-500 mb-0.5">{s.label}</p>
                                                <p className={`font-bold ${s.color}`}>{s.value}</p>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Candidate votes */}
                                    {result.candidate_votes?.length > 0 && (
                                        <div className="px-5 pb-4">
                                            <div className="text-xs text-slate-500 mb-2 uppercase tracking-wide">Candidate Results</div>
                                            <div className="space-y-2">
                                                {result.candidate_votes.map((cv, idx) => {
                                                    const pct = result.valid_votes > 0 ? ((cv.votes / result.valid_votes) * 100).toFixed(1) : 0;
                                                    return (
                                                        <div key={idx} className="flex items-center gap-3">
                                                            <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: cv.party_color }} />
                                                            <span className="text-slate-600 text-sm w-36 truncate">{cv.candidate}</span>
                                                            <span className="text-xs text-slate-500 w-10">{cv.party}</span>
                                                            <div className="flex-1 bg-white rounded-full h-1.5">
                                                                <div className="h-1.5 rounded-full" style={{ width: `${pct}%`, backgroundColor: cv.party_color }} />
                                                            </div>
                                                            <span className="text-iec-navy text-sm font-semibold w-16 text-right">{cv.votes?.toLocaleString()}</span>
                                                            <span className="text-slate-500 text-xs w-10 text-right">{pct}%</span>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    {/* Party Responses — informational context only */}
                                    {result.party_acceptances?.length > 0 && (
                                        <div className="px-5 pb-4">
                                            <div className="text-xs text-slate-400 mb-2 uppercase tracking-wide">
                                                Party Responses <span className="normal-case font-normal">(informational — does not affect your approval)</span>
                                            </div>
                                            <div className="space-y-2">
                                                {result.party_acceptances.map((pa, idx) => {
                                                    const cfg = PARTY_STATUS_CONFIG[pa.status] || PARTY_STATUS_CONFIG.pending;
                                                    return (
                                                        <div key={idx}>
                                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${cfg.color}`}>
                                                                {cfg.icon} {pa.abbr} — {cfg.label}
                                                            </span>
                                                            {pa.comments && (
                                                                <p className="text-xs text-slate-500 italic mt-1 ml-3 pl-2 border-l-2 border-slate-200">
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
                                               className="inline-flex items-center gap-2 text-sm text-iec-pink-600 hover:text-iec-pink-600">
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

                                    {/* Ward cert comments (shown in approved tab) */}
                                    {result.ward_comments && (
                                        <div className="mx-5 mb-4 p-3 bg-iec-pink-500/10 border border-teal-500/30 rounded-lg">
                                            <div className="text-xs text-iec-pink-600 mb-1 font-semibold">Ward Certification Note</div>
                                            <div className="text-teal-200 text-sm">{result.ward_comments}</div>
                                        </div>
                                    )}

                                    {/* Action Buttons — available immediately in parallel workflow */}
                                    {isPending && (
                                        <div className="px-5 pb-5 flex flex-wrap gap-3 border-t border-slate-200 pt-4 mt-2">
                                            <button onClick={() => openAction(result, 'approve')} className="flex-1 min-w-[140px] px-4 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg transition-colors">
                                                ✓ Certify
                                            </button>
                                            <button onClick={() => openAction(result, 'approve_reservation')} className="flex-1 min-w-[160px] px-4 py-3 bg-iec-pink-600/70 hover:bg-iec-pink-600 text-white font-bold rounded-lg transition-colors">
                                                ⚠ Certify with Reservation
                                            </button>
                                            <button onClick={() => openAction(result, 'reject')} className="flex-1 min-w-[140px] px-4 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-lg transition-colors">
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
                        className="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl flex flex-col"
                        style={{ maxHeight: 'min(92vh, 580px)' }}
                    >
                        {/* Modal Header */}
                        <div className="px-6 pt-6 pb-4 flex-shrink-0 border-b border-slate-200">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-lg font-bold text-iec-navy">{ACTION_CONFIG[action].title}</h2>
                                    <p className="text-slate-600 text-xs mt-0.5">
                                        {selectedResult.polling_station} · {selectedResult.polling_station_code}
                                    </p>
                                </div>
                                <button
                                    onClick={closePanel}
                                    disabled={processing}
                                    className="text-slate-600 hover:text-iec-navy text-2xl leading-none flex-shrink-0 w-8 h-8 flex items-center justify-center"
                                >
                                    ×
                                </button>
                            </div>
                        </div>

                        {/* Scrollable Content */}
                        <div className="px-6 py-5 overflow-y-auto flex-1 space-y-4">
                            <div className="p-3 bg-white rounded-xl text-sm text-slate-700">
                                {ACTION_CONFIG[action].description}
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                {[
                                    { label: 'Votes Cast',  value: selectedResult.total_votes_cast?.toLocaleString() },
                                    { label: 'Valid Votes', value: selectedResult.valid_votes?.toLocaleString() },
                                    { label: 'Turnout',     value: `${selectedResult.turnout}%` },
                                ].map(s => (
                                    <div key={s.label} className="bg-white p-3 rounded-xl text-center">
                                        <div className="text-xs text-slate-600 mb-0.5">{s.label}</div>
                                        <div className="text-iec-navy font-bold">{s.value}</div>
                                    </div>
                                ))}
                            </div>

                            {flash && (
                                <div className={`p-3 rounded-lg text-sm ${flash.type === 'error' ? 'bg-red-500/20 text-red-300' : 'bg-iec-pink-500/20 text-iec-pink-600'}`}>
                                    {flash.text}
                                </div>
                            )}

                            <div className="bg-white border border-slate-200 rounded-xl p-4">
                                <label className="block text-gray-600 font-semibold mb-2 text-sm">
                                    {ACTION_CONFIG[action].commentLabel}
                                    {ACTION_CONFIG[action].commentReq && <span className="text-red-500 ml-1">*</span>}
                                </label>
                                <textarea
                                    value={comment}
                                    onChange={(e) => setComment(e.target.value)}
                                    rows={4}
                                    placeholder={ACTION_CONFIG[action].placeholder}
                                    className="w-full px-4 py-3 bg-white border-2 border-slate-200 focus:border-iec-pink-500 rounded-xl text-iec-navy resize-none focus:outline-none transition-colors text-sm"
                                />
                            </div>
                        </div>

                        {/* Sticky Footer */}
                        <div className="px-6 py-4 border-t border-slate-200 flex-shrink-0 flex gap-3">
                            <button
                                onClick={submitAction}
                                disabled={processing}
                                className={`flex-1 py-3 rounded-xl font-bold text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors ${ACTION_CONFIG[action].confirmColor}`}
                            >
                                {processing ? 'Processing…' : ACTION_CONFIG[action].confirmBtn}
                            </button>
                            <button
                                onClick={closePanel}
                                disabled={processing}
                                className="px-5 py-3 bg-white hover:bg-slate-100 text-iec-navy rounded-xl font-semibold transition-colors"
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
