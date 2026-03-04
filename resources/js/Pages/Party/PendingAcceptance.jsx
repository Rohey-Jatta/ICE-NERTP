import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

export default function PendingAcceptance({ auth, pendingResults }) {
    const [selectedResult, setSelectedResult] = useState(null);
    const [decision, setDecision] = useState(null);
    const [comments, setComments] = useState('');

    const handleSubmit = (resultId, status) => {
        setDecision(status);
        // TODO: Submit to backend
        alert(`Submitted: ${status} for result ${resultId}`);
    };

    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">Pending Result Acceptance</h1>
                    <a href="/party/dashboard" className="px-4 py-2 bg-slate-700 text-white rounded-lg">
                        ← Back to Dashboard
                    </a>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <h2 className="text-xl font-bold text-white mb-4">Results Awaiting Your Decision ({pendingResults?.length || 0})</h2>
                    
                    {pendingResults?.length > 0 ? (
                        <div className="space-y-4">
                            {pendingResults.map((result) => (
                                <div key={result.id} className="bg-slate-900/50 rounded-lg p-6 border border-slate-700/30">
                                    <div className="flex justify-between items-start mb-4">
                                        <div>
                                            <h3 className="text-xl font-bold text-white">{result.polling_station.name}</h3>
                                            <p className="text-gray-400 text-sm">Code: {result.polling_station.code}</p>
                                        </div>
                                        <span className="px-3 py-1 bg-amber-500/20 text-amber-300 rounded-full text-sm">
                                            Awaiting Decision
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <div className="text-gray-400 text-sm">Total Cast</div>
                                            <div className="text-2xl font-bold text-white">{result.total_votes_cast}</div>
                                        </div>
                                        <div>
                                            <div className="text-gray-400 text-sm">Valid Votes</div>
                                            <div className="text-2xl font-bold text-teal-300">{result.valid_votes}</div>
                                        </div>
                                        <div>
                                            <div className="text-gray-400 text-sm">Rejected</div>
                                            <div className="text-2xl font-bold text-amber-300">{result.rejected_votes}</div>
                                        </div>
                                    </div>

                                    {/* THREE OPTIONS: Accept, Accept with Reservation, Reject */}
                                    <div className="mb-4">
                                        <label className="block text-white mb-2">Your Decision:</label>
                                        <div className="grid grid-cols-3 gap-3">
                                            <button
                                                onClick={() => handleSubmit(result.id, 'accepted')}
                                                className="px-4 py-3 bg-teal-700 hover:bg-teal-600 text-white rounded-lg font-semibold"
                                            >
                                                ✓ Accept
                                            </button>
                                            <button
                                                onClick={() => setSelectedResult(result.id)}
                                                className="px-4 py-3 bg-amber-700 hover:bg-amber-600 text-white rounded-lg font-semibold"
                                            >
                                                ⚠️ Accept with Reservation
                                            </button>
                                            <button
                                                onClick={() => setSelectedResult(result.id)}
                                                className="px-4 py-3 bg-red-700 hover:bg-red-600 text-white rounded-lg font-semibold"
                                            >
                                                ✗ Reject
                                            </button>
                                        </div>
                                    </div>

                                    {selectedResult === result.id && (
                                        <div className="mt-4 p-4 bg-slate-800/50 rounded-lg">
                                            <label className="block text-white mb-2">
                                                Add Comments (Required):
                                            </label>
                                            <textarea
                                                value={comments}
                                                onChange={(e) => setComments(e.target.value)}
                                                placeholder="Explain your reservation or rejection..."
                                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-700 rounded-lg text-white"
                                                rows="4"
                                            />
                                            <div className="flex gap-3 mt-3">
                                                <button
                                                    onClick={() => handleSubmit(result.id, 'accepted_with_reservation')}
                                                    className="px-4 py-2 bg-teal-700 text-white rounded-lg"
                                                >
                                                    Submit
                                                </button>
                                                <button
                                                    onClick={() => setSelectedResult(null)}
                                                    className="px-4 py-2 bg-slate-700 text-white rounded-lg"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-12 text-gray-400">
                            No pending results for acceptance
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
