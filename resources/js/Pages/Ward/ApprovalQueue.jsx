import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';

export default function WardApprovalQueue({ auth, pendingResults = [] }) {
    const { post, processing } = useForm();

    const handleApprove = (resultId) => {
        if (confirm('Are you sure you want to approve this result?')) {
            post(`/ward/approve/${resultId}`);
        }
    };

    const handleReject = (resultId) => {
        const reason = prompt('Enter rejection reason:');
        if (reason) {
            post(`/ward/reject/${resultId}`, {
                data: { comments: reason }
            });
        }
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Ward Approval Queue</h1>

                {pendingResults.length > 0 && (
                    <div className="bg-amber-500/20 border border-amber-500/50 rounded-xl p-4 mb-6">
                        <p className="text-amber-300">
                            ⏳ <strong>{pendingResults.length} results</strong> awaiting your approval
                        </p>
                    </div>
                )}

                <div className="space-y-4">
                    {pendingResults.length > 0 ? (
                        pendingResults.map((result) => (
                            <div key={result.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 className="text-xl font-bold text-white">{result.polling_station}</h3>
                                        <p className="text-gray-400 text-sm">
                                            Submitted by {result.officer} at {result.submitted_at}
                                        </p>
                                    </div>
                                    <span className={`px-4 py-2 rounded-lg ${
                                        result.party_acceptance === 'All Accepted'
                                            ? 'bg-teal-500/20 text-teal-300'
                                            : 'bg-amber-500/20 text-amber-300'
                                    }`}>
                                        Party: {result.party_acceptance}
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Total Votes</div>
                                        <div className="text-white font-bold text-2xl">{result.total_votes}</div>
                                    </div>
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Turnout</div>
                                        <div className="text-white font-bold text-2xl">{result.turnout}</div>
                                    </div>
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Valid Votes</div>
                                        <div className="text-white font-bold text-2xl">{result.valid_votes}</div>
                                    </div>
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Rejected</div>
                                        <div className="text-white font-bold text-2xl">{result.rejected_votes}</div>
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <button
                                        onClick={() => handleApprove(result.id)}
                                        disabled={processing}
                                        className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:bg-gray-600 text-white font-bold rounded-lg"
                                    >
                                        ✓ Approve & Certify
                                    </button>
                                    <button
                                        onClick={() => handleReject(result.id)}
                                        disabled={processing}
                                        className="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 disabled:bg-gray-600 text-white font-bold rounded-lg"
                                    >
                                        ✗ Reject & Return
                                    </button>
                                    <button className="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <div className="text-6xl mb-4">✓</div>
                            <p className="text-gray-300 text-lg">No results pending approval</p>
                            <p className="text-gray-500 text-sm mt-2">All results have been processed</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
