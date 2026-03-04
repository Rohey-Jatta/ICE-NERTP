import AppLayout from '@/Layouts/AppLayout';

export default function WardApprovalQueue({ auth, pendingResults }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">Ward Approval Queue</h1>
                    <a href="/ward/dashboard" className="px-4 py-2 bg-slate-700 text-white rounded-lg">
                        ← Back to Dashboard
                    </a>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <h2 className="text-xl font-bold text-white mb-4">Pending Results ({pendingResults?.length || 0})</h2>
                    
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
                                            Pending Approval
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

                                    <div className="flex gap-3">
                                        <button className="flex-1 px-4 py-2 bg-teal-700 hover:bg-teal-600 text-white rounded-lg">
                                            ✓ Approve
                                        </button>
                                        <button className="flex-1 px-4 py-2 bg-red-700 hover:bg-red-600 text-white rounded-lg">
                                            ✗ Reject
                                        </button>
                                        <button className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg">
                                            View Details →
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-12 text-gray-400">
                            No pending results for approval
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
