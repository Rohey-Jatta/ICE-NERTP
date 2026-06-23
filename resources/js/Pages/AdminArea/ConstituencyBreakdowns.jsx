import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

const STATUS_CONFIG = {
    'Certified':       { color: 'bg-iec-pink-500/20 text-iec-pink-600',    dot: 'bg-teal-400'   },
    'Pending Review':  { color: 'bg-orange-500/20 text-orange-600', dot: 'bg-orange-400' },
    'In Pipeline':     { color: 'bg-iec-pink-500/20 text-iec-pink-600',     dot: 'bg-blue-400'   },
    'In Progress':     { color: 'bg-amber-500/20 text-amber-600',   dot: 'bg-amber-400'  },
    'No Results':      { color: 'bg-slate-100 text-slate-600',     dot: 'bg-gray-500'   },
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
                    <Link href="/admin-area/dashboard" className="text-slate-600 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                        Admin-Area Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Constituency Breakdowns</h1>
                    {adminArea?.name && <p className="text-iec-pink-600 mt-1">{adminArea.name}</p>}
                    <p className="text-slate-600 text-sm mt-1">
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
                        <div className="bg-white rounded-xl p-5 border border-slate-200">
                            <div className="text-3xl font-bold text-iec-navy">{stats.total || 0}</div>
                            <div className="text-slate-600 text-sm mt-1">Total Constituencies</div>
                        </div>
                        <div className="bg-white rounded-xl p-5 border border-slate-200">
                            <div className="text-3xl font-bold text-iec-pink-600">{stats.certified || 0}</div>
                            <div className="text-slate-600 text-sm mt-1">Fully Certified</div>
                        </div>
                        <div className="bg-white rounded-xl p-5 border border-slate-200">
                            <div className="text-3xl font-bold text-iec-pink-600">{stats.awaiting || 0}</div>
                            <div className="text-slate-500 text-sm mt-1">In Pipeline</div>
                            <div className="text-slate-500 text-xs">Ward/Constituency level</div>
                        </div>
                        <div className="bg-white rounded-xl p-5 border border-slate-200">
                            <div className="text-3xl font-bold text-iec-navy">{(stats.totalVotes || 0).toLocaleString()}</div>
                            <div className="text-slate-500 text-sm mt-1">Total Votes</div>
                        </div>
                    </div>
                )}

                {/* Constituency Table */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    {constituencies.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-white">
                                    <tr>
                                        <th className="text-left text-slate-600 py-4 px-5 text-sm">Constituency</th>
                                        <th className="text-right text-slate-600 py-4 px-4 text-sm">Wards</th>
                                        <th className="text-right text-slate-600 py-4 px-4 text-sm">Stations</th>
                                        <th className="text-right text-slate-600 py-4 px-4 text-sm">Votes</th>
                                        <th className="text-right text-slate-600 py-4 px-4 text-sm">Turnout</th>
                                        <th className="text-center text-slate-600 py-4 px-4 text-sm">Area Certified</th>
                                        <th className="text-center text-slate-600 py-4 px-4 text-sm">Pending Review</th>
                                        <th className="text-center text-slate-600 py-4 px-4 text-sm">In Pipeline</th>
                                        <th className="text-center text-slate-600 py-4 px-4 text-sm">Status</th>
                                        <th className="text-center text-slate-600 py-4 px-4 text-sm">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {constituencies.map((c, i) => {
                                        const cfg = STATUS_CONFIG[c.status] || STATUS_CONFIG['In Progress'];
                                        return (
                                            <tr key={i} className="border-t border-slate-200 hover:bg-slate-100 transition-colors">
                                                <td className="py-4 px-5 text-iec-navy font-semibold">{c.name}</td>
                                                <td className="py-4 px-4 text-right text-slate-600">{c.wards}</td>
                                                <td className="py-4 px-4 text-right text-slate-600">{c.stations}</td>
                                                <td className="py-4 px-4 text-right text-iec-navy font-medium">{(c.votes || 0).toLocaleString()}</td>
                                                <td className="py-4 px-4 text-right text-iec-navy">{c.turnout}%</td>

                                                {/* Area Certified / total that reached admin-area */}
                                                <td className="py-4 px-4 text-center">
                                                    <span className={`text-sm font-semibold ${c.certified_count > 0 ? 'text-iec-pink-600' : 'text-slate-600'}`}>
                                                        {c.certified_count}/{c.admin_level}
                                                    </span>
                                                </td>

                                                {/* Pending this approver's decision */}
                                                <td className="py-4 px-4 text-center">
                                                    <span className={`text-sm font-semibold ${c.pending_review > 0 ? 'text-orange-600' : 'text-slate-600'}`}>
                                                        {c.pending_review}
                                                    </span>
                                                </td>

                                                {/* Still at ward/constituency */}
                                                <td className="py-4 px-4 text-center">
                                                    <span className={`text-sm font-semibold ${c.in_pipeline > 0 ? 'text-iec-pink-600' : 'text-slate-600'}`}>
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
                                                            className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-iec-navy rounded-lg text-sm font-semibold transition-colors"
                                                        >
                                                            Review ({c.pending_review})
                                                        </button>
                                                    ) : (
                                                        <span className="px-4 py-2 text-slate-600 text-sm">
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
                            <p className="text-slate-600">No constituency data available.</p>
                            <p className="text-slate-600 text-sm mt-1">Configure the administrative hierarchy first.</p>
                        </div>
                    )}
                </div>

                {/* Pipeline explanation */}
                <div className="mt-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <p className="text-slate-600 text-xs leading-relaxed">
                        <strong className="text-slate-600">Column guide:</strong>
                        &nbsp;<strong className="text-iec-pink-600">Area Certified</strong> — results certified by you (out of those that reached this level) &nbsp;·&nbsp;
                        <strong className="text-orange-600">Pending Review</strong> — ready and waiting for your decision &nbsp;·&nbsp;
                        <strong className="text-iec-pink-600">In Pipeline</strong> — still being certified at ward/constituency level below you
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}