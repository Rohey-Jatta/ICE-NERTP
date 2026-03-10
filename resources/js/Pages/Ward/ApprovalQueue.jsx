import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';

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

    const handleView = (resultId) => {
        router.visit(`/results/${resultId}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Ward Approval Queue</h1>

                {pendingResults.length > 0 && (
                    <div className="bg-slate-500/20 border border-pink-300/50 rounded-xl p-4 mb-6">
                        <p className="text-pink-300">
                            <strong>{pendingResults.length} results</strong> awaiting your approval
                        </p>
                    </div>
                )}

                <div className="space-y-4">
                    {pendingResults.length > 0 ? (
                        pendingResults.map((result) => (
                            <div key={result.id} className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                                <div className="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 className="text-xl font-bold text-white">{result.polling_station}</h3>
                                        <p className="text-gray-400 text-sm">
                                            Submitted by {result.officer} at {result.submitted_at}
                                        </p>
                                    </div>
                                    <span className={`px-4 py-2 rounded-lg ${
                                        result.party_acceptance === 'All Accepted'
                                            ? 'bg-slate-500/20 text-pink-300'
                                            : 'bg-slate-500/20 text-blue-300'
                                    }`}>
                                        Party: {result.party_acceptance}
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                    <div className="bg-slate-800/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Total Votes</div>
                                        <div className="text-white font-bold text-2xl">{result.total_votes}</div>
                                    </div>
                                    <div className="bg-slate-800/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Turnout</div>
                                        <div className="text-white font-bold text-2xl">{result.turnout}</div>
                                    </div>
                                    <div className="bg-slate-800/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Valid Votes</div>
                                        <div className="text-white font-bold text-2xl">{result.valid_votes}</div>
                                    </div>
                                    <div className="bg-slate-800/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Rejected</div>
                                        <div className="text-white font-bold text-2xl">{result.rejected_votes}</div>
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <button
                                        onClick={() => handleApprove(result.id)}
                                        disabled={processing}
                                        className="flex-1 px-6 py-3 bg-slate-600 hover:bg-slate-700 disabled:bg-slate-600 text-white font-bold rounded-lg"
                                    >
                                        Approve & Certify
                                    </button>
                                    <button
                                        onClick={() => handleReject(result.id)}
                                        disabled={processing}
                                        className="flex-1 px-6 py-3 bg-slate-600 hover:bg-slate-700 disabled:bg-slate-600 text-white font-bold rounded-lg"
                                    >
                                        Reject & Return
                                    </button>
                                    <button
                                        onClick={() => handleView(result.id)}
                                        className="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg"
                                    >
                                        View Details
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-pink-300/50 text-center">
                            <p className="text-gray-300 text-lg">No results pending approval</p>
                            <p className="text-gray-500 text-sm mt-2">All results have been processed</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
