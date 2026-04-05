import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_CONFIG = {
    submitted:                 { label: 'Submitted',             color: 'bg-sky-500/20 text-sky-300 border-sky-500/30',        icon: '📤' },
    pending_party_acceptance:  { label: 'Party Review',          color: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',icon: '🤝' },
    pending_ward:              { label: 'Ward Review',           color: 'bg-amber-500/20 text-amber-300 border-amber-500/30',  icon: '⏳' },
    ward_certified:            { label: 'Ward Certified',        color: 'bg-teal-500/20 text-teal-300 border-teal-500/30',    icon: '✓' },
    pending_constituency:      { label: 'Constituency Review',   color: 'bg-blue-500/20 text-blue-300 border-blue-500/30',    icon: '⏳' },
    constituency_certified:    { label: 'Constituency Certified',color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30',    icon: '✓' },
    pending_admin_area:        { label: 'Admin Area Review',     color: 'bg-purple-500/20 text-purple-300 border-purple-500/30',icon:'⏳' },
    admin_area_certified:      { label: 'Admin Area Certified',  color: 'bg-violet-500/20 text-violet-300 border-violet-500/30',icon:'✓' },
    pending_national:          { label: 'National Review',       color: 'bg-pink-500/20 text-pink-300 border-pink-500/30',    icon: '⏳' },
    nationally_certified:      { label: 'Nationally Certified',  color: 'bg-green-500/20 text-green-300 border-green-500/30', icon: '🏆' },
    rejected:                  { label: 'Rejected',              color: 'bg-red-500/20 text-red-300 border-red-500/30',       icon: '✗' },
};

const PIPELINE_STEPS = [
    'submitted', 'pending_party_acceptance', 'pending_ward',
    'ward_certified', 'pending_constituency', 'constituency_certified',
    'pending_admin_area', 'admin_area_certified', 'pending_national', 'nationally_certified',
];

function PipelineBar({ status }) {
    const currentStep = PIPELINE_STEPS.indexOf(status);
    const totalSteps  = PIPELINE_STEPS.length;
    const pct         = currentStep >= 0 ? Math.round(((currentStep + 1) / totalSteps) * 100) : 0;
    const cfg         = STATUS_CONFIG[status] || {};

    return (
        <div>
            <div className="flex justify-between text-xs text-gray-400 mb-1">
                <span>Certification Progress</span>
                <span className="font-semibold">{pct}%</span>
            </div>
            <div className="w-full bg-slate-700 rounded-full h-2">
                <div
                    className="h-2 rounded-full transition-all duration-500"
                    style={{
                        width: `${pct}%`,
                        background: status === 'nationally_certified'
                            ? 'linear-gradient(90deg, #10b981, #14b8a6)'
                            : status.includes('rejected') || status === 'submitted'
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

    const pendingCount   = submissions.filter(s => ['submitted','pending_party_acceptance','pending_ward'].includes(s.certification_status)).length;
    const certifiedCount = submissions.filter(s => ['ward_certified','constituency_certified','admin_area_certified','nationally_certified'].includes(s.certification_status)).length;
    const rejectedCount  = submissions.filter(s => s.is_editable).length;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/officer/dashboard"
                          className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        ← Officer Dashboard
                    </Link>
                    <div className="flex flex-wrap gap-4 items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-white">My Submissions</h1>
                            {station && (
                                <p className="text-gray-400 mt-0.5 text-sm">
                                    {station.name}
                                    <span className="ml-2 font-mono text-xs text-gray-500 bg-slate-900/60 px-1.5 py-0.5 rounded">{station.code}</span>
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
                    <div className="px-4 py-2 bg-slate-700/40 border border-slate-600/30 rounded-full text-gray-300 text-sm">
                        📋 {submissions.length} total submission{submissions.length !== 1 ? 's' : ''}
                    </div>
                    {pendingCount > 0 && (
                        <div className="px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-full text-amber-300 text-sm">
                            ⏳ {pendingCount} in pipeline
                        </div>
                    )}
                    {certifiedCount > 0 && (
                        <div className="px-4 py-2 bg-teal-500/15 border border-teal-500/30 rounded-full text-teal-300 text-sm">
                            ✓ {certifiedCount} certified
                        </div>
                    )}
                    {rejectedCount > 0 && (
                        <div className="px-4 py-2 bg-red-500/15 border border-red-500/30 rounded-full text-red-300 text-sm">
                            ✗ {rejectedCount} rejected — action required
                        </div>
                    )}
                </div>

                {submissions.length === 0 ? (
                    <div className="bg-slate-800/40 rounded-xl p-16 border border-slate-700/50 text-center">
                        <div className="text-5xl mb-4">📋</div>
                        <h2 className="text-xl font-bold text-white mb-2">No Submissions Yet</h2>
                        <p className="text-gray-400 text-sm mb-6">Submit your station's election results to get started.</p>
                        <Link href="/officer/results/submit"
                              className="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg">
                            Submit Results →
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {submissions.map((submission) => {
                            const cfg       = STATUS_CONFIG[submission.certification_status] || { label: submission.certification_status, color: 'bg-gray-500/20 text-gray-300 border-gray-500/30', icon: '○' };
                            const isExpanded = expandedId === submission.id;

                            return (
                                <div key={submission.id}
                                     className={`bg-slate-800/40 rounded-xl border transition-all ${
                                         submission.is_editable
                                             ? 'border-red-500/40'
                                             : submission.certification_status === 'nationally_certified'
                                             ? 'border-green-500/30'
                                             : 'border-slate-700/50'
                                     }`}>

                                    {/* Main row */}
                                    <div className="p-5">
                                        <div className="flex flex-wrap gap-3 items-start justify-between mb-3">
                                            <div>
                                                <div className="flex items-center gap-3 flex-wrap">
                                                    <h3 className="text-white font-bold">{submission.polling_station_name}</h3>
                                                    <span className="font-mono text-xs text-gray-500 bg-slate-900/50 px-2 py-0.5 rounded">
                                                        {submission.polling_station_code}
                                                    </span>
                                                    <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border ${cfg.color}`}>
                                                        {cfg.icon} {cfg.label}
                                                    </span>
                                                    {submission.rejection_count > 0 && (
                                                        <span className="px-2 py-0.5 rounded-full text-xs bg-orange-500/20 text-orange-300">
                                                            Rejected {submission.rejection_count}×
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-gray-500 text-xs mt-1">
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
                                                    className="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-gray-300 text-xs rounded-lg"
                                                >
                                                    {isExpanded ? 'Hide' : 'Details'}
                                                </button>
                                            </div>
                                        </div>

                                        {/* Stats row */}
                                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                                            {[
                                                { label: 'Registered',   value: submission.total_registered_voters?.toLocaleString(), color: 'text-gray-300' },
                                                { label: 'Votes Cast',   value: submission.total_votes_cast?.toLocaleString(),         color: 'text-white' },
                                                { label: 'Turnout',      value: `${submission.turnout}%`,                             color: 'text-blue-300' },
                                                { label: 'Valid',        value: submission.valid_votes?.toLocaleString(),              color: 'text-teal-300' },
                                            ].map((s) => (
                                                <div key={s.label} className="bg-slate-900/40 rounded-lg p-2.5 text-center">
                                                    <div className={`font-bold text-sm ${s.color}`}>{s.value}</div>
                                                    <div className="text-gray-500 text-xs">{s.label}</div>
                                                </div>
                                            ))}
                                        </div>

                                        {/* Pipeline bar */}
                                        <PipelineBar status={submission.certification_status} />
                                    </div>

                                    {/* Expanded details */}
                                    {isExpanded && (
                                        <div className="border-t border-slate-700/50 p-5 space-y-4">

                                            {/* Rejection reason */}
                                            {submission.last_rejection_reason && (
                                                <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-lg">
                                                    <div className="text-xs text-red-400 font-semibold mb-1">Rejection Reason</div>
                                                    <div className="text-red-200 text-sm"
                                                         dangerouslySetInnerHTML={{ __html: submission.last_rejection_reason }} />
                                                </div>
                                            )}

                                            {/* Candidate breakdown */}
                                            {submission.candidate_votes?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Candidate Results</div>
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
                                                                        <span className="text-gray-300 text-sm w-32 truncate">{cv.candidate_name}</span>
                                                                        <span className="text-xs text-gray-500 w-8">{cv.party_abbr}</span>
                                                                        <div className="flex-1 bg-slate-700 rounded-full h-1.5">
                                                                            <div className="h-1.5 rounded-full"
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

                                            {/* Photo */}
                                            {submission.photo_url && (
                                                <div>
                                                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-2">Result Sheet Photo</div>
                                                    <a href={submission.photo_url} target="_blank" rel="noopener noreferrer"
                                                       className="inline-flex items-center gap-2 text-blue-400 hover:text-blue-300 text-sm">
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