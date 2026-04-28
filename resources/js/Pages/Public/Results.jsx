import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';

function ElectionSelector({ elections = [], selectedElectionId }) {
    if (elections.length <= 1) return null;
    return (
        <div className="flex flex-wrap justify-center gap-2 mb-6">
            <span className="text-gray-500 text-xs self-center mr-1">Election:</span>
            {elections.map(e => (
                <button
                    key={e.id}
                    onClick={() => router.get('/results', { election: e.id }, { preserveScroll: false })}
                    className={`px-4 py-1.5 rounded-lg text-sm font-semibold transition-all ${
                        selectedElectionId === e.id
                            ? 'bg-pink-600 text-white shadow-lg'
                            : 'bg-slate-800/40 text-gray-300 hover:bg-slate-700 border border-slate-700/50'
                    }`}
                >
                    {e.name}
                </button>
            ))}
        </div>
    );
}

function ResultsNav({ active = 'summary', electionId }) {
    const param = electionId ? `?election=${electionId}` : '';
    const tabs = [
        { key: 'summary',  label: 'Summary',  href: `/results${param}` },
        { key: 'map',      label: 'Map',       href: `/results/map${param}` },
        { key: 'stations', label: 'Stations',  href: `/results/stations${param}` },
    ];
    return (
        <div className="flex justify-center gap-3 flex-wrap">
            {tabs.map(t => (
                <Link
                    key={t.key}
                    href={t.href}
                    prefetch
                    className={`px-6 py-2.5 rounded-lg font-semibold text-sm transition-all ${
                        active === t.key
                            ? 'bg-slate-700 text-white shadow-lg'
                            : 'bg-slate-800/30 text-gray-300 hover:bg-slate-700 hover:text-white'
                    }`}
                >
                    {t.label}
                </Link>
            ))}
        </div>
    );
}

function HeroBanner() {
    return (
        <div className="py-8 text-center">
            <div className="container mx-auto px-4">
                <h2 className="text-2xl font-bold text-white mb-1">Election Results</h2>
                <p className="text-gray-400 text-sm">
                    Independent Electoral Commission of The Gambia — Official Results Portal
                </p>
            </div>
        </div>
    );
}

