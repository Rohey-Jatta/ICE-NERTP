import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function WardBreakdowns({ auth, constituency, wards = [] }) {
    const totalStations  = wards.reduce((s, w) => s + w.stations, 0);
    const totalCertified = wards.reduce((s, w) => s + w.certified, 0);
    const totalVotes     = wards.reduce((s, w) => s + w.votes, 0);
    const overallProgress = totalStations > 0 ? Math.round((totalCertified / totalStations) * 100) : 0;

    const statusColors = {
        'Fully Certified':     'bg-teal-500/20 text-teal-300 border-teal-500/30',
        'Partially Certified': 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        'Pending':             'bg-slate-500/20 text-slate-300 border-slate-500/30',
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header with back link */}
                <div className="mb-6">
                    <Link href="/constituency/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        Back to Constituency Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Ward Breakdowns</h1>
                    {constituency?.name && <p className="text-teal-300 mt-1">{constituency.name}</p>}
                </div>

                {/* Overall summary */}
                {wards.length > 0 && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-white">{wards.length}</div>
                            <div className="text-gray-400 text-sm mt-1">Total Wards</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-teal-300">{totalCertified}</div>
                            <div className="text-gray-400 text-sm mt-1">Stations Certified</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-white">{totalVotes.toLocaleString()}</div>
                            <div className="text-gray-400 text-sm mt-1">Total Votes</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-blue-300">{overallProgress}%</div>
                            <div className="text-gray-400 text-sm mt-1">Overall Progress</div>
                        </div>
                    </div>
                )}

                {/* Overall progress bar */}
                {overallProgress > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                        <div className="flex justify-between items-center mb-3">
                            <span className="text-gray-300 font-semibold">Constituency Certification Progress</span>
                            <span className="text-white font-bold">{overallProgress}%</span>
                        </div>
                        <div className="w-full bg-slate-700 rounded-full h-4">
                            <div
                                className="bg-gradient-to-r from-teal-500 to-teal-400 h-4 rounded-full transition-all"
                                style={{ width: `${overallProgress}%` }}
                            />
                        </div>
                    </div>
                )}

                {/* Ward table */}
                <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
                    {wards.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-slate-900/50">
                                    <tr>
                                        <th className="text-left text-gray-400 py-4 px-5">Ward</th>
                                        <th className="text-right text-gray-400 py-4 px-4">Stations</th>
                                        <th className="text-right text-gray-400 py-4 px-4">Certified</th>
                                        <th className="text-right text-gray-400 py-4 px-4">Total Votes</th>
                                        <th className="text-right text-gray-400 py-4 px-4">Turnout</th>
                                        <th className="text-left text-gray-400 py-4 px-4">Progress</th>
                                        <th className="text-center text-gray-400 py-4 px-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-700/30">
                                    {wards.map((ward, i) => (
                                        <tr key={i} className="hover:bg-slate-700/20">
                                            <td className="py-4 px-5 text-white font-semibold">{ward.name}</td>
                                            <td className="py-4 px-4 text-right text-gray-300">{ward.stations}</td>
                                            <td className="py-4 px-4 text-right text-teal-300 font-semibold">{ward.certified}</td>
                                            <td className="py-4 px-4 text-right text-white">{ward.votes?.toLocaleString()}</td>
                                            <td className="py-4 px-4 text-right text-white">{ward.turnout}%</td>
                                            <td className="py-4 px-4">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex-1 bg-slate-700 rounded-full h-2 min-w-[60px]">
                                                        <div
                                                            className="bg-teal-500 h-2 rounded-full"
                                                            style={{ width: `${ward.progress}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-xs text-gray-400 w-8">{ward.progress}%</span>
                                                </div>
                                            </td>
                                            <td className="py-4 px-4 text-center">
                                                <span className={`px-3 py-1 rounded-full text-xs font-semibold border ${
                                                    statusColors[ward.status] || statusColors['Pending']
                                                }`}>
                                                    {ward.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-12 text-center">
                            <p className="text-gray-400">No ward data available for this constituency.</p>
                            <p className="text-gray-500 text-sm mt-1">Data will appear once polling stations submit results.</p>
                        </div>
                    )}
                </div>

                {/* Navigation */}
                <div className="mt-6 flex gap-4">
                    <Link href="/constituency/approval-queue" className="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                        Go to Approval Queue
                    </Link>
                    <Link href="/constituency/reports" className="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg">
                        Generate Reports
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
