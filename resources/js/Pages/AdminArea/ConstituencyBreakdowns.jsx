import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

const STATUS_CONFIG = {
    'Certified':       { color: 'bg-teal-500/20 text-teal-300',    dot: 'bg-teal-400'   },
    'Pending Review':  { color: 'bg-orange-500/20 text-orange-300', dot: 'bg-orange-400' },
    'In Pipeline':     { color: 'bg-blue-500/20 text-blue-300',     dot: 'bg-blue-400'   },
    'In Progress':     { color: 'bg-amber-500/20 text-amber-300',   dot: 'bg-amber-400'  },
    'No Results':      { color: 'bg-gray-500/20 text-gray-400',     dot: 'bg-gray-500'   },
};

export default function ConstituencyBreakdowns({ auth, adminArea, constituencies = [], stats = {} }) {
    const handleViewQueue = (id) => {
        router.visit(`/admin-area/approval-queue?constituency=${id}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/admin-area/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Admin-Area Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Constituency Breakdowns</h1>
                    {adminArea?.name && <p className="text-teal-300 mt-1">{adminArea.name}</p>}
                    <p className="text-gray-400 text-sm mt-1">
                        Full pipeline status across all constituencies in your administrative area
                    </p>
                </div>

                {/* Legend */}
                <div className="mb-5 flex flex-wrap gap-3">
                    {Object.entries(STATUS_CONFIG).map(([label, cfg]) => (
                        <span key={label} className={`inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold border ${cfg.color} border-current/20`}>
                            <span className={`w-2 h-2 rounded-full ${cfg.dot}`} />
                            {label}
                        </span>
                    ))}
                </div>

                {/* Summary Cards */}
                {Object.keys(stats).length > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-white">{stats.total || 0}</div>
                            <div className="text-gray-400 text-sm mt-1">Total Constituencies</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-teal-300">{stats.certified || 0}</div>
                            <div className="text-gray-400 text-sm mt-1">Fully Certified</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-blue-300">{stats.awaiting || 0}</div>
                            <div className="text-gray-400 text-sm mt-1">In Pipeline</div>
                            <div className="text-gray-500 text-xs">Ward/Constituency level</div>
                        </div>
                        <div className="bg-slate-800/40 rounded-xl p-5 border border-slate-700/50">
                            <div className="text-3xl font-bold text-white">{(stats.totalVotes || 0).toLocaleString()}</div>
                            <div className="text-gray-400 text-sm mt-1">Total Votes</div>
                        </div>
                    </div>
                )}

                {/* Constituency Table */}
                <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
                    {constituencies.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-slate-900/50">
                                    <tr>
                                        <th className="text-left text-gray-400 py-4 px-5 text-sm">Constituency</th>
                                        <th className="text-right text-gray-400 py-4 px-4 text-sm">Wards</th>
                                        <th className="text-right text-gray-400 py-4 px-4 text-sm">Stations</th>
                                        <th className="text-right text-gray-400 py-4 px-4 text-sm">Votes</th>
                                        <th className="text-right text-gray-400 py-4 px-4 text-sm">Turnout</th>
                                        <th className="text-center text-gray-400 py-4 px-4 text-sm">Area Certified</th>
                                        <th className="text-center text-gray-400 py-4 px-4 text-sm">Pending Review</th>
                                        <th className="text-center text-gray-400 py-4 px-4 text-sm">In Pipeline</th>
                                        <th className="text-center text-gray-400 py-4 px-4 text-sm">Status</th>
                                        <th className="text-center text-gray-400 py-4 px-4 text-sm">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {constituencies.map((c, i) => {
                                        const cfg = STATUS_CONFIG[c.status] || STATUS_CONFIG['In Progress'];
                                        return (
                                            <tr key={i} className="border-t border-slate-700/40 hover:bg-slate-700/20 transition-colors">
                                                <td className="py-4 px-5 text-white font-semibold">{c.name}</td>
                                                <td className="py-4 px-4 text-right text-gray-300">{c.wards}</td>
                                                <td className="py-4 px-4 text-right text-gray-300">{c.stations}</td>
                                                <td className="py-4 px-4 text-right text-white font-medium">{(c.votes || 0).toLocaleString()}</td>
                                                <td className="py-4 px-4 text-right text-white">{c.turnout}%</td>

                                                {/* Area Certified / total that reached admin-area */}
                                                <td className="py-4 px-4 text-center">
                                                    <span className={`text-sm font-semibold ${c.certified_count > 0 ? 'text-teal-300' : 'text-gray-500'}`}>
                                                        {c.certified_count}/{c.admin_level}
                                                    </span>
                                                </td>

                                                {/* Pending this approver's decision */}
                                                <td className="py-4 px-4 text-center">
                                                    <span className={`text-sm font-semibold ${c.pending_review > 0 ? 'text-orange-300' : 'text-gray-500'}`}>
                                                        {c.pending_review}
                                                    </span>
                                                </td>

                                                {/* Still at ward/constituency */}
                                                <td className="py-4 px-4 text-center">
                                                    <span className={`text-sm font-semibold ${c.in_pipeline > 0 ? 'text-blue-300' : 'text-gray-500'}`}>
                                                        {c.in_pipeline}
                                                    </span>
                                                </td>

                                                <td className="py-4 px-4 text-center">
                                                    <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ${cfg.color}`}>
                                                        <span className={`w-1.5 h-1.5 rounded-full ${cfg.dot}`} />
                                                        {c.status}
                                                    </span>
                                                </td>

                                                <td className="py-4 px-4 text-center">
                                                    {c.pending_review > 0 ? (
                                                        <button
                                                            onClick={() => handleViewQueue(c.id)}
                                                            className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm font-semibold transition-colors"
                                                        >
                                                            Review ({c.pending_review})
                                                        </button>
                                                    ) : (
                                                        <span className="px-4 py-2 text-gray-500 text-sm">
                                                            {c.in_pipeline > 0 ? 'Awaiting pipeline' : c.certified_count > 0 ? 'Done ✓' : '—'}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="p-12 text-center">
                            <p className="text-gray-400">No constituency data available.</p>
                            <p className="text-gray-500 text-sm mt-1">Configure the administrative hierarchy first.</p>
                        </div>
                    )}
                </div>

                {/* Pipeline explanation */}
                <div className="mt-4 p-4 bg-slate-900/40 rounded-xl border border-slate-700/30">
                    <p className="text-gray-500 text-xs leading-relaxed">
                        <strong className="text-gray-400">Column guide:</strong>
                        &nbsp;<strong className="text-teal-400">Area Certified</strong> — results certified by you (out of those that reached this level) &nbsp;·&nbsp;
                        <strong className="text-orange-400">Pending Review</strong> — ready and waiting for your decision &nbsp;·&nbsp;
                        <strong className="text-blue-400">In Pipeline</strong> — still being certified at ward/constituency level below you
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}