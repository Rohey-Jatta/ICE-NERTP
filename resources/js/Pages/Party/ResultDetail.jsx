import AppLayout from '@/Layouts/AppLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const CERT_STATUS_LABELS = {
    pending_party_acceptance: 'Awaiting Party Acceptance',
    pending_ward:             'At Ward Review',
    ward_certified:           'Ward Certified',
    pending_constituency:     'At Constituency',
    constituency_certified:   'Constituency Certified',
    pending_admin_area:       'At Admin Area',
    admin_area_certified:     'Admin Area Certified',
    pending_national:         'At National',
    nationally_certified:     'Nationally Certified',
};

const DECISION_CONFIG = {
    accepted: {
        label:       'Accept Result',
        description: 'You confirm the vote counts are correct as submitted.',
        btnClass:    'bg-teal-600 hover:bg-teal-500',
        icon:        '✓',
        commentReq:  false,
        commentLabel:'Additional Comments (optional)',
    },
    accepted_with_reservation: {
        label:       'Accept with Reservation',
        description: 'You accept the result but flag concerns for the record.',
        btnClass:    'bg-yellow-600 hover:bg-yellow-500',
        icon:        '⚠',
        commentReq:  true,
        commentLabel:'State your reservation (required)',
    },
    rejected: {
        label:       'Dispute / Reject Result',
        description: 'You dispute this result. Your objection will be logged and visible to IEC.',
        btnClass:    'bg-red-700 hover:bg-red-600',
        icon:        '✗',
        commentReq:  true,
        commentLabel:'Reason for disputing this result (required)',
    },
};

