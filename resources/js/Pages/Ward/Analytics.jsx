import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function WardAnalytics({ auth, ward, stats = {}, stationBreakdown = [] }) {
    const certifiedPct = stats.totalStations > 0
        ? Math.round((stats.certified / stats.totalStations) * 100)
        : 0;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/ward/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Ward Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Ward Analytics</h1>
                    {ward?.name && <p className="text-teal-300 mt-1">{ward.name}</p>}
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                    {[
                        { label: 'Total Stations', value: stats.totalStations || 0, color: 'text-white' },
                        { label: 'Certified',       value: stats.certified     || 0, color: 'text-teal-300' },
                        { label: 'Pending',         value: stats.pending       || 0, color: 'text-amber-300' },
                        { label: 'Rejected',        value: stats.rejected      || 0, color: 'text-red-300' },
                        { label: 'Total Votes',     value: (stats.totalVotes || 0).toLocaleString(), color: 'text-white' },
                        { label: 'Turnout Rate',    value: `${stats.turnoutRate || 0}%`, color: 'text-blue-300' },
                    ].map((card, i) => (
                        <div key={i} className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className={`text-2xl font-bold mb-1 ${card.color}`}>{card.value}</div>
                            <div className="text-gray-400 text-xs">{card.label}</div>
                        </div>
                    ))}
                </div>

                {/* Certification Progress */}
                {stats.totalStations > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                        <div className="flex justify-between items-center mb-3">
                            <h2 className="text-lg font-bold text-white">Certification Progress</h2>
                            <span className="text-white font-bold text-xl">{certifiedPct}%</span>
                        </div>
                        <div className="w-full bg-slate-700 rounded-full h-5">
                            <div
                                className="bg-gradient-to-r from-teal-600 to-teal-400 h-5 rounded-full transition-all"
                                style={{ width: `${certifiedPct}%` }}
                            />
                        </div>
                        <div className="flex justify-between text-xs text-gray-500 mt-2">
                            <span>{stats.certified} certified</span>
                            <span>{stats.totalStations - (stats.certified || 0)} remaining</span>
                        </div>
                    </div>
                )}

                {/* Station Breakdown Table */}
                {stationBreakdown.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-lg font-bold text-white mb-5">Station Breakdown</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="text-left text-gray-400 py-3 pr-4">Station</th>
                                        <th className="text-left text-gray-400 py-3 pr-4">Code</th>
                                        <th className="text-right text-gray-400 py-3 pr-4">Registered</th>
                                        <th className="text-right text-gray-400 py-3 pr-4">Votes</th>
                                        <th className="text-right text-gray-400 py-3 pr-4">Turnout</th>
                                        <th className="text-center text-gray-400 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stationBreakdown.map((station, i) => {
                                        const statusColors = {
                                            'Certified':    'bg-teal-500/20 text-teal-300',
                                            'Pending':      'bg-amber-500/20 text-amber-300',
                                            'Submitted':    'bg-amber-500/20 text-amber-300',
                                            'Rejected':     'bg-red-500/20 text-red-300',
                                            'Not Reported': 'bg-gray-500/20 text-gray-400',
                                        };
                                        return (
                                            <tr key={i} className="border-b border-slate-700/30 hover:bg-slate-700/20">
                                                <td className="py-3 pr-4 text-white font-medium">{station.name}</td>
                                                <td className="py-3 pr-4 text-gray-400 font-mono text-xs">{station.code}</td>
                                                <td className="py-3 pr-4 text-right text-gray-300">{station.voters?.toLocaleString()}</td>
                                                <td className="py-3 pr-4 text-right text-white">{station.votes?.toLocaleString()}</td>
                                                <td className="py-3 pr-4 text-right text-white">{station.turnout}%</td>
                                                <td className="py-3 text-center">
                                                    <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                                                        statusColors[station.status] || 'bg-gray-500/20 text-gray-400'
                                                    }`}>
                                                        {station.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {stationBreakdown.length === 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                        <p className="text-gray-400">No station data available yet.</p>
                        <p className="text-gray-500 text-sm mt-1">Data will appear once results are submitted.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
