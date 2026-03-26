import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function ConstituencyBreakdowns({ auth, constituencies = [], stats = {} }) {
    const handleView = (name) => {
        router.visit(`/admin-area/constituencies/${encodeURIComponent(name)}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Constituency Breakdowns</h1>

                {/* Summary Cards */}
                {Object.keys(stats).length > 0 && (
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-slate-600/40 rounded-xl p-6 border border-slate-500/50">
                            <div className="text-gray-400 text-sm mb-2">Total Constituencies</div>
                            <div className="text-white font-bold text-3xl">{stats.total || 0}</div>
                        </div>
                        <div className="bg-slate-600/20 border border-slate-500/50 rounded-xl p-6">
                            <div className="text-slate-300 text-sm mb-2">Certified</div>
                            <div className="text-white font-bold text-3xl">{stats.certified || 0}</div>
                        </div>
                        <div className="bg-slate-600/20 border border-slate-500/50 rounded-xl p-6">
                            <div className="text-slate-300 text-sm mb-2">Pending</div>
                            <div className="text-white font-bold text-3xl">{stats.pending || 0}</div>
                        </div>
                        <div className="bg-slate-600/40 rounded-xl p-6 border border-slate-500/50">
                            <div className="text-gray-400 text-sm mb-2">Total Votes</div>
                            <div className="text-white font-bold text-3xl">{stats.totalVotes?.toLocaleString() || 0}</div>
                        </div>
                    </div>
                )}

                {/* Constituency Table */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {constituencies.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="text-left text-gray-400 py-3">Constituency</th>
                                        <th className="text-right text-gray-400 py-3">Wards</th>
                                        <th className="text-right text-gray-400 py-3">Stations</th>
                                        <th className="text-right text-gray-400 py-3">Total Votes</th>
                                        <th className="text-right text-gray-400 py-3">Turnout</th>
                                        <th className="text-center text-gray-400 py-3">Status</th>
                                        <th className="text-center text-gray-400 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {constituencies.map((c, i) => (
                                        <tr key={i} className="border-b border-slate-700/50">
                                            <td className="py-4 text-white font-semibold">{c.name}</td>
                                            <td className="py-4 text-right text-white">{c.wards}</td>
                                            <td className="py-4 text-right text-white">{c.stations}</td>
                                            <td className="py-4 text-right text-white">{c.votes?.toLocaleString()}</td>
                                            <td className="py-4 text-right text-white">{c.turnout}%</td>
                                            <td className="py-4 text-center">
                                                <span className={`px-3 py-1 rounded-full text-sm ${
                                                    c.status === 'Certified'
                                                        ? 'bg-slate-500/20 text-slate-300'
                                                        : 'bg-slate-500/20 text-slate-300'
                                                }`}>
                                                    {c.status}
                                                </span>
                                            </td>
                                            <td className="py-4 text-center">
                                                <button
                                                    onClick={() => handleView(c.name)}
                                                    className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm"
                                                >
                                                    View Details
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-gray-400 text-center py-8">No constituency data available</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
