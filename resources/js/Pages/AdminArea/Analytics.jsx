import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function AdminAreaAnalytics({ auth, adminArea, stats = {}, constituencies = [] }) {
    const certificationRate = stats.totalConstituencies > 0
        ? Math.round((stats.certified / stats.totalConstituencies) * 100)
        : 0;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/admin-area/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                        Admin-Area Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Admin-Area Analytics</h1>
                    {adminArea?.name && <p className="text-iec-pink-600 mt-1">{adminArea.name}</p>}
                    <p className="text-slate-500 text-sm mt-1">
                        Statistical overview across all constituencies in your area
                    </p>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                    {[
                        { label: 'Constituencies', value: stats.totalConstituencies || 0,               color: 'text-iec-navy'       },
                        { label: 'Certified',       value: stats.certified          || 0,               color: 'text-iec-pink-600'    },
                        { label: 'Total Wards',     value: stats.totalWards         || 0,               color: 'text-iec-pink-600'    },
                        { label: 'Total Votes',     value: (stats.totalVotes || 0).toLocaleString(),    color: 'text-iec-navy'       },
                        { label: 'Avg Turnout',     value: `${stats.avgTurnout || 0}%`,                 color: 'text-amber-300'   },
                        { label: 'Cert. Rate',      value: `${certificationRate}%`,                     color: 'text-iec-pink-600'  },
                    ].map((card, i) => (
                        <div key={i} className="bg-white rounded-xl p-5 border border-slate-200">
                            <div className={`text-2xl font-bold mb-1 ${card.color}`}>{card.value}</div>
                            <div className="text-slate-500 text-xs">{card.label}</div>
                        </div>
                    ))}
                </div>

                {/* Certification Progress */}
                {stats.totalConstituencies > 0 && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200 mb-6">
                        <div className="flex justify-between items-center mb-3">
                            <h2 className="text-lg font-bold text-iec-navy">Admin-Area Certification Progress</h2>
                            <span className="text-iec-navy font-bold text-xl">{certificationRate}%</span>
                        </div>
                        <div className="w-full bg-white rounded-full h-5">
                            <div
                                className="bg-gradient-to-r from-teal-600 to-teal-400 h-5 rounded-full transition-all"
                                style={{ width: `${certificationRate}%` }}
                            />
                        </div>
                        <div className="flex justify-between text-xs text-slate-500 mt-2">
                            <span>{stats.certified} constituencies certified</span>
                            <span>{(stats.totalConstituencies || 0) - (stats.certified || 0)} remaining</span>
                        </div>
                    </div>
                )}

                {/* Turnout Summary */}
                {(stats.highestTurnout > 0 || stats.lowestTurnout > 0) && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200 mb-6">
                        <h2 className="text-lg font-bold text-iec-navy mb-4">Turnout Analysis</h2>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="bg-white p-4 rounded-lg text-center">
                                <div className="text-slate-500 text-sm mb-1">Average Turnout</div>
                                <div className="text-iec-navy font-bold text-3xl">{stats.avgTurnout}%</div>
                            </div>
                            <div className="bg-white p-4 rounded-lg text-center">
                                <div className="text-slate-500 text-sm mb-1">Highest Turnout</div>
                                <div className="text-iec-pink-600 font-bold text-3xl">{stats.highestTurnout}%</div>
                            </div>
                            <div className="bg-white p-4 rounded-lg text-center">
                                <div className="text-slate-500 text-sm mb-1">Lowest Turnout</div>
                                <div className="text-amber-300 font-bold text-3xl">{stats.lowestTurnout}%</div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Constituency Breakdown Chart */}
                {constituencies.length > 0 && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200">
                        <h2 className="text-lg font-bold text-iec-navy mb-5">Certification Progress by Constituency</h2>
                        <div className="space-y-4">
                            {constituencies.map((constituency, i) => (
                                <div key={i}>
                                    <div className="flex justify-between items-center mb-1">
                                        <span className="text-slate-600 text-sm font-medium">{constituency.name}</span>
                                        <div className="flex items-center gap-3 text-sm">
                                            <span className="text-slate-500">{(constituency.votes || 0).toLocaleString()} votes</span>
                                            <span className="text-slate-500">{constituency.turnout}% turnout</span>
                                            <span className="text-iec-navy font-bold w-10 text-right">{constituency.progress}%</span>
                                        </div>
                                    </div>
                                    <div className="w-full bg-white rounded-full h-3">
                                        <div
                                            className={`h-3 rounded-full transition-all ${
                                                constituency.progress === 100
                                                    ? 'bg-gradient-to-r from-teal-600 to-teal-400'
                                                    : 'bg-gradient-to-r from-amber-600 to-amber-400'
                                            }`}
                                            style={{ width: `${constituency.progress || 0}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {constituencies.length === 0 && (
                    <div className="bg-white rounded-xl p-12 border border-slate-200 text-center">
                        <p className="text-slate-500">No constituency analytics available yet.</p>
                        <p className="text-slate-500 text-sm mt-1">Data will appear once results are submitted and certified.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}