export default function Results({ election, elections = [], selectedElectionId, stats, candidates, message }) {
    useInertiaPrefetch(['/results/map', '/results/stations']);

    if (!election) {
        return (
            <AppLayout>
                <HeroBanner />
                <div className="container mx-auto px-4 pb-16">
                    <ElectionSelector elections={elections} selectedElectionId={selectedElectionId} />
                    <div className="max-w-2xl mx-auto text-center p-10 bg-slate-800/40 rounded-xl border border-pink-700/50">
                        <div className="text-5xl mb-4">🗳️</div>
                        <h3 className="text-2xl font-bold text-white mb-3">No Active Election</h3>
                        <p className="text-gray-400">
                            There is no active election at this time. Results will appear here once an election
                            has been configured and certified by the IEC.
                        </p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (!stats || !candidates || candidates.length === 0) {
        return (
            <AppLayout>
                <div className="bg-slate-800/50 border-b border-slate-700/50 py-6">
                    <div className="container mx-auto px-4 text-center">
                        <h1 className="text-3xl font-bold text-white mb-3">{election.name}</h1>
                        <ElectionSelector elections={elections} selectedElectionId={selectedElectionId} />
                        <ResultsNav active="summary" electionId={selectedElectionId} />
                    </div>
                </div>
                <HeroBanner />
                <div className="container mx-auto px-4 pb-16">
                    <div className="max-w-2xl mx-auto text-center p-10 bg-slate-800/40 rounded-xl border border-amber-500/20">
                        <div className="text-5xl mb-4">⏳</div>
                        <h3 className="text-2xl font-bold text-white mb-3">Results Pending Publication</h3>
                        <p className="text-gray-400 leading-relaxed">
                            {message || 'Election results are currently being certified through the IEC approval pipeline.'}
                        </p>
                        <div className="mt-6 flex justify-center gap-4">
                            <Link href={`/results/stations${selectedElectionId ? `?election=${selectedElectionId}` : ''}`} prefetch
                                  className="px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-semibold text-sm transition-colors">
                                View Station Status →
                            </Link>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const turnout = stats?.total_registered > 0
        ? ((stats.total_cast / stats.total_registered) * 100).toFixed(1)
        : 0;

    const totalValidVotes = stats?.valid_votes || 0;

    return (
        <AppLayout>
            <div className="bg-slate-800/50 border-b border-slate-700/50 py-6">
                <div className="container mx-auto px-4 text-center">
                    <h1 className="text-3xl font-bold text-white mb-3">{election.name}</h1>
                    <ElectionSelector elections={elections} selectedElectionId={selectedElectionId} />
                    <ResultsNav active="summary" electionId={selectedElectionId} />
                </div>
            </div>

            <div className="container mx-auto px-4 py-8">
                <div className="max-w-7xl mx-auto">

                    <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                        {[
                            { label: 'Registered Voters', value: parseInt(stats?.total_registered || 0).toLocaleString(), color: 'text-white' },
                            { label: 'Votes Cast',         value: parseInt(stats?.total_cast || 0).toLocaleString(),        color: 'text-white' },
                            { label: 'Valid Votes',        value: parseInt(stats?.valid_votes || 0).toLocaleString(),       color: 'text-teal-300' },
                            { label: 'Rejected Votes',     value: parseInt(stats?.rejected_votes || 0).toLocaleString(),   color: 'text-amber-300' },
                            { label: 'Turnout',            value: `${turnout}%`,                                            color: 'text-blue-300' },
                        ].map(stat => (
                            <div key={stat.label} className="bg-slate-800/60 rounded-xl p-5 border border-slate-700/50 text-center">
                                <div className={`text-3xl font-bold mb-1 ${stat.color}`}>{stat.value}</div>
                                <div className="text-gray-400 text-xs uppercase tracking-wide">{stat.label}</div>
                            </div>
                        ))}
                    </div>

                    <div className="bg-slate-800/60 rounded-xl p-6 border border-slate-700/50 mb-8">
                        <div className="flex justify-between items-center mb-3">
                            <div>
                                <div className="text-sm text-gray-400 uppercase tracking-wide mb-0.5">Stations Reporting</div>
                                <div className="text-2xl font-bold text-white">
                                    {parseInt(stats?.stations_reported || 0)} / {parseInt(stats?.total_stations || 0)}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-sm text-gray-400 uppercase tracking-wide mb-0.5">Progress</div>
                                <div className="text-2xl font-bold text-white">
                                    {stats?.total_stations > 0
                                        ? Math.round((stats.stations_reported / stats.total_stations) * 100)
                                        : 0}%
                                </div>
                            </div>
                        </div>
                        <div className="w-full bg-slate-700/50 rounded-full h-4">
                            <div
                                className="bg-gradient-to-r from-teal-500 to-teal-600 h-4 rounded-full transition-all duration-500"
                                style={{
                                    width: `${stats?.total_stations > 0
                                        ? (stats.stations_reported / stats.total_stations) * 100
                                        : 0}%`
                                }}
                            />
                        </div>
                    </div>

                    <div className="bg-slate-800/60 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Candidate Results</h2>
                        <div className="space-y-4">
                            {(candidates || []).map((candidate, index) => {
                                const percentage   = totalValidVotes > 0
                                    ? ((candidate.total_votes / totalValidVotes) * 100).toFixed(2)
                                    : 0;
                                const primaryColor = candidate.party_color?.split(',')[0] || '#6b7280';
                                const isLeading    = index === 0;

                                return (
                                    <div
                                        key={candidate.id}
                                        className={`rounded-xl p-5 border transition-colors ${
                                            isLeading
                                                ? 'bg-teal-900/20 border-teal-500/30'
                                                : 'bg-slate-900/40 border-slate-700/30'
                                        }`}
                                    >
                                        <div className="flex justify-between items-start mb-3 flex-wrap gap-2">
                                            <div className="flex items-center gap-3">
                                                {isLeading && <span className="text-xl">🏆</span>}
                                                <div className="w-3 h-3 rounded-full flex-shrink-0 mt-0.5"
                                                     style={{ backgroundColor: primaryColor }} />
                                                <div>
                                                    <div className="font-bold text-white text-lg">{candidate.name}</div>
                                                    <div className="text-sm text-gray-400">
                                                        {candidate.party_abbr} — {candidate.party_name}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-2xl font-bold text-white">
                                                    {parseInt(candidate.total_votes || 0).toLocaleString()}
                                                </div>
                                                <div className="text-sm font-semibold text-gray-300">{percentage}%</div>
                                            </div>
                                        </div>
                                        <div className="w-full bg-slate-700/50 rounded-full h-3">
                                            <div
                                                className="h-3 rounded-full transition-all duration-700"
                                                style={{ width: `${percentage}%`, backgroundColor: primaryColor }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="mt-6 flex flex-wrap gap-3 justify-center">
                        <Link href={`/results/map${selectedElectionId ? `?election=${selectedElectionId}` : ''}`} prefetch
                              className="px-5 py-2.5 bg-slate-800/60 hover:bg-slate-700 border border-slate-700/50 text-gray-300 hover:text-white rounded-lg font-semibold text-sm transition-colors">
                            🗺 View Map
                        </Link>
                        <Link href={`/results/stations${selectedElectionId ? `?election=${selectedElectionId}` : ''}`} prefetch
                              className="px-5 py-2.5 bg-slate-800/60 hover:bg-slate-700 border border-slate-700/50 text-gray-300 hover:text-white rounded-lg font-semibold text-sm transition-colors">
                            📋 View All Stations
                        </Link>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}