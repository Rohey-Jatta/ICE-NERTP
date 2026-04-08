import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function ChairmanAnalytics({ auth, nationalStats = {}, regionalBreakdown = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                <div className="mb-6">
                    <Link href="/chairman/dashboard"
                          className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        Chairman Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">National Analytics</h1>
                    <p className="text-gray-400 mt-1 text-sm">Full analytics — certified results only</p>
                </div>

                {/* National Summary */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    {[
                        { label: 'Total Polling Stations',   value: (nationalStats.totalStations || 0).toLocaleString(),    color: 'text-white' },
                        { label: 'Registered Voters',        value: (nationalStats.registeredVoters || 0).toLocaleString(),  color: 'text-white' },
                        { label: 'Certified Votes Cast',     value: (nationalStats.votesCast || 0).toLocaleString(),         color: 'text-teal-300' },
                        { label: 'National Turnout',         value: `${nationalStats.turnout || 0}%`,                        color: 'text-blue-300' },
                        { label: 'Stations Certified',       value: `${nationalStats.certifiedPercentage || 0}%`,             color: 'text-green-300' },
                    ].map((card) => (
                        <div key={card.label} className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className={`text-2xl font-bold mb-1 ${card.color}`}>{card.value}</div>
                            <div className="text-gray-400 text-xs">{card.label}</div>
                        </div>
                    ))}
                </div>

                {/* Party Performance */}
                {nationalStats.partyPerformance?.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                        <h2 className="text-xl font-bold text-white mb-5">National Party Performance (Certified Results)</h2>
                        <div className="space-y-3">
                            {nationalStats.partyPerformance.map((party, i) => (
                                <div key={i} className={`p-4 rounded-xl border ${
                                    i === 0 ? 'bg-teal-900/20 border-teal-500/30' : 'bg-slate-900/40 border-slate-700/30'
                                }`}>
                                    <div className="flex justify-between items-center mb-2">
                                        <div className="flex items-center gap-3">
                                            <div className="w-3 h-3 rounded-full flex-shrink-0"
                                                 style={{ backgroundColor: party.color?.split(',')[0] || '#6b7280' }} />
                                            <span className="text-white font-bold">{party.name}</span>
                                            {i === 0 && <span className="text-teal-400 text-xs">🏆 Leading</span>}
                                        </div>
                                        <div className="text-right">
                                            <span className="text-white font-bold text-lg">{party.percentage}%</span>
                                            <div className="text-gray-400 text-xs">{party.votes?.toLocaleString()} votes</div>
                                        </div>
                                    </div>
                                    <div className="w-full bg-slate-700 rounded-full h-2.5">
                                        <div
                                            className="h-2.5 rounded-full transition-all"
                                            style={{
                                                width: `${party.percentage}%`,
                                                backgroundColor: party.color?.split(',')[0] || '#6b7280',
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Regional Breakdown */}
                {regionalBreakdown.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-xl font-bold text-white mb-5">Regional Certification Progress</h2>
                        <div className="space-y-3">
                            {regionalBreakdown.map((region, i) => (
                                <div key={i} className="bg-slate-900/50 p-4 rounded-xl">
                                    <div className="flex justify-between mb-2">
                                        <span className="text-white font-semibold">{region.name}</span>
                                        <div className="text-right text-xs">
                                            <span className="text-gray-400">{region.votes?.toLocaleString()} votes &bull; </span>
                                            <span className={region.progress === 100 ? 'text-green-400' : 'text-amber-400'}>
                                                {region.certified}/{region.total} stations
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="flex-1 bg-slate-700 rounded-full h-3">
                                            <div
                                                className={`h-3 rounded-full transition-all ${
                                                    region.progress === 100
                                                        ? 'bg-gradient-to-r from-teal-500 to-green-400'
                                                        : 'bg-gradient-to-r from-blue-600 to-teal-500'
                                                }`}
                                                style={{ width: `${region.progress}%` }}
                                            />
                                        </div>
                                        <span className="text-white font-bold text-sm w-12 text-right">{region.progress}%</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {!nationalStats.partyPerformance?.length && !regionalBreakdown.length && (
                    <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                        <p className="text-gray-400">No certified results available for analytics yet.</p>
                        <p className="text-gray-500 text-sm mt-1">Analytics will populate as results are nationally certified.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}