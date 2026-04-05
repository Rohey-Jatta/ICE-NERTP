import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Publish({ auth, readinessCheck = {}, summary = {} }) {
    const [publishConfirm, setPublishConfirm] = useState('');
    const { post, processing } = useForm();

    const allReady = readinessCheck.allCertified && readinessCheck.partyAcceptances && readinessCheck.auditComplete;

    const handlePublish = () => {
        if (publishConfirm === 'PUBLISH FINAL RESULTS') {
            post('/chairman/publish-results');
        } else {
            alert('Please type the confirmation phrase exactly as shown.');
        }
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-3xl">

                <div className="mb-6">
                    <Link href="/chairman/dashboard"
                          className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        ← Chairman Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Publish Final Results</h1>
                    <p className="text-gray-400 mt-1 text-sm">
                        Make nationally certified results publicly visible. This action is <strong className="text-red-300">irreversible</strong>.
                    </p>
                </div>

                {/* Warning */}
                <div className="mb-6 p-5 bg-red-500/10 border border-red-500/40 rounded-xl">
                    <h2 className="text-red-300 font-bold mb-1">⚠ Critical Action</h2>
                    <p className="text-red-400 text-sm">
                        Publishing will make all nationally certified results permanently visible to the public.
                        Ensure all results have been reviewed and certified before proceeding.
                    </p>
                </div>

                {/* Readiness checks */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                    <h2 className="text-white font-bold text-lg mb-4">Publication Readiness Checklist</h2>
                    <div className="space-y-3">
                        {[
                            {
                                ok:    readinessCheck.allCertified,
                                label: 'All Stations Nationally Certified',
                                sub:   `${summary.certified || 0} / ${summary.total || 0} stations certified`,
                            },
                            {
                                ok:    readinessCheck.partyAcceptances,
                                label: 'Party Acceptance Records Complete',
                                sub:   'All party decisions logged in audit trail',
                            },
                            {
                                ok:    readinessCheck.auditComplete,
                                label: 'Certification Chain Verified',
                                sub:   'All approval levels completed',
                            },
                        ].map((check, i) => (
                            <div key={i} className={`flex items-center gap-4 p-4 rounded-xl border ${
                                check.ok
                                    ? 'bg-teal-500/10 border-teal-500/30'
                                    : 'bg-red-500/10 border-red-500/30'
                            }`}>
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0 ${
                                    check.ok ? 'bg-teal-600' : 'bg-red-600'
                                }`}>
                                    {check.ok ? '✓' : '✗'}
                                </div>
                                <div>
                                    <div className={`font-semibold ${check.ok ? 'text-teal-300' : 'text-red-300'}`}>
                                        {check.label}
                                    </div>
                                    <div className="text-gray-500 text-xs">{check.sub}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Summary */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                    <h2 className="text-white font-bold text-lg mb-4">Certification Summary</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        {[
                            { label: 'Total Stations',   value: summary.total || 0,           color: 'text-white' },
                            { label: 'Certified',        value: summary.certified || 0,        color: 'text-teal-300' },
                            { label: '% Complete',       value: `${summary.percentComplete || 0}%`, color: 'text-blue-300' },
                            { label: 'Still Pending',    value: summary.pendingNational || 0,  color: summary.pendingNational > 0 ? 'text-amber-300' : 'text-gray-400' },
                        ].map((s) => (
                            <div key={s.label} className="bg-slate-900/50 rounded-lg p-3 text-center">
                                <div className={`text-xl font-bold ${s.color}`}>{s.value}</div>
                                <div className="text-gray-500 text-xs">{s.label}</div>
                            </div>
                        ))}
                    </div>
                    <div className="w-full bg-slate-700 rounded-full h-3">
                        <div
                            className="bg-gradient-to-r from-teal-600 to-green-500 h-3 rounded-full transition-all"
                            style={{ width: `${summary.percentComplete || 0}%` }}
                        />
                    </div>
                    <p className="text-gray-500 text-xs mt-2 text-right">Last updated: {summary.lastUpdated}</p>
                </div>

                {/* Confirmation */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <h2 className="text-white font-bold text-lg mb-3">Confirmation Required</h2>
                    <p className="text-gray-400 text-sm mb-4">
                        Type the following phrase exactly to enable the publish button:
                    </p>
                    <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-lg mb-4 text-center">
                        <span className="text-red-300 font-mono font-bold">PUBLISH FINAL RESULTS</span>
                    </div>
                    <input
                        type="text"
                        value={publishConfirm}
                        onChange={(e) => setPublishConfirm(e.target.value)}
                        placeholder="Type confirmation phrase here..."
                        className="w-full px-4 py-3 bg-slate-900/60 border border-slate-600 rounded-lg text-white mb-4 focus:outline-none focus:border-red-500"
                    />
                    <button
                        onClick={handlePublish}
                        disabled={publishConfirm !== 'PUBLISH FINAL RESULTS' || processing || !allReady}
                        className="w-full py-4 bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-500 hover:to-teal-500 disabled:from-slate-700 disabled:to-slate-700 disabled:cursor-not-allowed text-white font-bold rounded-xl text-lg transition-all"
                    >
                        {processing ? 'Publishing…' : '📢 Publish Final Results to Public'}
                    </button>
                    {!allReady && (
                        <p className="text-red-400 text-xs text-center mt-2">
                            All readiness checks must pass before publishing.
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}