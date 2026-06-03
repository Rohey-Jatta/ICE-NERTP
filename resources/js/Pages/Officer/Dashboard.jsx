import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ACTIVE_CERTIFICATION_PIPELINE } from '@/Utils/resultStatus';

export default function OfficerDashboard({ auth, station, statistics = {}, hasSubmitted, canSubmit }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-iec-navy">Polling Officer Dashboard</h1>
                    <p className="text-slate-500 mt-1 text-sm">
                        Your role: <strong className="text-iec-navy">Submit and manage election results</strong> from your assigned polling station.
                    </p>
                </div>

                {/* No station assigned warning */}
                {!station && (
                    <div className="mb-6 p-5 bg-red-500/10 border border-red-500/40 rounded-xl">
                        <h2 className="text-red-300 font-bold mb-1">⚠ No Polling Station Assigned</h2>
                        <p className="text-red-400 text-sm">
                            You have not been assigned to a polling station yet.
                            Contact the IEC Administrator to complete your assignment before Election Day.
                        </p>
                    </div>
                )}

                {/* Station Info */}
                {station && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200 mb-6">
                        <div className="flex flex-wrap gap-6 items-start justify-between">
                            <div>
                                <div className="text-xs text-slate-500 uppercase tracking-wide mb-1">Your Assigned Station</div>
                                <h2 className="text-2xl font-bold text-iec-navy">{station.name}</h2>
                                <div className="flex items-center gap-4 mt-2 flex-wrap">
                                    <span className="text-xs font-mono bg-white text-slate-600 px-2 py-1 rounded">
                                        {station.code}
                                    </span>
                                    <span className="text-slate-500 text-sm">
                                        {station.registered_voters?.toLocaleString()} registered voters
                                    </span>
                                    <span className="text-slate-500 text-sm">{station.election_name}</span>
                                </div>
                            </div>

                            {/* Submission status */}
                            {hasSubmitted ? (
                                <div className="px-4 py-2 bg-iec-pink-500/20 border border-teal-500/40 rounded-xl text-iec-pink-600 text-sm font-semibold">
                                    ✓ Results Submitted
                                </div>
                            ) : (
                                <div className="px-4 py-2 bg-pink-500/20 border border-pink-500/40 rounded-xl text-pink-600 text-sm font-semibold">
                                    Results Not Yet Submitted
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Rejection alert */}
                {statistics.rejected > 0 && (
                    <div className="mb-6 p-4 bg-red-500/10 border border-red-500/40 rounded-xl flex items-center gap-3">
                        <span className="w-3 h-3 bg-red-400 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-red-300 flex-1">
                            <strong>{statistics.rejected}</strong> result{statistics.rejected > 1 ? 's have' : ' has'} been
                            rejected and requires resubmission.
                        </p>
                        <Link href="/officer/results/submit"
                              className="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-bold rounded-lg whitespace-nowrap">
                            Resubmit Now →
                        </Link>
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    {[
                        { label: 'Total Submissions', value: statistics.totalSubmissions || 0, color: 'text-iec-navy' },
                        { label: 'In Pipeline',       value: statistics.pending          || 0, color: 'text-amber-400' },
                        { label: 'Certified',         value: statistics.certified        || 0, color: 'text-iec-pink-600' },
                        { label: 'Rejected',          value: statistics.rejected         || 0, color: 'text-red-700' },
                    ].map((card) => (
                        <div key={card.label} className="bg-white rounded-xl p-5 border border-slate-200">
                            <div className={`text-3xl font-bold mb-1 ${card.color}`}>{card.value}</div>
                            <div className="text-slate-500 text-sm">{card.label}</div>
                        </div>
                    ))}
                </div>

                {/* Quick Actions */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    {/* Submit Results */}
                    <Link
                        href="/officer/results/submit"
                        className={`group p-6 rounded-xl border transition-all ${
                            canSubmit
                                ? 'bg-iec-slate-600/20 hover:bg-iec-slate-700/30 border-iec-slate-200'
                                : statistics.rejected > 0
                                ? 'bg-red-600/15 hover:bg-red-600/25 border-red-500/30'
                                : 'bg-slate-100 border-slate-200 opacity-60 pointer-events-none'
                        }`}
                    >
                        <div className="flex items-start gap-3">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-xl ${
                                canSubmit ? 'bg-iec-pink-500/20' : statistics.rejected > 0 ? 'bg-red-500/20' : 'bg-slate-100/20'
                            }`}>
                                {statistics.rejected > 0 ? '↩' : '📋'}
                            </div>
                            <div>
                                <div className="font-bold text-iec-navy text-lg">
                                    {statistics.rejected > 0 ? 'Resubmit Results' : 'Submit Results'}
                                </div>
                                <div className="text-slate-500 text-sm mt-0.5">
                                    {canSubmit
                                        ? 'Enter vote counts, upload result sheet photo'
                                        : statistics.rejected > 0
                                        ? 'Correct and resubmit your rejected result'
                                        : 'Results already submitted for this station'}
                                </div>
                            </div>
                        </div>
                    </Link>

                    {/* View Submissions */}
                    <Link
                        href="/officer/submissions"
                        className="group p-6 bg-slate-100 hover:bg-slate-100 border border-slate-200 rounded-xl transition-all"
                    >
                        <div className="flex items-start gap-3">
                            <div className="w-10 h-10 bg-iec-pink-500/20 rounded-xl flex items-center justify-center text-xl">📊</div>
                            <div>
                                <div className="font-bold text-iec-navy text-lg">My Submissions</div>
                                <div className="text-slate-500 text-sm mt-0.5">
                                    View all submitted results and their current certification status
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                {/* Process guide */}
                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <h2 className="text-iec-navy font-bold text-lg mb-4">Certification Pipeline</h2>
                    <div className="flex flex-wrap gap-2 items-center">
                        {ACTIVE_CERTIFICATION_PIPELINE.map((step, idx) => (
                            <div key={step.key} className="flex items-center gap-2">
                                <div className="flex items-center gap-1.5 px-3 py-1.5 bg-white rounded-lg border border-slate-200">
                                    <span className="text-xs text-slate-500 font-mono">{step.step}</span>
                                    <span className="text-xs text-slate-600">{step.label}</span>
                                </div>
                                {idx < ACTIVE_CERTIFICATION_PIPELINE.length - 1 && (
                                    <span className="text-slate-600">→</span>
                                )}
                            </div>
                        ))}
                    </div>
                    <p className="text-slate-500 text-xs mt-3">
                        Party representatives review in parallel; their response is visible to approvers but does not block this certification path.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
