import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import { useForm } from '@inertiajs/react';

export default function Publish({ auth, readinessCheck = {}, summary = {} }) {
    const [publishConfirm, setPublishConfirm] = useState('');
    const { post, processing } = useForm();

    const handlePublish = () => {
        if (publishConfirm === 'PUBLISH FINAL RESULTS') {
            post('/chairman/publish-results');
        } else {
            alert('Please type the confirmation phrase exactly');
        }
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <h1 className="text-3xl font-bold text-white mb-6">Publish Final Results</h1>

                <div className="bg-red-500/20 border border-red-500/50 rounded-xl p-6 mb-6">
                    <p className="text-red-300">
                        ⚠️ <strong>CRITICAL ACTION:</strong> Publishing will make results visible to the public. This action is IRREVERSIBLE.
                    </p>
                </div>

                {/* Publication Readiness Check */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                    <h2 className="text-xl font-bold text-white mb-4">Publication Readiness</h2>

                    <div className="space-y-4">
                        <div className="flex items-center gap-3">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white font-bold ${
                                readinessCheck.allCertified ? 'bg-green-500' : 'bg-red-500'
                            }`}>
                                {readinessCheck.allCertified ? '✓' : '✗'}
                            </div>
                            <div>
                                <div className="text-white font-semibold">All Stations Nationally Certified</div>
                                <div className="text-gray-400 text-sm">
                                    {summary.certified || 0} / {summary.total || 0} stations
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white font-bold ${
                                readinessCheck.partyAcceptances ? 'bg-green-500' : 'bg-red-500'
                            }`}>
                                {readinessCheck.partyAcceptances ? '✓' : '✗'}
                            </div>
                            <div>
                                <div className="text-white font-semibold">All Party Acceptances Recorded</div>
                                <div className="text-gray-400 text-sm">No outstanding party disputes</div>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white font-bold ${
                                readinessCheck.auditComplete ? 'bg-green-500' : 'bg-red-500'
                            }`}>
                                {readinessCheck.auditComplete ? '✓' : '✗'}
                            </div>
                            <div>
                                <div className="text-white font-semibold">All Audit Logs Complete</div>
                                <div className="text-gray-400 text-sm">Full certification chain verified</div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Certification Summary */}
                {Object.keys(summary).length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                        <h2 className="text-xl font-bold text-white mb-4">Final Certification Summary</h2>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="bg-slate-900/50 p-4 rounded-lg">
                                <div className="text-gray-400 text-sm">Completion</div>
                                <div className="text-white font-bold text-3xl">{summary.percentComplete || 0}%</div>
                            </div>
                            <div className="bg-slate-900/50 p-4 rounded-lg">
                                <div className="text-gray-400 text-sm">Last Updated</div>
                                <div className="text-white font-semibold">{summary.lastUpdated || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Confirmation Section */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                    <h2 className="text-xl font-bold text-white mb-4">Confirmation Required</h2>

                    <p className="text-gray-300 mb-4">
                        To publish the final results, type the following phrase exactly:
                    </p>

                    <div className="bg-amber-500/20 border border-amber-500/50 rounded-lg p-4 mb-4">
                        <p className="text-amber-300 font-mono font-bold text-center">
                            PUBLISH FINAL RESULTS
                        </p>
                    </div>

                    <input
                        type="text"
                        value={publishConfirm}
                        onChange={(e) => setPublishConfirm(e.target.value)}
                        placeholder="Type confirmation phrase here..."
                        className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white mb-4"
                    />

                    <button
                        onClick={handlePublish}
                        disabled={publishConfirm !== 'PUBLISH FINAL RESULTS' || processing}
                        className="w-full px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 disabled:from-gray-600 disabled:to-gray-700 text-white font-bold rounded-lg shadow-lg text-lg disabled:cursor-not-allowed"
                    >
                        {processing ? 'Publishing...' : '🚀 PUBLISH FINAL RESULTS TO PUBLIC'}
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
