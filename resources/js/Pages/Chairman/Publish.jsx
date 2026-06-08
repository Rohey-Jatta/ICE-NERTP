import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const ELECTION_STATUS_CONFIG = {
    active:          { label: 'Open',             color: 'bg-pink-100 text-green-800 border-pink-300',   dot: 'bg-pink-500',  desc: 'Accepting submissions from polling officers.' },
    certifying:      { label: 'Certifying',       color: 'bg-blue-100 text-blue-800 border-blue-300',      dot: 'bg-blue-500',   desc: 'Certification in progress. Officers can still submit.' },
    results_pending: { label: 'Results Published', color: 'bg-amber-100 text-amber-800 border-amber-300',  dot: 'bg-amber-500',  desc: 'Results are visible publicly. Officers can still submit.' },
    certified:       { label: 'Closed',           color: 'bg-green-100 text-green-800 border-green-300',         dot: 'bg-green-500',    desc: 'Election is officially closed. No further submissions accepted.' },
};

export default function Publish({ auth, readinessCheck = {}, summary = {}, election = null }) {
    const [publishConfirm, setPublishConfirm] = useState('');
    const [closeConfirm,   setCloseConfirm]   = useState('');
    const { post, processing } = useForm();

    const canPublish = readinessCheck.canPublish;
    const canClose   = readinessCheck.canClose;
    const isClosed   = election?.status === 'certified';

    const statusCfg = election ? (ELECTION_STATUS_CONFIG[election.status] || ELECTION_STATUS_CONFIG.active) : null;

    const handlePublish = () => {
        if (publishConfirm === 'PUBLISH CERTIFIED RESULTS') {
            post('/chairman/publish-results');
        } else {
            alert('Please type the confirmation phrase exactly as shown.');
        }
    };

    const handleClose = () => {
        if (closeConfirm === 'CLOSE ELECTION') {
            post('/chairman/close-election');
        } else {
            alert('Please type the confirmation phrase exactly as shown.');
        }
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-3xl">

                <div className="mb-6">
                    <Link href="/chairman/dashboard"
                          className="text-slate-500 hover:text-iec-navy text-sm inline-flex items-center gap-1 mb-3">
                        ← Chairman Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Results Publication &amp; Election Closure</h1>
                    <p className="text-slate-500 mt-1 text-sm">
                        Manage result publication and election lifecycle. These are two distinct actions with different effects.
                    </p>
                </div>

                {/* Election Status Banner */}
                {statusCfg && election && (
                    <div className={`mb-6 p-4 rounded-xl border flex items-start gap-3 ${statusCfg.color}`}>
                        <span className={`w-3 h-3 rounded-full flex-shrink-0 mt-0.5 ${statusCfg.dot}`} />
                        <div>
                            <div className="font-bold text-sm">
                                Election Status: {statusCfg.label}
                            </div>
                            <div className="text-sm mt-0.5">{statusCfg.desc}</div>
                            {election.name && (
                                <div className="text-xs mt-1 opacity-75 font-medium">{election.name}</div>
                            )}
                        </div>
                    </div>
                )}

                {!election && (
                    <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm">
                        No active election found. Create and activate an election first.
                    </div>
                )}

                {/* Certification Summary */}
                {election && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200 mb-6">
                        <h2 className="text-iec-navy font-bold text-lg mb-4">Certification Progress</h2>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            {[
                                { label: 'Total Stations',   value: summary.total || 0,               color: 'text-iec-navy' },
                                { label: 'Nationally Certified', value: summary.certified || 0,        color: 'text-green-700' },
                                { label: 'Pending Chairman', value: summary.pendingNational || 0,      color: summary.pendingNational > 0 ? 'text-amber-700' : 'text-slate-500' },
                                { label: '% Complete',       value: `${summary.percentComplete || 0}%`, color: 'text-iec-pink-600' },
                            ].map((s) => (
                                <div key={s.label} className="bg-slate-50 rounded-lg p-3 text-center">
                                    <div className={`text-xl font-bold ${s.color}`}>{s.value}</div>
                                    <div className="text-slate-500 text-xs mt-1">{s.label}</div>
                                </div>
                            ))}
                        </div>
                        <div className="w-full bg-slate-200 rounded-full h-3">
                            <div
                                className="bg-gradient-to-r from-teal-600 to-green-500 h-3 rounded-full transition-all"
                                style={{ width: `${summary.percentComplete || 0}%` }}
                            />
                        </div>
                        <div className="flex justify-between text-xs text-slate-500 mt-2">
                            <span>Last updated: {summary.lastUpdated}</span>
                            <Link href="/chairman/national-queue" className="text-iec-pink-600 hover:underline font-semibold">
                                View Certification Queue →
                            </Link>
                        </div>
                    </div>
                )}

                {/* ── ACTION 1: Publish Results ─────────────────────────────── */}
                <div className={`bg-white rounded-xl border mb-6 overflow-hidden ${isClosed ? 'opacity-60' : ''}`}>
                    <div className="p-5 border-b border-slate-200 bg-green-50">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl flex-shrink-0">📢</span>
                            <div>
                                <h2 className="text-iec-navy font-bold text-lg">Action 1: Publish Certified Results</h2>
                                <p className="text-slate-600 text-sm mt-1">
                                    Makes nationally certified station results prominently visible on the public website.
                                    <strong className="text-green-700"> Polling officers can still submit results after this action.</strong>
                                </p>
                                <p className="text-slate-500 text-xs mt-2">
                                    Use this when you want the public to see certified results while the election is still ongoing.
                                    This changes election status from <code className="bg-white px-1 rounded">active</code> → <code className="bg-white px-1 rounded">results_pending</code>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="p-6">
                        {isClosed ? (
                            <div className="p-4 bg-slate-50 rounded-lg text-slate-500 text-sm text-center">
                                Election is already closed. This action is no longer available.
                            </div>
                        ) : election?.status === 'results_pending' ? (
                            <div className="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm text-center">
                                ✓ Results are already published. Officers can still submit. Use "Close Election" when you are ready to end submissions.
                            </div>
                        ) : (
                            <>
                                {!canPublish && (
                                    <div className="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs">
                                        ⚠ At least one result must be nationally certified before you can publish.
                                    </div>
                                )}
                                <p className="text-sm text-slate-600 mb-3">
                                    Type the following phrase exactly to enable the publish button:
                                </p>
                                <div className="p-3 bg-green-50 border border-green-200 rounded-lg mb-3 text-center">
                                    <span className="text-green-800 font-mono font-bold">PUBLISH CERTIFIED RESULTS</span>
                                </div>
                                <input
                                    type="text"
                                    value={publishConfirm}
                                    onChange={(e) => setPublishConfirm(e.target.value)}
                                    placeholder="Type confirmation phrase here..."
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy mb-4 focus:outline-none focus:border-green-500"
                                    disabled={!canPublish || isClosed}
                                />
                                <button
                                    onClick={handlePublish}
                                    disabled={publishConfirm !== 'PUBLISH CERTIFIED RESULTS' || processing || !canPublish || isClosed}
                                    className="w-full py-3 px-4 bg-green-600 hover:bg-green-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-all"
                                >
                                    {processing ? 'Publishing…' : '📢 Publish Certified Results (Election Stays Open)'}
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* ── ACTION 2: Close Election ──────────────────────────────── */}
                <div className={`bg-white rounded-xl border overflow-hidden ${isClosed ? 'opacity-60' : ''}`}>
                    <div className="p-5 border-b border-slate-200 bg-red-50">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl flex-shrink-0">🔒</span>
                            <div>
                                <h2 className="text-iec-navy font-bold text-lg">Action 2: Close Election</h2>
                                <p className="text-slate-600 text-sm mt-1">
                                    Permanently closes the election for result submissions.
                                    <strong className="text-red-700"> Polling officers will NOT be able to submit new results after this action.</strong>
                                </p>
                                <p className="text-slate-500 text-xs mt-2">
                                    Use this only when you are confident all polling stations have submitted their results.
                                    This is irreversible and changes election status to <code className="bg-white px-1 rounded">certified</code>.
                                    Publicly visible certified results remain accessible.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="p-6">
                        {isClosed ? (
                            <div className="p-4 bg-slate-50 rounded-lg text-slate-500 text-sm text-center">
                                ✓ Election is already closed. No further submissions can be made.
                            </div>
                        ) : (
                            <>
                                <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <p className="text-red-800 text-xs font-semibold">⚠ Warning: This action cannot be undone.</p>
                                    <p className="text-red-700 text-xs mt-1">
                                        After closing, polling officers will see an "Election Closed" message and cannot submit station results.
                                        Make sure you have received all polling stations'results before closing.
                                    </p>
                                </div>
                                <p className="text-sm text-slate-600 mb-3">
                                    Type the following phrase exactly to enable the close button:
                                </p>
                                <div className="p-3 bg-red-50 border border-red-200 rounded-lg mb-3 text-center">
                                    <span className="text-red-800 font-mono font-bold">CLOSE ELECTION</span>
                                </div>
                                <input
                                    type="text"
                                    value={closeConfirm}
                                    onChange={(e) => setCloseConfirm(e.target.value)}
                                    placeholder="Type confirmation phrase here..."
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy mb-4 focus:outline-none focus:border-red-500"
                                    disabled={!canClose || isClosed}
                                />
                                <button
                                    onClick={handleClose}
                                    disabled={closeConfirm !== 'CLOSE ELECTION' || processing || !canClose || isClosed}
                                    className="w-full py-3 px-4 bg-red-600 hover:bg-red-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-all"
                                >
                                    {processing ? 'Closing…' : '🔒 Close Election — Stop All Submissions'}
                                </button>
                                {!canClose && !isClosed && (
                                    <p className="text-slate-500 text-xs text-center mt-2">
                                        No open election found to close.
                                    </p>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {/* Quick links */}
                <div className="mt-6 flex flex-wrap gap-3">
                    <Link href="/chairman/national-queue"
                          className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg text-sm">
                        National Certification Queue
                    </Link>
                    <Link href="/results"
                          className="px-4 py-2 bg-white hover:bg-slate-100 text-iec-navy font-semibold rounded-lg text-sm border border-slate-200"
                          target="_blank">
                        View Public Results Page ↗
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
