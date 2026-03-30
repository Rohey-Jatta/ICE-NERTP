import AppLayout from '@/Layouts/AppLayout';

export default function ChairmanAnalytics({ auth, nationalStats = {}, regionalBreakdown = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">National Analytics Dashboard</h1>

                {/* National Summary */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    <div className="bg-gradient-to-br from-slate-300 to-slate-800 rounded-xl p-6 text-gteal-800 shadow-lg">
                        <div className="text-sm opacity-90 mb-2">Total Polling Stations</div>
                        <div className="text-4xl font-bold">{nationalStats.totalStations?.toLocaleString() || 0}</div>
                    </div>
                    <div className="bg-gradient-to-br from-slate-400 to-slate-800 rounded-xl p-6 text-gteal-700 shadow-lg">
                        <div className="text-sm opacity-90 mb-2">Total Registered Voters</div>
                        <div className="text-4xl font-bold">{nationalStats.registeredVoters?.toLocaleString() || 0}</div>
                    </div>
                    <div className="bg-gradient-to-br from-slate-400 to-slate-800 rounded-xl p-6 text-gteal-800 shadow-lg">
                        <div className="text-sm opacity-90 mb-2">Total Votes Cast</div>
                        <div className="text-4xl font-bold">{nationalStats.votesCast?.toLocaleString() || 0}</div>
                    </div>
                    <div className="bg-gradient-to-br from-slate-400 to-slate-800 rounded-xl p-6 text-gteal-800 shadow-lg">
                        <div className="text-sm opacity-90 mb-2">National Turnout</div>
                        <div className="text-4xl font-bold">{nationalStats.turnout || 0}%</div>
                    </div>
                    <div className="bg-gradient-to-br from-slate-400 to-slate-800 rounded-xl p-6 text-gteal-800 shadow-lg">
                        <div className="text-sm opacity-90 mb-2">Nationally Certified</div>
                        <div className="text-4xl font-bold">{nationalStats.certifiedPercentage || 0}%</div>
                    </div>
                </div>

                {/* Regional Breakdown */}
                {regionalBreakdown.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                        <h2 className="text-xl font-bold text-white mb-6">Regional Certification Progress</h2>
                        <div className="space-y-4">
                            {regionalBreakdown.map((region, i) => (
                                <div key={i} className="bg-slate-900/50 p-4 rounded-lg">
                                    <div className="flex justify-between mb-2">
                                        <span className="text-white font-semibold">{region.name}</span>
                                        <span className="text-gray-400">{region.votes?.toLocaleString()} votes</span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="flex-1 bg-slate-700 rounded-full h-3">
                                            <div
                                                className={`h-3 rounded-full ${
                                                    region.progress === 100
                                                        ? 'bg-gradient-to-r from-teal-300 to-teal-600'
                                                        : 'bg-gradient-to-r from-teal-600 to-ateal-300'
                                                }`}
                                                style={{ width: `${region.progress}%` }}
                                            />
                                        </div>
                                        <span className="text-white font-bold w-12 text-right">{region.progress}%</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Party Performance */}
                {nationalStats.partyPerformance && nationalStats.partyPerformance.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-6">National Party Performance (Certified Results)</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {nationalStats.partyPerformance.map((party, i) => (
                                <div key={i} className="bg-slate-900/50 p-4 rounded-lg">
                                    <div className="flex justify-between items-center mb-3">
                                        <span className="text-white font-bold text-lg">{party.name}</span>
                                        <span className="text-slate-300 font-bold text-xl">{party.percentage}%</span>
                                    </div>
                                    <div className="text-gray-400 text-sm mb-2">{party.votes?.toLocaleString()} votes</div>
                                    <div className="bg-slate-700 rounded-full h-2">
                                        <div
                                            className="bg-slate-500 h-2 rounded-full"
                                            style={{ width: `${party.percentage}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