export default function ResultDetail({ auth, party, result, myAcceptance }) {
    const [selectedDecision, setSelectedDecision] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [photoOpen, setPhotoOpen] = useState(false);

    const { data, setData, post, errors } = useForm({
        status:   '',
        comments: '',
    });

    const partyColor = party?.color?.split(',')[0] || '#6b7280';
    const totalVotes = result?.valid_votes || 0;

    const handleDecision = (decisionKey) => {
        setSelectedDecision(decisionKey);
        setData('status', decisionKey);
        setData('comments', '');
    };

    const submitDecision = (e) => {
        e.preventDefault();
        if (!selectedDecision) return;
        setProcessing(true);
        post(`/party/result/${result.id}/decide`, {
            onFinish: () => setProcessing(false),
        });
    };

    const alreadyDecided = myAcceptance?.is_final;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-5xl">

                {/* Back nav */}
                <div className="mb-6">
                    <Link href="/party/pending-acceptance"
                          className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        ← Back to Pending Results
                    </Link>

                    <div className="flex flex-wrap gap-4 items-start justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-white">{result.polling_station_name}</h1>
                            <div className="flex items-center gap-3 mt-1 flex-wrap">
                                <span className="text-xs font-mono text-gray-500 bg-slate-900/60 px-2 py-0.5 rounded">
                                    {result.polling_station_code}
                                </span>
                                <span className="text-gray-400 text-sm">
                                    {CERT_STATUS_LABELS[result.certification_status] || result.certification_status}
                                </span>
                                <span className="text-gray-500 text-xs">Submitted: {result.submitted_at}</span>
                            </div>
                        </div>

                        {/* Already decided badge */}
                        {alreadyDecided && (
                            <div className={`px-4 py-2 rounded-xl font-semibold text-sm border ${
                                myAcceptance.status === 'accepted'
                                    ? 'bg-teal-500/20 text-teal-300 border-teal-500/40'
                                    : myAcceptance.status === 'rejected'
                                    ? 'bg-red-500/20 text-red-300 border-red-500/40'
                                    : 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40'
                            }`}>
                                {myAcceptance.status === 'accepted' && '✓ You Accepted'}
                                {myAcceptance.status === 'rejected' && '✗ You Disputed'}
                                {myAcceptance.status === 'accepted_with_reservation' && '⚠ Accepted with Reservation'}
                                <div className="text-xs opacity-70 mt-0.5">on {myAcceptance.decided_at}</div>
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* ── Left: Result Data ───────────────────────────────── */}
                    <div className="lg:col-span-2 space-y-5">

                        {/* Turnout summary */}
                        <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 p-5">
                            <h2 className="text-white font-bold text-lg mb-4">Vote Totals</h2>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                {[
                                    { label: 'Registered Voters', value: result.total_registered_voters?.toLocaleString(), color: 'text-white' },
                                    { label: 'Total Votes Cast',  value: result.total_votes_cast?.toLocaleString(),         color: 'text-white' },
                                    { label: 'Valid Votes',       value: result.valid_votes?.toLocaleString(),              color: 'text-teal-300' },
                                    { label: 'Rejected Ballots',  value: result.rejected_votes?.toLocaleString(),           color: 'text-red-300' },
                                ].map((item) => (
                                    <div key={item.label} className="bg-slate-900/50 rounded-lg p-3 text-center">
                                        <div className={`text-xl font-bold mb-1 ${item.color}`}>{item.value}</div>
                                        <div className="text-gray-400 text-xs">{item.label}</div>
                                    </div>
                                ))}
                            </div>

                            {/* Turnout bar */}
                            <div className="mt-4">
                                <div className="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Voter Turnout</span>
                                    <span className="font-bold text-white">{result.turnout_percentage}%</span>
                                </div>
                                <div className="w-full bg-slate-700 rounded-full h-3">
                                    <div
                                        className="bg-gradient-to-r from-teal-600 to-teal-400 h-3 rounded-full"
                                        style={{ width: `${result.turnout_percentage}%` }}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Candidate breakdown */}
                        {result.candidate_votes?.length > 0 && (
                            <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 p-5">
                                <h2 className="text-white font-bold text-lg mb-4">Results by Candidate</h2>
                                <div className="space-y-3">
                                    {[...result.candidate_votes]
                                        .sort((a, b) => b.votes - a.votes)
                                        .map((cv, idx) => {
                                            const pct = totalVotes > 0
                                                ? ((cv.votes / totalVotes) * 100).toFixed(1)
                                                : 0;
                                            const isLeading = idx === 0;
                                            return (
                                                <div key={cv.candidate_id}
                                                     className={`p-4 rounded-lg border ${
                                                         isLeading
                                                             ? 'bg-teal-900/20 border-teal-500/30'
                                                             : 'bg-slate-900/40 border-slate-700/30'
                                                     }`}>
                                                    <div className="flex justify-between items-start mb-2">
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-3 h-3 rounded-full flex-shrink-0"
                                                                 style={{ backgroundColor: cv.party_color }} />
                                                            <div>
                                                                <div className="text-white font-semibold text-sm">
                                                                    {cv.candidate_name}
                                                                    {isLeading && (
                                                                        <span className="ml-2 text-xs text-teal-400">🏆 Leading</span>
                                                                    )}
                                                                </div>
                                                                <div className="text-gray-400 text-xs">{cv.party_name}</div>
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="text-white font-bold">{cv.votes?.toLocaleString()}</div>
                                                            <div className="text-gray-400 text-xs">{pct}%</div>
                                                        </div>
                                                    </div>
                                                    <div className="w-full bg-slate-700 rounded-full h-2">
                                                        <div
                                                            className="h-2 rounded-full transition-all"
                                                            style={{ width: `${pct}%`, backgroundColor: cv.party_color }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                </div>
                            </div>
                        )}

                        {/* Result sheet photo */}
                        <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 p-5">
                            <h2 className="text-white font-bold text-lg mb-3">Result Sheet Photo</h2>
                            {result.photo_url ? (
                                <div>
                                    <button
                                        onClick={() => setPhotoOpen(true)}
                                        className="relative w-full rounded-lg overflow-hidden border border-slate-600/50 hover:border-teal-500/50 transition-colors group"
                                    >
                                        <img
                                            src={result.photo_url}
                                            alt="Result sheet"
                                            className="w-full max-h-64 object-contain bg-white group-hover:opacity-90 transition-opacity"
                                        />
                                        <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/30">
                                            <span className="text-white text-sm font-semibold bg-black/60 px-4 py-2 rounded-lg">
                                                🔍 Click to enlarge
                                            </span>
                                        </div>
                                    </button>
                                    
                                    <a href={result.photo_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300 mt-2"
                                    >
                                        📄 Open full image in new tab
                                    </a>
                                </div>
                            ) : (
                                <div className="p-6 bg-slate-900/50 rounded-lg border border-slate-700/30 text-center">
                                    <p className="text-gray-500 text-sm">No result sheet photo attached.</p>
                                </div>
                            )}
                        </div>

                        {/* Other parties' decisions */}
                        {result.other_party_acceptances?.length > 0 && (
                            <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 p-5">
                                <h2 className="text-white font-bold text-lg mb-3">Other Parties' Decisions</h2>
                                <div className="space-y-2">
                                    {result.other_party_acceptances.map((pa, idx) => (
                                        <div key={idx} className="flex items-start gap-3 p-3 bg-slate-900/40 rounded-lg">
                                            <span className={`px-2 py-0.5 rounded text-xs font-semibold flex-shrink-0 ${
                                                pa.status === 'accepted' ? 'bg-teal-500/20 text-teal-300' :
                                                pa.status === 'rejected' ? 'bg-red-500/20 text-red-300' :
                                                'bg-yellow-500/20 text-yellow-300'
                                            }`}>
                                                {pa.abbr}
                                            </span>
                                            <div>
                                                <div className="text-gray-300 text-sm font-medium">
                                                    {pa.status === 'accepted' ? '✓ Accepted' :
                                                     pa.status === 'rejected' ? '✗ Disputed' : '⚠ Accepted with Reservation'}
                                                    {' — '}<span className="text-gray-400 font-normal">{pa.party_name}</span>
                                                </div>
                                                {pa.comments && (
                                                    <div className="text-gray-500 text-xs mt-0.5 italic">"{pa.comments}"</div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ── Right: Decision Panel ───────────────────────────── */}
                    <div className="lg:col-span-1">
                        <div className="sticky top-24">
                            {alreadyDecided ? (
                                /* Already decided — show summary */
                                <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 p-5">
                                    <h2 className="text-white font-bold text-lg mb-4">Your Decision</h2>
                                    <div className={`p-4 rounded-xl border mb-4 ${
                                        myAcceptance.status === 'accepted'
                                            ? 'bg-teal-500/10 border-teal-500/40'
                                            : myAcceptance.status === 'rejected'
                                            ? 'bg-red-500/10 border-red-500/40'
                                            : 'bg-yellow-500/10 border-yellow-500/40'
                                    }`}>
                                        <div className={`text-lg font-bold mb-1 ${
                                            myAcceptance.status === 'accepted' ? 'text-teal-300' :
                                            myAcceptance.status === 'rejected' ? 'text-red-300' : 'text-yellow-300'
                                        }`}>
                                            {myAcceptance.status === 'accepted' && '✓ Accepted'}
                                            {myAcceptance.status === 'rejected' && '✗ Disputed'}
                                            {myAcceptance.status === 'accepted_with_reservation' && '⚠ Accepted with Reservation'}
                                        </div>
                                        <div className="text-gray-400 text-xs">Recorded on {myAcceptance.decided_at}</div>
                                        {myAcceptance.comments && (
                                            <div className="mt-3 p-3 bg-slate-900/50 rounded-lg text-gray-300 text-sm italic">
                                                "{myAcceptance.comments}"
                                            </div>
                                        )}
                                    </div>
                                    <p className="text-gray-500 text-xs text-center">
                                        Your decision is final and has been logged in the audit trail.
                                    </p>
                                    <Link href="/party/pending-acceptance"
                                          className="block text-center mt-4 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">
                                        ← Back to Pending Results
                                    </Link>
                                </div>
                            ) : (
                                /* Decision form */
                                <div className="bg-slate-800/40 rounded-xl border border-amber-500/30 p-5">
                                    <h2 className="text-white font-bold text-lg mb-2">Your Decision</h2>
                                    <p className="text-gray-400 text-xs mb-5 leading-relaxed">
                                        Review the vote counts and result sheet photo carefully before making your decision.
                                        Your decision will be logged and visible to IEC officials.
                                    </p>

                                    {/* Decision buttons */}
                                    <div className="space-y-3 mb-5">
                                        {Object.entries(DECISION_CONFIG).map(([key, cfg]) => (
                                            <button
                                                key={key}
                                                onClick={() => handleDecision(key)}
                                                className={`w-full p-3 rounded-lg border-2 text-left transition-all ${
                                                    selectedDecision === key
                                                        ? key === 'accepted' ? 'border-teal-500 bg-teal-500/20'
                                                        : key === 'rejected' ? 'border-red-500 bg-red-500/20'
                                                        : 'border-yellow-500 bg-yellow-500/20'
                                                        : 'border-slate-600 hover:border-slate-500 bg-slate-900/30'
                                                }`}
                                            >
                                                <div className={`font-bold text-sm ${
                                                    selectedDecision === key
                                                        ? key === 'accepted' ? 'text-teal-300'
                                                        : key === 'rejected' ? 'text-red-300'
                                                        : 'text-yellow-300'
                                                        : 'text-white'
                                                }`}>
                                                    {cfg.icon} {cfg.label}
                                                </div>
                                                <div className="text-gray-500 text-xs mt-0.5">{cfg.description}</div>
                                            </button>
                                        ))}
                                    </div>

                                    {/* Comment field */}
                                    {selectedDecision && (
                                        <form onSubmit={submitDecision}>
                                            <div className="mb-4">
                                                <label className="block text-gray-300 text-sm font-semibold mb-2">
                                                    {DECISION_CONFIG[selectedDecision].commentLabel}
                                                    {DECISION_CONFIG[selectedDecision].commentReq && (
                                                        <span className="text-red-400 ml-1">*</span>
                                                    )}
                                                </label>
                                                <textarea
                                                    value={data.comments}
                                                    onChange={(e) => setData('comments', e.target.value)}
                                                    rows={4}
                                                    required={DECISION_CONFIG[selectedDecision].commentReq}
                                                    className="w-full px-3 py-2 bg-slate-900/60 border border-slate-600 rounded-lg text-white text-sm resize-none focus:outline-none focus:border-teal-500"
                                                    placeholder={
                                                        selectedDecision === 'rejected'
                                                            ? 'Clearly state why you are disputing this result...'
                                                            : selectedDecision === 'accepted_with_reservation'
                                                            ? 'State your concerns or reservations...'
                                                            : 'Any additional notes (optional)...'
                                                    }
                                                />
                                                {errors.comments && (
                                                    <p className="text-red-400 text-xs mt-1">{errors.comments}</p>
                                                )}
                                            </div>

                                            <button
                                                type="submit"
                                                disabled={processing || (
                                                    DECISION_CONFIG[selectedDecision].commentReq && !data.comments.trim()
                                                )}
                                                className={`w-full py-3 rounded-lg font-bold text-white text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed ${
                                                    DECISION_CONFIG[selectedDecision].btnClass
                                                }`}
                                            >
                                                {processing ? 'Submitting…' : `Confirm — ${DECISION_CONFIG[selectedDecision].label}`}
                                            </button>
                                        </form>
                                    )}

                                    {!selectedDecision && (
                                        <p className="text-gray-600 text-xs text-center">
                                            Select a decision above to continue.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

          
            {photoOpen && result.photo_url && (
                <div
                    className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
                    onClick={() => setPhotoOpen(false)}
                >
                    <div className="relative max-w-5xl w-full" onClick={(e) => e.stopPropagation()}>
                        <button
                            onClick={() => setPhotoOpen(false)}
                            className="absolute -top-10 right-0 text-white text-3xl hover:text-gray-300"
                        >
                            ×
                        </button>
                        <img
                            src={result.photo_url}
                            alt="Result sheet"
                            className="w-full rounded-xl shadow-2xl"
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}