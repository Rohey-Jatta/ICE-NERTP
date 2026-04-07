import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import RichTextEditor from '@/Components/RichTextEditor';

const STATUS_LABELS = {
    submitted:                { label: 'Submitted',               color: 'bg-gray-500/20 text-gray-300' },
    pending_ward:             { label: 'Pending Ward',             color: 'bg-amber-500/20 text-amber-300' },
    ward_certified:           { label: 'Ward Certified',           color: 'bg-teal-500/20 text-teal-300' },
    pending_constituency:     { label: 'At Constituency',          color: 'bg-blue-500/20 text-blue-300' },
    constituency_certified:   { label: 'Constituency Certified',   color: 'bg-cyan-500/20 text-cyan-300' },
    pending_admin_area:       { label: 'Pending Admin-Area',       color: 'bg-orange-500/20 text-orange-300' },
    admin_area_certified:     { label: 'Admin-Area Certified',     color: 'bg-violet-500/20 text-violet-300' },
    pending_national:         { label: 'At Chairman',              color: 'bg-pink-500/20 text-pink-300' },
    nationally_certified:     { label: 'Nationally Certified',     color: 'bg-green-500/20 text-green-300' },
};

const PARTY_STATUS_CONFIG = {
    accepted:                  { label: 'Accepted',             color: 'bg-green-500/20 text-green-300',  icon: '✓' },
    accepted_with_reservation: { label: 'Accepted (Reserved)',  color: 'bg-yellow-500/20 text-yellow-300', icon: '⚠' },
    rejected:                  { label: 'Rejected',             color: 'bg-red-500/20 text-red-300',      icon: '✗' },
    pending:                   { label: 'Pending',              color: 'bg-gray-500/20 text-gray-300',     icon: '…' },
};

