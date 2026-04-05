import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

const STATUS_CFG = {
    submitted:                { label: 'Submitted',             color: 'bg-sky-500/20 text-sky-300' },
    pending_party_acceptance: { label: 'Party Review',          color: 'bg-yellow-500/20 text-yellow-300' },
    pending_ward:             { label: 'Ward Review',           color: 'bg-amber-500/20 text-amber-300' },
    ward_certified:           { label: 'Ward Certified',        color: 'bg-teal-500/20 text-teal-300' },
    pending_constituency:     { label: 'Constituency',          color: 'bg-blue-500/20 text-blue-300' },
    constituency_certified:   { label: 'Const. Certified',      color: 'bg-cyan-500/20 text-cyan-300' },
    pending_admin_area:       { label: 'Admin Area',            color: 'bg-purple-500/20 text-purple-300' },
    admin_area_certified:     { label: 'Area Certified',        color: 'bg-violet-500/20 text-violet-300' },
    pending_national:         { label: 'Pending National ⚑',   color: 'bg-amber-500/20 text-amber-300 font-bold' },
    nationally_certified:     { label: 'Nationally Certified ✓',color: 'bg-green-500/20 text-green-300' },
};

export default function AllResults({ auth, results = {}, filter = 'all', counts = {} }) {
    const data     = results.data || [];
    const links    = results.links || [];
    const meta     = results.meta || {};

    const filters = [
        { key: 'all',                  label: 'All Results',         count: counts.all },
        { key: 'pending_national',     label: 'Pending My Approval', count: counts.pending_national },
        { key: 'in_pipeline',          label: 'In Pipeline',         count: counts.in_pipeline },
        { key: 'nationally_certified', label: 'Nationally Certified',count: counts.nationally_certified },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                <div className="mb-6">
                    <Link href="/chairman/dashboard"
                          className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        ← Chairman Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">All Results — National Overview</h1>
                    <p className="text-gray-400 mt-1 text-sm">Complete view of all election results across the nation</p>
                </div>

                {/* Filter tabs */}
                <div className="flex flex-wrap gap-2 mb-6">
                    {filters.map((f) => (
                        <button
                            key={f.key}
                            onClick={() => router.get('/chairman/all-results', { filter: f.key }, { preserveState: false })}
                            className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all ${
                                filter === f.key
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-slate-800/40 text-gray-400 hover:bg-slate-700 border border-slate-700/50'
                            }`}
                        >
                            {f.label}
                            <span className={`text-xs px-2 py-0.5 rounded-full ${filter === f.key ? 'bg-white/20' : 'bg-slate-700'}`}>
                                {f.count || 0}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Results table */}
                <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
                    {data.length === 0 ? (
                        <div className="p-12 text-center">
                            <p className="text-gray-400">No results match this filter.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-700 bg-slate-900/40">
                                        <th className="text-left text-gray-400 py-3 px-4">Station</th>
                                        <th className="text-left text-gray-400 py-3 px-4">Ward</th>
                                        <th className="text-right text-gray-400 py-3 px-4">Votes Cast</th>
                                        <th className="text-right text-gray-400 py-3 px-4">Turnout</th>
                                        <th className="text-center text-gray-400 py-3 px-4">Status</th>
                                        <th className="text-center text-gray-400 py-3 px-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map((result) => {
                                        const cfg = STATUS_CFG[result.certification_status]
                                            || { label: result.certification_status, color: 'bg-gray-500/20 text-gray-300' };
                                        const isPendingNational = result.certification_status === 'pending_national';

                                        return (
                                            <tr key={result.id}
                                                className={`border-b border-slate-700/30 hover:bg-slate-700/20 ${
                                                    isPendingNational ? 'bg-amber-500/5' : ''
                                                }`}>
                                                <td className="py-3 px-4">
                                                    <div className="font-semibold text-white">{result.polling_station_name}</div>
                                                    <div className="text-gray-500 text-xs font-mono">{result.polling_station_code}</div>
                                                </td>
                                                <td className="py-3 px-4 text-gray-400 text-xs">{result.ward_name}</td>
                                                <td className="py-3 px-4 text-right text-white">{result.total_votes_cast?.toLocaleString()}</td>
                                                <td className="py-3 px-4 text-right text-blue-300">{result.turnout_percentage}%</td>
                                                <td className="py-3 px-4 text-center">
                                                    <span className={`px-2.5 py-1 rounded-full text-xs ${cfg.color}`}>
                                                        {cfg.label}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4 text-center">
                                                    {isPendingNational ? (
                                                        <Link href="/chairman/national-queue"
                                                              className="text-xs px-3 py-1 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 rounded-lg border border-amber-500/30">
                                                            Certify →
                                                        </Link>
                                                    ) : (
                                                        <span className="text-gray-600 text-xs">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {links.length > 3 && (
                    <div className="mt-4 flex justify-center gap-1">
                        {links.map((link, i) =>
                            link.url ? (
                                <Link key={i} href={link.url}
                                      className={`px-3 py-2 text-sm rounded-lg ${
                                          link.active ? 'bg-blue-600 text-white' : 'bg-slate-800 text-gray-300 hover:bg-slate-700'
                                      }`}
                                      dangerouslySetInnerHTML={{ __html: link.label }} />
                            ) : (
                                <span key={i} className="px-3 py-2 text-sm rounded-lg bg-slate-900 text-gray-600"
                                      dangerouslySetInnerHTML={{ __html: link.label }} />
                            )
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}