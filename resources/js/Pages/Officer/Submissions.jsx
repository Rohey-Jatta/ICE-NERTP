import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    CERTIFIED_RESULT_STATUSES,
    EARLY_PIPELINE_STATUSES,
    RESULT_STATUS,
    getCertificationPipelinePercent,
    getResultStatusMeta,
} from '@/Utils/resultStatus';

function PipelineBar({ status }) {
    const pct        = getCertificationPipelinePercent(status);
    const isReturned = status === RESULT_STATUS.REJECTED;

    return (
        <div>
            <div className="flex justify-between text-xs text-slate-500 mb-1">
                <span>Certification Progress</span>
                <span className="font-semibold">{pct}%</span>
            </div>
            <div className="w-full bg-white rounded-full h-2">
                <div
                    className="h-2 rounded-full transition-all duration-500"
                    style={{
                        width: `${pct}%`,
                        background: status === RESULT_STATUS.NATIONALLY_CERTIFIED
                            ? 'linear-gradient(90deg, #10b981, #14b8a6)'
                            : isReturned
                            ? '#ef4444'
                            : 'linear-gradient(90deg, #3b82f6, #8b5cf6)',
                    }}
                />
            </div>
        </div>
    );
}

export default function Submissions({ auth, submissions = [], station }) {
    const [expandedId, setExpandedId] = useState(null);

    const pendingCount   = submissions.filter(s => EARLY_PIPELINE_STATUSES.includes(s.certification_status)).length;
    const certifiedCount = submissions.filter(s => CERTIFIED_RESULT_STATUSES.includes(s.certification_status)).length;
    const rejectedCount  = submissions.filter(s => s.is_editable).length;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    {/* <Link href="/officer/dashboard"
                          className="text-slate-500 hover:text-iec-navy text-sm inline-flex items-center gap-1 mb-3">
                        ← Officer Dashboard
                    </Link> */}
                    <div className="flex flex-wrap gap-4 items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-iec-navy">My Submissions</h1>
                            {station && (
                                <p className="text-slate-500 mt-0.5 text-sm">
                                    {station.name}
                                    <span className="ml-2 font-mono text-xs text-slate-500 bg-white px-1.5 py-0.5 rounded">{station.code}</span>
                                </p>
                            )}
                        </div>
                        {rejectedCount > 0 && (
                            <Link href="/officer/results/submit"
                                  className="px-5 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-lg text-sm">
                                ↩ Resubmit Rejected Result
                            </Link>
                        )}
                    </div>
                </div>

                {/* Summary */}
                <div className="flex flex-wrap gap-3 mb-6">
                    <div className="px-4 py-2 bg-slate-100 border border-slate-200 rounded-full text-slate-600 text-sm">
                        📋 {submissions.length} total submission{submissions.length !== 1 ? 's' : ''}
                    </div>
                    {pendingCount > 0 && (
                        <div className="px-4 py-2 bg-pink-500/15 border border-pink-500/30 rounded-full text-pink-500 text-sm">
                           {pendingCount} in pipeline
                        </div>
                    )}
                    {certifiedCount > 0 && (
                        <div className="px-4 py-2 bg-iec-pink-500/15 border border-teal-500/30 rounded-full text-iec-pink-600 text-sm">
                            ✓ {certifiedCount} certified
                        </div>
                    )}
                    {rejectedCount > 0 && (
                        <div className="px-4 py-2 bg-red-500/15 border border-red-500/30 rounded-full text-red-500 text-sm">
                            ✗ {rejectedCount} rejected — action required
                        </div>
                    )}
                </div>

                {submissions.length === 0 ? (
                    <div className="bg-white rounded-xl p-16 border border-slate-200 text-center">
                        <div className="text-5xl mb-4">📋</div>
                        <h2 className="text-xl font-bold text-iec-navy mb-2">No Submissions Yet</h2>
                        <p className="text-slate-500 text-sm mb-6">Submit your station's election results to get started.</p>
                        <Link href="/officer/results/submit"
                              className="inline-block px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
                            Submit Results →
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {submissions.map((submission) => {
                            const cfg       = getResultStatusMeta(submission.certification_status);
                            const isExpanded = expandedId === submission.id;

                            return (
                                <div key={submission.id}
                                     className={`bg-white rounded-xl border transition-all ${
                                         submission.is_editable
                                             ? 'border-red-500/40'
                                             : submission.certification_status === RESULT_STATUS.NATIONALLY_CERTIFIED
                                             ? 'border-green-500/30'
                                             : 'border-slate-200'
                                     }`}>

                                    {/* Main row */}
                                    <div className="p-5">
                                        <div className="flex flex-wrap gap-3 items-start justify-between mb-3">
                                            <div>
                                                <div className="flex items-center gap-3 flex-wrap">
                                                    <h3 className="text-iec-navy font-bold">{submission.polling_station_name}</h3>
                                                    <span className="font-mono text-xs text-slate-500 bg-white px-2 py-0.5 rounded">
                                                        {submission.polling_station_code}
                                                    </span>
                                                    <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border ${cfg.borderedBadgeClass}`}>
                                                        {cfg.icon} {cfg.label}
                                                    </span>
                                                    {submission.rejection_count > 0 && (
                                                        <span className="px-2 py-0.5 rounded-full text-xs bg-Pink-500/20 text-pink-500">
                                                            Rejected {submission.rejection_count}×
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-slate-500 text-xs mt-1">
                                                    Submitted: {submission.submitted_at}
                                                    {submission.version > 1 && ` · v${submission.version}`}
                                                </p>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                {submission.is_editable && (
                                                    <Link href="/officer/results/submit"
                                                          className="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-bold rounded-lg">
                                                        Resubmit
                                                    </Link>
                                                )}
                                                <button
                                                    onClick={() => setExpandedId(isExpanded ? null : submission.id)}
                                                    className="px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-600 text-xs rounded-lg"
                                                >
                                                    {isExpanded ? 'Hide' : 'Details'}
                                                </button>
                                            </div>
                                        </div>

                                        {/* Stats row */}
                                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                                            {[
                                                { label: 'Registered',   value: submission.total_registered_voters?.toLocaleString(), color: 'text-slate-600' },
                                                { label: 'Votes Cast',   value: submission.total_votes_cast?.toLocaleString(),         color: 'text-iec-navy' },
                                                { label: 'Turnout',      value: `${submission.turnout}%`,                             color: 'text-iec-pink-600' },
                                                { label: 'Valid',        value: submission.valid_votes?.toLocaleString(),              color: 'text-iec-pink-600' },
                                            ].map((s) => (
                                                <div key={s.label} className="bg-slate-50 rounded-lg p-2.5 text-center">
                                                    <div className={`font-bold text-sm ${s.color}`}>{s.value}</div>
                                                    <div className="text-slate-500 text-xs">{s.label}</div>
                                                </div>
                                            ))}
                                        </div>

                                        {/* Pipeline bar */}
                                        <PipelineBar status={submission.certification_status} />
                                    </div>

                                    {/* Expanded details */}
                                    {isExpanded && (
                                        <div className="border-t border-slate-200 p-5 space-y-4">

                                            {/* Rejection reason */}
                                            {submission.last_rejection_reason && (
                                                <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-lg">
                                                    <div className="text-xs text-red-600 font-semibold mb-1">Rejection Reason</div>
                                                    <div className="text-red-500 text-sm"
                                                         dangerouslySetInnerHTML={{ __html: submission.last_rejection_reason }} />
                                                </div>
                                            )}

                                            {/* Candidate breakdown */}
                                            {submission.candidate_votes?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-slate-500 uppercase tracking-wide mb-2">Candidate Results</div>
                                                    <div className="space-y-2">
                                                        {[...submission.candidate_votes]
                                                            .sort((a, b) => b.votes - a.votes)
                                                            .map((cv, idx) => {
                                                                const pct = submission.valid_votes > 0
                                                                    ? ((cv.votes / submission.valid_votes) * 100).toFixed(1)
                                                                    : 0;
                                                                return (
                                                                    <div key={idx} className="flex items-center gap-3">
                                                                        <div className="w-2 h-2 rounded-full flex-shrink-0"
                                                                             style={{ backgroundColor: cv.party_color }} />
                                                                        <span className="text-slate-600 text-sm w-32 truncate">{cv.candidate_name}</span>
                                                                        <span className="text-xs text-slate-500 w-8">{cv.party_abbr}</span>
                                                                        <div className="flex-1 bg-white rounded-full h-1.5">
                                                                            <div className="h-1.5 rounded-full"
                                                                                 style={{ width: `${pct}%`, backgroundColor: cv.party_color }} />
                                                                        </div>
                                                                        <span className="text-iec-navy text-sm font-semibold w-16 text-right">
                                                                            {cv.votes?.toLocaleString()}
                                                                        </span>
                                                                        <span className="text-slate-500 text-xs w-10 text-right">{pct}%</span>
                                                                    </div>
                                                                );
                                                            })}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Photo */}
                                            {submission.photo_url && (
                                                <div>
                                                    <div className="text-xs text-slate-500 uppercase tracking-wide mb-2">Result Sheet Photo</div>
                                                    <a href={submission.photo_url} target="_blank" rel="noopener noreferrer"
                                                       className="inline-flex items-center gap-2 text-iec-pink-600 hover:text-iec-pink-600 text-sm">
                                                        📄 View Result Sheet Photo
                                                    </a>
                                                </div>
                                            )}
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
