import AppLayout from '@/Layouts/AppLayout';

export default function WardAnalytics({ auth, stats = {}, stationBreakdown = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Ward Analytics</h1>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-gray-400 text-sm mb-2">Total Stations</div>
                        <div className="text-white font-bold text-3xl">{stats.totalStations || 0}</div>
                    </div>
                    <div className="bg-slate-800/40 border border-pink-300/50 rounded-xl p-6">
                        <div className="text-gray-400 text-sm mb-2">Certified</div>
                        <div className="text-white font-bold text-3xl">{stats.certified || 0}</div>
                    </div>
                    <div className="bg-slate-800/40 border border-pink-300/50 rounded-xl p-6">
                        <div className="text-gray-400 text-sm mb-2">Pending</div>
                        <div className="text-white font-bold text-3xl">{stats.pending || 0}</div>
                    </div>
                    <div className="bg-slate-800/40 border border-pink-300/50 rounded-xl p-6">
                        <div className="text-gray-400 text-sm mb-2">Rejected</div>
                        <div className="text-white font-bold text-3xl">{stats.rejected || 0}</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-gray-400 text-sm mb-2">Total Votes</div>
                        <div className="text-white font-bold text-3xl">{stats.totalVotes?.toLocaleString() || 0}</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-gray-400 text-sm mb-2">Turnout Rate</div>
                        <div className="text-white font-bold text-3xl">{stats.turnoutRate || 0}%</div>
                    </div>
                </div>

                {/* Progress Chart */}
                {stats.totalStations > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50 mb-6">
                        <h2 className="text-xl font-bold text-white mb-4">Certification Progress</h2>
                        <div className="space-y-4">
                            <div>
                                <div className="flex justify-between text-sm mb-2">
                                    <span className="text-gray-400">Certified</span>
                                    <span className="text-white font-bold">
                                        {((stats.certified / stats.totalStations) * 100).toFixed(1)}%
                                    </span>
                                </div>
                                <div className="w-full bg-slate-700 rounded-full h-4">
                                    <div
                                        className="bg-teal-600 h-4 rounded-full"
                                        style={{ width: `${(stats.certified / stats.totalStations) * 100}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Station Breakdown */}
                {stationBreakdown.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <h2 className="text-xl font-bold text-white mb-4">Station-by-Station Breakdown</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-pink-300">
                                        <th className="text-left text-gray-400 py-3">Station</th>
                                        <th className="text-right text-gray-400 py-3">Votes</th>
                                        <th className="text-right text-gray-400 py-3">Turnout</th>
                                        <th className="text-center text-gray-400 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stationBreakdown.map((station, i) => (
                                        <tr key={i} className="border-b border-pink-300/50">
                                            <td className="py-3 text-white">{station.name}</td>
                                            <td className="py-3 text-right text-white">{station.votes}</td>
                                            <td className="py-3 text-right text-white">{station.turnout}%</td>
                                            <td className="py-3 text-center">
                                                <span className={`px-3 py-1 rounded-full text-sm ${
                                                    station.status === 'Certified'
                                                        ? 'bg-teal-500/20 text-gray-400'
                                                        : 'bg-slate-500/20 text-gray-400'
                                                }`}>
                                                    {station.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
