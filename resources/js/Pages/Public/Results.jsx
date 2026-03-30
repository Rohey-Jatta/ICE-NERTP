import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function Results({ election, stats, candidates }) {
    if (!election) {
        return (
            <AppLayout>
                <div className="container mx-auto px-4 py-12 min-h-screen flex items-center justify-center">
                    <div className="text-center p-12 bg-slate-800/40 rounded-xl border border-pink-300/50 max-w-2xl">
                        <h1 className="text-3xl font-bold text-white mb-4">No Results Available</h1>
                        <p className="text-pink-200 mb-6">Results will be published once voting concludes.</p>
                        <Link href="/" className="inline-block px-6 py-3 bg-pink-600 text-white rounded-lg font-semibold hover:bg-pink-700 transition-colors">
                            Back Home
                        </Link>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const turnout = stats?.total_registered > 0
        ? ((stats.total_cast / stats.total_registered) * 100).toFixed(1)
        : 0;

    return (
        <AppLayout>
            <div className="container mx-auto px-4 py-8">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center mb-8">
                        <h1 className="text-4xl font-bold text-white mb-6">{election.name}</h1>

                        {/* Navigation Tabs — Link for instant SPA navigation */}
                        <div className="flex justify-center gap-4 mb-8 flex-wrap">
                            <Link href="/results" className="px-6 py-3 bg-slate-700 text-white rounded-lg font-semibold shadow-lg hover:bg-slate-600 transition-colors">
                                Summary
                            </Link>
                            <Link href="/results/map" className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all">
                                Map
                            </Link>
                            <Link href="/results/stations" className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all">
                                Stations
                            </Link>
                        </div>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                        {[
                            { label: 'Registered Voters', value: parseInt(stats?.total_registered || 0).toLocaleString(), color: 'text-white' },
                            { label: 'Votes Cast', value: parseInt(stats?.total_cast || 0).toLocaleString(), color: 'text-white' },
                            { label: 'Valid Votes', value: parseInt(stats?.valid_votes || 0).toLocaleString(), color: 'text-teal-300' },
                            { label: 'Rejected Votes', value: parseInt(stats?.rejected_votes || 0).toLocaleString(), color: 'text-amber-300' },
                            { label: 'Turnout', value: `${turnout}%`, color: 'text-white' },
                        ].map((stat) => (
                            <div key={stat.label} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className={`text-3xl font-bold mb-2 ${stat.color}`}>{stat.value}</div>
                                <div className="text-gray-400 text-sm">{stat.label}</div>
                            </div>
                        ))}
                    </div>

                    {/* Progress Bar */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-8">
                        <div className="flex justify-between items-center mb-3">
                            <div>
                                <div className="text-sm text-gray-400">Stations Reporting</div>
                                <div className="text-2xl font-bold text-white">
                                    {parseInt(stats?.stations_reported || 0)} / {parseInt(stats?.total_stations || 0)}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-sm text-gray-400">Progress</div>
                                <div className="text-2xl font-bold text-white">
                                    {stats?.total_stations > 0 ? Math.round((stats.stations_reported / stats.total_stations) * 100) : 0}%
                                </div>
                            </div>
                        </div>
                        <div className="w-full bg-slate-700/50 rounded-full h-4">
                            <div
                                className="bg-gradient-to-r from-teal-500 to-teal-600 h-4 rounded-full transition-all duration-500"
                                style={{ width: `${stats?.total_stations > 0 ? (stats.stations_reported / stats.total_stations) * 100 : 0}%` }}
                            />
                        </div>
                    </div>

                    {/* Candidates */}
                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Candidate Results</h2>
                        <div className="space-y-4">
                            {(candidates || []).map((candidate, index) => {
                                const percentage = stats?.valid_votes > 0
                                    ? ((candidate.total_votes / stats.valid_votes) * 100).toFixed(2)
                                    : 0;
                                return (
                                    <div key={candidate.id} className="bg-slate-900/50 rounded-lg p-4 border border-slate-700/30">
                                        <div className="flex justify-between mb-3">
                                            <div className="flex items-center gap-3">
                                                {index === 0 && <span className="text-2xl">🏆</span>}
                                                <div>
                                                    <div className="font-bold text-white text-lg">{candidate.name}</div>
                                                    <div className="text-sm text-gray-400">{candidate.party_abbr}</div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-xl font-bold text-white">{parseInt(candidate.total_votes || 0).toLocaleString()}</div>
                                                <div className="text-sm font-semibold text-gray-300">{percentage}%</div>
                                            </div>
                                        </div>
                                        <div className="w-full bg-slate-700/50 rounded-full h-3">
                                            <div
                                                className="bg-gradient-to-r from-slate-500 to-slate-600 h-3 rounded-full"
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