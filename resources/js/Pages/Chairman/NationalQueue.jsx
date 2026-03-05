import AppLayout from '@/Layouts/AppLayout';

export default function NationalQueue({ auth, adminAreaResults = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">National Certification Queue</h1>

                {adminAreaResults.length > 0 && (
                    <div className="bg-blue-500/20 border border-blue-500/50 rounded-xl p-4 mb-6">
                        <p className="text-blue-300">
                            🏛️ <strong>Chairman Review:</strong> {adminAreaResults.length} administrative areas awaiting final national certification
                        </p>
                    </div>
                )}

                <div className="space-y-6">
                    {adminAreaResults.length > 0 ? (
                        adminAreaResults.map((area) => (
                            <div key={area.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex justify-between items-start mb-6">
                                    <div>
                                        <h3 className="text-2xl font-bold text-white mb-2">{area.name}</h3>
                                        <p className="text-gray-400">
                                            Admin Area certified at {area.certified_at}
                                        </p>
                                    </div>
                                    <span className="px-4 py-2 bg-purple-500/20 text-purple-300 rounded-lg font-semibold">
                                        Admin Area Certified
                                    </span>
                                </div>

                                <div className="grid grid-cols-3 gap-6 mb-6">
                                    <div className="bg-gradient-to-br from-blue-600/20 to-blue-800/20 border border-blue-500/30 p-6 rounded-lg">
                                        <div className="text-blue-300 text-sm mb-2">Constituencies</div>
                                        <div className="text-white font-bold text-3xl">{area.constituencies}</div>
                                    </div>
                                    <div className="bg-gradient-to-br from-teal-600/20 to-teal-800/20 border border-teal-500/30 p-6 rounded-lg">
                                        <div className="text-teal-300 text-sm mb-2">Total Votes</div>
                                        <div className="text-white font-bold text-3xl">{area.total_votes?.toLocaleString()}</div>
                                    </div>
                                    <div className="bg-gradient-to-br from-green-600/20 to-green-800/20 border border-green-500/30 p-6 rounded-lg">
                                        <div className="text-green-300 text-sm mb-2">Progress</div>
                                        <div className="text-white font-bold text-3xl">{area.progress}%</div>
                                    </div>
                                </div>

                                <div className="flex gap-4">
                                    <button
                                        onClick={() => alert('Backend route needed: POST /chairman/certify-national/' + area.id)}
                                        className="flex-1 px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-bold rounded-lg shadow-lg text-lg"
                                    >
                                        ✓ CERTIFY NATIONALLY (Final Approval)
                                    </button>
                                    <button
                                        onClick={() => alert('Backend route needed: POST /chairman/reject/' + area.id)}
                                        className="flex-1 px-8 py-4 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-bold rounded-lg shadow-lg text-lg"
                                    >
                                        ✗ REJECT & Return to Admin Area
                                    </button>
                                    <button className="px-8 py-4 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg">
                                        📊 Full Report
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-300">No admin areas awaiting national certification</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