export default function AdminAreaApprovalQueue({ auth, adminArea, results = [], filter = 'pending', counts = {} }) {
    const [selectedResult, setSelectedResult] = useState(null);
    const [action, setAction]                 = useState(null);
    const [comment, setComment]               = useState('');
    const [processing, setProcessing]         = useState(false);
    const [flash, setFlash]                   = useState(null);

    const filterTabs = [
        { key: 'pending',  label: 'Pending',   count: counts.pending  || 0, color: 'amber'  },
        { key: 'approved', label: 'Certified',  count: counts.approved || 0, color: 'teal'   },
        { key: 'rejected', label: 'Rejected',   count: counts.rejected || 0, color: 'red'    },
        { key: 'all',      label: 'All',        count: counts.all      || 0, color: 'slate'  },
    ];

    const tabColors = {
        amber: { active: 'bg-amber-500 text-white',  dot: 'bg-amber-400' },
        teal:  { active: 'bg-teal-500 text-white',   dot: 'bg-teal-400'  },
        red:   { active: 'bg-red-500 text-white',    dot: 'bg-red-400'   },
        slate: { active: 'bg-slate-500 text-white',  dot: 'bg-slate-400' },
    };

    const actionConfig = {
        approve: {
            title:        'Certify at Admin-Area Level',
            description:  'This result will be certified at the administrative area level and automatically promoted to the IEC Chairman queue for final national certification.',
            confirmBtn:   'Certify & Send to Chairman',
            confirmColor: 'bg-teal-600 hover:bg-teal-700',
            commentReq:   false,
            commentLabel: 'Additional Comments (optional)',
        },
        approve_reservation: {
            title:        'Certify with Reservation',
            description:  'Result will be certified at admin-area level with your reservation flagged. It will be promoted to the IEC Chairman queue.',
            confirmBtn:   'Certify with Reservation',
            confirmColor: 'bg-amber-600 hover:bg-amber-700',
            commentReq:   true,
            commentLabel: 'Reservation Note (required)',
        },
        reject: {
            title:        'Reject & Return to Constituency',
            description:  'This result will be returned to the constituency approver with your rejection reason. The constituency approver will be required to re-certify.',
            confirmBtn:   'Confirm Rejection',
            confirmColor: 'bg-red-600 hover:bg-red-700',
            commentReq:   true,
            commentLabel: 'Rejection Reason (required)',
        },
    };

    const handleFilterChange = (key) => {
        router.get('/admin-area/approval-queue', { filter: key }, { preserveState: false });
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
    };

    const submitAction = async () => {
        if (!selectedResult || !action) return;

        if ((action === 'approve_reservation' || action === 'reject') && !comment.replace(/<[^>]*>/g, '').trim()) {
            setFlash({ type: 'error', text: 'A comment is required for this action.' });
            return;
        }

        setProcessing(true);
        setFlash(null);

        const endpoints = {
            approve:             `/admin-area/approve/${selectedResult.id}`,
            approve_reservation: `/admin-area/approve-with-reservation/${selectedResult.id}`,
            reject:              `/admin-area/reject/${selectedResult.id}`,
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
                    <Link href="/admin-area/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Admin-Area Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Admin-Area Approval Queue</h1>
                    {adminArea?.name && <p className="text-teal-300 mt-1">{adminArea.name}</p>}
                    <p className="text-gray-400 text-sm mt-1">
                        Review constituency-certified results and certify at administrative area level
                    </p>
                </div>

                {/* Flash */}
                {flash && (
                    <div className={`mb-4 p-4 rounded-xl border ${
                        flash.type === 'error'
                            ? 'bg-red-500/20 border-red-500/50 text-red-300'
                            : 'bg-teal-500/20 border-teal-500/50 text-teal-300'
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

                {/* Results */}
                {results.length === 0 ? (
                    <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                        <p className="text-gray-300 text-lg">
                            {filter === 'pending' ? 'No results pending admin-area certification' :
                             filter === 'approved' ? 'No admin-area certified results yet' :
                             filter === 'rejected' ? 'No rejected results' : 'No results found'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {results.map(result => {
                            const statusCfg = STATUS_LABELS[result.certification_status] || { label: result.certification_status, color: 'bg-gray-500/20 text-gray-300' };
                            const isPending = result.certification_status === 'pending_admin_area';

                            return (
                                <div
                                    key={result.id}
                                    className={`bg-slate-800/40 rounded-xl border transition-all ${
                                        isPending ? 'border-orange-500/30' : 'border-slate-700/50'
                                    }`}
                                >
                                    {/* Card Header */}
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
                                            <div className="flex flex-wrap gap-3 text-sm text-gray-400 mt-1">
                                                <span>📍 <strong className="text-gray-300">{result.constituency}</strong></span>
                                                <span>⬡ <strong className="text-gray-300">{result.ward}</strong></span>
                                                <span>Submitted {result.submitted_at}</span>
                                            </div>
                                        </div>

                                        <div className="text-right text-sm">
                                            <div className="text-gray-400">Party Status</div>
                                            <div className={`font-semibold ${
                                                result.party_total === 0 ? 'text-gray-500' :
                                                result.party_accepted === result.party_total ? 'text-green-300' : 'text-amber-300'
                                            }`}>
                                                {result.party_total === 0 ? 'N/A' : `${result.party_accepted}/${result.party_total} Accepted`}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Vote Summary */}
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 px-5 pb-4">
                                        <div className="bg-slate-900/50 p-3 rounded-lg">
                                            <div className="text-xs text-gray-400 mb-1">Registered</div>
                                            <div className="text-white font-bold">{result.total_registered_voters?.toLocaleString()}</div>
                                        </div>
                                        <div className="bg-slate-900/50 p-3 rounded-lg">
                                            <div className="text-xs text-gray-400 mb-1">Votes Cast</div>
                                            <div className="text-white font-bold">{result.total_votes_cast?.toLocaleString()}</div>
                                        </div>
                                        <div className="bg-slate-900/50 p-3 rounded-lg">
                                            <div className="text-xs text-gray-400 mb-1">Valid</div>
                                            <div className="text-teal-300 font-bold">{result.valid_votes?.toLocaleString()}</div>
                                        </div>
                                        <div className="bg-slate-900/50 p-3 rounded-lg">
                                            <div className="text-xs text-gray-400 mb-1">Turnout</div>
                                            <div className="text-white font-bold">{result.turnout}%</div>
                                        </div>
                                    </div>

                                    {/* Candidate bars removed for instant navigation/offline UX */}

                                    {/* Party acceptances */}
                                    {result.party_acceptances?.length > 0 && (
                                        <div className="px-5 pb-4">
                                            <div className="text-xs text-gray-500 mb-2 uppercase tracking-wide">Party Representative Status</div>
                                            <div className="flex flex-wrap gap-2">
                                                {result.party_acceptances.map((pa, idx) => {
                                                    const cfg = PARTY_STATUS_CONFIG[pa.status] || PARTY_STATUS_CONFIG.pending;
                                                    return (
                                                        <span key={idx} className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold ${cfg.color}`}>
                                                            {cfg.icon} {pa.abbr} — {cfg.label}
                                                            {pa.comments && <span className="ml-1 opacity-70" title={pa.comments}>💬</span>}
                                                        </span>
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

                                    {/* Prior rejection */}
                                    {result.last_rejection_reason && (
                                        <div className="mx-5 mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg">
                                            <div className="text-xs text-red-400 mb-1 font-semibold">Previous Rejection Reason</div>
                                            <div className="text-red-300 text-sm" dangerouslySetInnerHTML={{ __html: result.last_rejection_reason }} />
                                        </div>
                                    )}

                                    {/* Area comments if certified */}
                                    {result.area_comments && (
                                        <div className="mx-5 mb-4 p-3 bg-teal-500/10 border border-teal-500/30 rounded-lg">
                                            <div className="text-xs text-teal-400 mb-1 font-semibold">Admin-Area Certification Note</div>
                                            <div className="text-teal-200 text-sm" dangerouslySetInnerHTML={{ __html: result.area_comments }} />
                                        </div>
                                    )}

                                    {/* Action Buttons — pending only */}
                                    {isPending && (
                                        <div className="px-5 pb-5 flex flex-wrap gap-3 border-t border-slate-700/50 pt-4 mt-2">
                                            <button
                                                onClick={() => openAction(result, 'approve')}
                                                className="flex-1 min-w-[140px] px-4 py-3 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-lg transition-colors"
                                            >
                                                ✓ Certify
                                            </button>
                                            <button
                                                onClick={() => openAction(result, 'approve_reservation')}
                                                className="flex-1 min-w-[140px] px-4 py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-lg transition-colors"
                                            >
                                                ⚠ Certify with Reservation
                                            </button>
                                            <button
                                                onClick={() => openAction(result, 'reject')}
                                                className="flex-1 min-w-[140px] px-4 py-3 bg-red-700 hover:bg-red-600 text-white font-bold rounded-lg transition-colors"
                                            >
                                                ✗ Reject & Return
                                            </button>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Action Modal */}
            {selectedResult && action && (
                <div className="fixed inset-0 z-50 bg-black/70 flex items-end sm:items-center justify-center p-4">
                    <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
                        <div className="p-6">
                            <div className="flex items-start justify-between mb-5">
                                <div>
                                    <h2 className="text-xl font-bold text-white">{actionConfig[action].title}</h2>
                                    <p className="text-sm text-gray-400 mt-1">
                                        {selectedResult.polling_station} — {selectedResult.constituency}
                                    </p>
                                </div>
                                <button onClick={closePanel} disabled={processing} className="text-gray-500 hover:text-white text-2xl leading-none">×</button>
                            </div>

                            <div className="p-4 bg-slate-800/60 rounded-lg mb-5 text-sm text-gray-300">
                                {actionConfig[action].description}
                            </div>

                            <div className="grid grid-cols-3 gap-3 mb-5">
                                <div className="bg-slate-800/50 p-3 rounded-lg text-center">
                                    <div className="text-xs text-gray-400">Votes Cast</div>
                                    <div className="text-white font-bold">{selectedResult.total_votes_cast?.toLocaleString()}</div>
                                </div>
                                <div className="bg-slate-800/50 p-3 rounded-lg text-center">
                                    <div className="text-xs text-gray-400">Valid Votes</div>
                                    <div className="text-teal-300 font-bold">{selectedResult.valid_votes?.toLocaleString()}</div>
                                </div>
                                <div className="bg-slate-800/50 p-3 rounded-lg text-center">
                                    <div className="text-xs text-gray-400">Turnout</div>
                                    <div className="text-white font-bold">{selectedResult.turnout}%</div>
                                </div>
                            </div>

                            {flash && (
                                <div className={`mb-4 p-3 rounded-lg text-sm ${flash.type === 'error' ? 'bg-red-500/20 text-red-300' : 'bg-teal-500/20 text-teal-300'}`}>
                                    {flash.text}
                                </div>
                            )}

                            <div className="mb-5">
                                <label className="block text-gray-300 font-semibold mb-2">
                                    {actionConfig[action].commentLabel}
                                    {actionConfig[action].commentReq && <span className="text-red-400 ml-1">*</span>}
                                </label>
                                <RichTextEditor
                                    value={comment}
                                    onChange={setComment}
                                    placeholder={
                                        action === 'reject'
                                            ? 'Clearly explain why this result is being rejected and what the constituency approver needs to address...'
                                            : action === 'approve_reservation'
                                            ? 'Describe your reservation or concern while certifying this result...'
                                            : 'Add any observations or notes about this result...'
                                    }
                                    minHeight="180px"
                                />
                            </div>

                            <div className="flex gap-3">
                                <button
                                    onClick={submitAction}
                                    disabled={processing || (actionConfig[action].commentReq && !comment.replace(/<[^>]*>/g, '').trim())}
                                    className={`flex-1 py-3 px-6 rounded-lg font-bold text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed ${actionConfig[action].confirmColor}`}
                                >
                                    {processing ? 'Processing…' : actionConfig[action].confirmBtn}
                                </button>
                                <button
                                    onClick={closePanel}
                                    disabled={processing}
                                    className="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-semibold"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}