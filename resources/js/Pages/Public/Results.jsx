import AppLayout from '@/Layouts/AppLayout';

export default function Results({ election, stats, candidates }) {
    if (!election) {
        return (
            <AppLayout>
                <div className="container mx-auto px-4 py-12 min-h-screen flex items-center justify-center">
                    <div className="text-center p-12 bg-slate-800/40 rounded-xl border border-slate-700/50 max-w-2xl">
                        <div className="text-6xl mb-6"></div>
                        <h1 className="text-3xl font-bold text-white mb-4">No Results Available</h1>
                        <p className="text-gray-400 mb-6">Results will be published once voting concludes.</p>
                        <a href="/" className="inline-block px-6 py-3 bg-teal-700 text-white rounded-lg font-semibold hover:bg-teal-800 transition-colors">
                            ← Back Home
                        </a>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const turnout = stats.total_registered > 0 ? (stats.total_cast / stats.total_registered * 100).toFixed(1) : 0;

    return (
        <AppLayout>
            <div className="container mx-auto px-4 py-8">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center mb-8">
                        
                        <h1 className="text-4xl font-bold text-white mb-6">{election.name}</h1>
                        
                        {/* Navigation Tabs - USING REGULAR <a> TAGS */}
                        <div className="flex justify-center gap-4 mb-8 flex-wrap">
                            <a 
                                href="/results"
                                className="px-6 py-3 bg-slate-700 text-white rounded-lg font-semibold shadow-lg hover:bg-slate-600 transition-colors"
                            >
                                Summary
                            </a>
                            <a 
                                href="/results/map"
                                className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all"
                            >
                                Map
                            </a>
                            <a 
                                href="/results/stations"
                                className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all"
                            >
                                Stations
                            </a>
                        </div>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <div className="text-3xl font-bold text-white mb-2">{parseInt(stats.total_registered).toLocaleString()}</div>
                            <div className="text-gray-400 text-sm">Registered Voters</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <div className="text-3xl font-bold text-white mb-2">{parseInt(stats.total_cast).toLocaleString()}</div>
                            <div className="text-gray-400 text-sm">Votes Cast</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <div className="text-3xl font-bold text-teal-300 mb-2">{parseInt(stats.valid_votes).toLocaleString()}</div>
                            <div className="text-gray-400 text-sm">Valid Votes</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <div className="text-3xl font-bold text-amber-300 mb-2">{parseInt(stats.rejected_votes).toLocaleString()}</div>
                            <div className="text-gray-400 text-sm">Rejected Votes</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <div className="text-3xl font-bold text-white mb-2">{turnout}%</div>
                            <div className="text-gray-400 text-sm">Turnout</div>
                        </div>
                    </div>

                    {/* Progress Bar */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-8">
                        <div className="flex justify-between items-center mb-3">
                            <div>
                                <div className="text-sm text-gray-400">Stations Reporting</div>
                                <div className="text-2xl font-bold text-white">
                                    {parseInt(stats.stations_reported)} / {parseInt(stats.total_stations)}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-sm text-gray-400">Progress</div>
                                <div className="text-2xl font-bold text-white">
                                    {stats.total_stations > 0 ? Math.round((stats.stations_reported / stats.total_stations) * 100) : 0}%
                                </div>
                            </div>
                        </div>
                        <div className="w-full bg-slate-700/50 rounded-full h-4">
                            <div 
                                className="bg-gradient-to-r from-teal-500 to-teal-600 h-4 rounded-full transition-all duration-500"
                                style={{ width: `${stats.total_stations > 0 ? (stats.stations_reported / stats.total_stations) * 100 : 0}%` }}
                            />
                        </div>
                    </div>

                    {/* Candidates Results */}
                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Candidate Results</h2>
                        <div className="space-y-4">
                            {candidates.map((candidate, index) => {
                                const percentage = stats.valid_votes > 0 ? (candidate.total_votes / stats.valid_votes * 100).toFixed(2) : 0;
                                return (
                                    <div key={candidate.id} className="bg-slate-900/50 rounded-lg p-4 border border-slate-700/30">
                                        <div className="flex justify-between mb-3">
                                            <div className="flex items-center gap-3">
                                                {index === 0 && <span className="text-2xl"></span>}
                                                <div>
                                                    <div className="font-bold text-white text-lg">{candidate.name}</div>
                                                    <div className="text-sm text-gray-400">{candidate.party_abbr}</div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-xl font-bold text-white">{parseInt(candidate.total_votes).toLocaleString()}</div>
                                                <div className="text-sm font-semibold text-gray-300">{percentage}%</div>
                                            </div>
                                        </div>
                                        <div className="w-full bg-slate-700/50 rounded-full h-3">
                                            <div 
                                                className="bg-gradient-to-r from-slate-500 to-slate-600 h-3 rounded-full transition-all duration-500" 
                                                style={{ width: `${percentage}%` }} 
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
