import AppLayout from '@/Layouts/AppLayout';

export default function AdminAreaAnalytics({ auth, stats = {}, constituencies = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Admin Area Analytics</h1>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div className="bg-gradient-to-br from-slate-800 to-slate-600 rounded-xl p-6 text-white">
                        <div className="text-sm opacity-90 mb-2">Total Constituencies</div>
                        <div className="text-4xl font-bold">{stats.totalConstituencies || 0}</div>
                    </div>
                    <div className="bg-gradient-to-br from-slate-600 to-slate-800 rounded-xl p-6 text-white">
                        <div className="text-sm opacity-90 mb-2">Certified</div>
                        <div className="text-4xl font-bold">{stats.certified || 0}</div>
                    </div>
                    <div className="bg-gradient-to-br from-slate-600 to-slate-800 rounded-xl p-6 text-white">
                        <div className="text-sm opacity-90 mb-2">Total Wards</div>
                        <div className="text-4xl font-bold">{stats.totalWards || 0}</div>
                    </div>
                    <div className="bg-gradient-to-br from-slate-600 to-slate-800 rounded-xl p-6 text-white">
                        <div className="text-sm opacity-90 mb-2">Total Votes</div>
                        <div className="text-4xl font-bold">{stats.totalVotes?.toLocaleString() || 0}</div>
                    </div>
                </div>

                {/* Progress Chart */}
                {constituencies.length > 0 && (
                    <div className="bg-slate-700/40 rounded-xl p-6 border border-slate-600/50 mb-6">
                        <h2 className="text-xl font-bold text-white mb-6">Certification Progress by Constituency</h2>
                        <div className="space-y-4">
                            {constituencies.map((constituency, i) => (
                                <div key={i}>
                                    <div className="flex justify-between text-sm mb-2">
                                        <span className="text-gray-300">{constituency.name}</span>
                                        <span className="text-white font-bold">{constituency.progress || 0}%</span>
                                    </div>
                                    <div className="w-full bg-slate-700 rounded-full h-3">
                                        <div
                                            className="bg-gradient-to-r from-teal-500 to-teal-600 h-3 rounded-full"
                                            style={{ width: `${constituency.progress || 0}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Turnout Analysis */}
                {stats.avgTurnout && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-4">Turnout Analysis</h2>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="bg-slate-900/50 p-4 rounded-lg">
                                <div className="text-gray-400 text-sm mb-2">Average Turnout</div>
                                <div className="text-white font-bold text-3xl">{stats.avgTurnout}%</div>
                            </div>
                            <div className="bg-slate-900/50 p-4 rounded-lg">
                                <div className="text-gray-400 text-sm mb-2">Highest Turnout</div>
                                <div className="text-white font-bold text-3xl">{stats.highestTurnout}%</div>
                            </div>
                            <div className="bg-slate-900/50 p-4 rounded-lg">
                                <div className="text-gray-400 text-sm mb-2">Lowest Turnout</div>
                                <div className="text-white font-bold text-3xl">{stats.lowestTurnout}%</div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
