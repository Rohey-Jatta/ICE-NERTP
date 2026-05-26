import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Publish({ auth, readinessCheck = {}, summary = {} }) {
    const [publishConfirm, setPublishConfirm] = useState('');
    const [closeConfirm, setCloseConfirm] = useState('');
    const { post, processing } = useForm();

    const canPublish = readinessCheck.canPublish;
    const canClose = readinessCheck.canPublish;

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
                    {/* <Link
                        href="/chairman/dashboard"
                        className="text-slate-500 hover:text-iec-navy text-sm inline-flex items-center gap-1 mb-3"
                    >
                        ← Chairman Dashboard
                    </Link> */}
                    <h1 className="text-3xl font-bold text-iec-navy">Publish Certified Results</h1>
                    {/* <p className="text-slate-700 mt-1 text-sm">
                        Publish station results that have been certified by the UEC chairman. Party representative acceptance is optional and does not need to complete before publication.
                    </p> */}
                </div>

                {/* Warning */}
                <div className="mb-6 p-5 bg-red-500/10 border border-red-500/40 rounded-xl">
                    <h2 className="text-red-500 font-bold mb-1">⚠ Critical Action</h2>
                    <p className="text-red-400 text-sm">
                        Publishing will expose certified station results publicly, but it will keep the election open for additional
                        station submissions until you explicitly close the election.
                    </p>
                </div>

                {/* Readiness checks */}
                <div className="bg-white rounded-xl p-6 border border-slate-200 mb-6">
                    <h2 className="text-iec-navy font-bold text-lg mb-4">Publication Readiness</h2>
                    <div className="space-y-3">
                        <div className={`flex items-center gap-4 p-4 rounded-xl border ${
                            canPublish
                                ? 'bg-iec-pink-500/10 border-teal-500/30'
                                : 'bg-red-500/10 border-red-500/30'
                        }`}>
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-iec-navy font-bold text-sm flex-shrink-0 ${
                                canPublish ? 'bg-iec-pink-600' : 'bg-red-600'
                            }`}>
                                {canPublish ? '✓' : '✗'}
                            </div>
                            <div>
                                <div className={`font-semibold ${canPublish ? 'text-iec-pink-600' : 'text-red-300'}`}>
                                    At least one certified station is ready to publish
                                </div>
                                <div className="text-slate-500 text-xs">
                                    {summary.certified || 0} station{summary.certified === 1 ? '' : 's'} have been certified.
                                </div>
                            </div>
                        </div>
                        {/* <div className="rounded-xl border border-slate-200 bg-slate-50 p-4"> */}
                            {/* <div className="font-semibold text-slate-800">Party acceptance is optional for publication</div> */}
                            {/* <p className="text-slate-500 text-xs mt-1">
                                This publish step can be taken whenever the chairman has certified station results, even if party representatives have not yet recorded their responses.
                            </p> */}
                        {/* </div> */}
                    </div>
                </div>

                {/* Summary */}
                <div className="bg-white rounded-xl p-6 border border-slate-200 mb-6">
                    <h2 className="text-iec-navy font-bold text-lg mb-4">Certification Summary</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        {[
                            { label: 'Total Stations',   value: summary.total || 0,               color: 'text-iec-navy' },
                            { label: 'Certified',        value: summary.certified || 0,            color: 'text-iec-pink-600' },
                            { label: '% Complete',       value: `${summary.percentComplete || 0}%`, color: 'text-iec-pink-600' },
                            { label: 'Still Pending',    value: summary.pendingNational || 0,      color: summary.pendingNational > 0 ? 'text-amber-300' : 'text-slate-500' },
                        ].map((s) => (
                            <div key={s.label} className="bg-white rounded-lg p-3 text-center">
                                <div className={`text-xl font-bold ${s.color}`}>{s.value}</div>
                                <div className="text-slate-500 text-xs">{s.label}</div>
                            </div>
                        ))}
                    </div>
                    <div className="w-full bg-white rounded-full h-3">
                        <div
                            className="bg-gradient-to-r from-teal-600 to-green-500 h-3 rounded-full transition-all"
                            style={{ width: `${summary.percentComplete || 0}%` }}
                        />
                    </div>
                    <p className="text-slate-500 text-xs mt-2 text-right">Last updated: {summary.lastUpdated}</p>
                </div>

                {/* View Live Results Link */}
                <div className="bg-white rounded-xl p-4 border border-slate-200 mb-6">
                    <Link
                        href="/results"
                        className="flex items-center justify-center text-sm font-semibold text-iec-pink-600 hover:text-iec-pink-700 transition-colors"
                    >
                        View Live Public Results Page
                    </Link>
                </div>

                {/* Confirmation */}
                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <h2 className="text-iec-navy font-bold text-lg mb-3">Confirmation Required</h2>
                    <p className="text-slate-500 text-sm mb-4">
                        Type the following phrase exactly to enable the publish button:
                    </p>
                    <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-lg mb-4 text-center">
                        <span className="text-red-400 font-mono font-bold">PUBLISH CERTIFIED RESULTS</span>
                    </div>
                    <input
                        type="text"
                        value={publishConfirm}
                        onChange={(e) => setPublishConfirm(e.target.value)}
                        placeholder="Type confirmation phrase here..."
                        className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy mb-4 focus:outline-none focus:border-red-500"
                    />
                    <button
                        onClick={handlePublish}
                        disabled={publishConfirm !== 'PUBLISH CERTIFIED RESULTS' || processing || !canPublish}
                        className="w-full py-4 bg-gradient-to-r from-green-600 to-teal-400 hover:from-green-500 hover:to-teal-300 disabled:from-slate-300 disabled:to-slate-300 disabled:cursor-not-allowed text-white font-bold rounded-xl text-lg transition-all"
                    >
                        {processing ? 'Publishing…' : '📢 Publish Certified Results to Public'}
                    </button>
                    {!canPublish && (
                        <p className="text-red-400 text-xs text-center mt-2">
                            You need at least one certified station before this election can be published.
                        </p>
                    )}

                    <div className="mt-8 border-t border-slate-200 pt-6">
                        <h3 className="text-iec-navy font-semibold text-lg mb-3">Close Election</h3>
                        <p className="text-slate-500 text-sm mb-4">
                            When you are ready to finalize this election, close it to prevent any further station submissions.
                        </p>
                        <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg mb-4">
                            <span className="font-mono text-slate-600">CLOSE ELECTION</span>
                        </div>
                        <input
                            type="text"
                            value={closeConfirm}
                            onChange={(e) => setCloseConfirm(e.target.value)}
                            placeholder="Type closure confirmation phrase here..."
                            className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy mb-4 focus:outline-none focus:border-red-500"
                        />
                        <button
                            onClick={handleClose}
                            disabled={closeConfirm !== 'CLOSE ELECTION' || processing || !canClose}
                            className="w-full py-4 bg-gradient-to-r from-rose-600 to-red-500 hover:from-rose-500 hover:to-red-400 disabled:from-slate-300 disabled:to-slate-300 disabled:cursor-not-allowed text-white font-bold rounded-xl text-lg transition-all"
                        >
                            {processing ? 'Closing…' : '🔒 Close Election'}
                        </button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
