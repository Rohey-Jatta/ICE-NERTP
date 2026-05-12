import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

const ACCEPTANCE_CONFIG = {
    accepted:                 { label: 'Accepted',              color: 'bg-iec-pink-500/20 text-iec-pink-600 border-teal-500/30',    icon: '✓' },
    accepted_with_reservation:{ label: 'Accepted (Reserved)',   color: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30', icon: '⚠' },
    rejected:                 { label: 'Disputed',              color: 'bg-red-500/20 text-red-300 border-red-500/30',       icon: '✗' },
    pending:                  { label: 'Not Yet Reviewed',      color: 'bg-slate-100 text-slate-500 border-slate-200',    icon: '○' },
};

const RESULT_STATUS_CONFIG = {
    not_reported:             { label: 'No Result Yet',         color: 'text-slate-500' },
    pending_party_acceptance: { label: 'Awaiting Parties',      color: 'text-yellow-400' },
    pending_ward:             { label: 'At Ward Review',        color: 'text-amber-400' },
    ward_certified:           { label: 'Ward Certified',        color: 'text-iec-pink-600' },
    pending_constituency:     { label: 'At Constituency',       color: 'text-iec-pink-600' },
    constituency_certified:   { label: 'Constituency Certified',color: 'text-cyan-400' },
    pending_admin_area:       { label: 'At Admin Area',         color: 'text-iec-pink-600' },
    admin_area_certified:     { label: 'Admin Area Certified',  color: 'text-violet-400' },
    pending_national:         { label: 'At National',           color: 'text-pink-400' },
    nationally_certified:     { label: 'Nationally Certified',  color: 'text-green-400' },
};

export default function PartyStations({ auth, stations = [], party }) {
    const partyColor = party?.color?.split(',')[0] || '#6b7280';

    const pendingCount   = stations.filter(s => s.has_result && !s.acceptance_is_final).length;
    const reviewedCount  = stations.filter(s => s.acceptance_is_final).length;
    const noResultCount  = stations.filter(s => !s.has_result).length;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Back link */}
                <div className="mb-6">
                    <Link href="/party/dashboard" className="text-slate-500 hover:text-iec-navy text-sm inline-flex items-center gap-1 mb-3">
                        ← Party Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">My Assigned Stations</h1>
                    {party?.name && (
                        <p className="text-slate-500 mt-1 flex items-center gap-2">
                            <span className="w-3 h-3 rounded-full inline-block"
                                  style={{ background: partyColor }} />
                            {party.name}
                        </p>
                    )}
                </div>

                {/* Summary pills */}
                <div className="flex flex-wrap gap-3 mb-6">
                    <div className="px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-full text-amber-300 text-sm font-semibold">
                        ⏳ {pendingCount} awaiting your decision
                    </div>
                    <div className="px-4 py-2 bg-iec-pink-500/15 border border-teal-500/30 rounded-full text-iec-pink-600 text-sm font-semibold">
                        ✓ {reviewedCount} reviewed
                    </div>
                    <div className="px-4 py-2 bg-gray-500/15 border border-slate-200 rounded-full text-slate-500 text-sm font-semibold">
                        ○ {noResultCount} no result yet
                    </div>
                </div>

                {stations.length === 0 ? (
                    <div className="bg-white rounded-xl p-12 border border-slate-200 text-center">
                        <p className="text-slate-500">No stations assigned to you yet.</p>
                        <p className="text-slate-500 text-sm mt-1">Contact the IEC Administrator to be assigned to polling stations.</p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {stations.map((station) => {
                            const acceptanceCfg = ACCEPTANCE_CONFIG[station.acceptance_status] || ACCEPTANCE_CONFIG.pending;
                            const resultCfg     = RESULT_STATUS_CONFIG[station.result_status] || RESULT_STATUS_CONFIG.not_reported;
                            const canReview     = station.has_result && !station.acceptance_is_final;

                            return (
                                <div key={station.id}
                                     className={`bg-white rounded-xl border transition-all ${
                                         canReview ? 'border-amber-500/30' : 'border-slate-200'
                                     }`}>
                                    <div className="p-5 flex flex-wrap gap-4 items-center justify-between">
                                        {/* Station info */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-3 flex-wrap">
                                                <h3 className="font-bold text-iec-navy text-lg">{station.name}</h3>
                                                <span className="text-xs font-mono text-slate-500 bg-white px-2 py-0.5 rounded">
                                                    {station.code}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-4 mt-1 flex-wrap">
                                                <span className="text-slate-500 text-sm">
                                                    {station.registered_voters?.toLocaleString()} registered voters
                                                </span>
                                                {station.has_result && (
                                                    <>
                                                        <span className="text-slate-600">·</span>
                                                        <span className="text-slate-500 text-sm">
                                                            {station.total_votes_cast?.toLocaleString()} votes cast
                                                        </span>
                                                        <span className="text-slate-600">·</span>
                                                        <span className="text-slate-500 text-sm">
                                                            {station.turnout_percentage}% turnout
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                            {/* Certification pipeline status */}
                                            <div className={`text-xs mt-1 ${resultCfg.color}`}>
                                                {station.has_result ? `Certification: ${resultCfg.label}` : 'No result submitted yet'}
                                            </div>
                                        </div>

                                        {/* Your acceptance status */}
                                        <div className="flex items-center gap-3 flex-shrink-0">
                                            <span className={`px-3 py-1.5 rounded-full text-xs font-semibold border ${acceptanceCfg.color}`}>
                                                {acceptanceCfg.icon} {acceptanceCfg.label}
                                            </span>

                                            {canReview ? (
                                                <Link
                                                    href={`/party/result/${station.result_id}`}
                                                    className="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-white text-sm font-bold rounded-lg transition-colors"
                                                >
                                                    Review & Decide →
                                                </Link>
                                            ) : station.has_result ? (
                                                <Link
                                                    href={`/party/result/${station.result_id}`}
                                                    className="px-4 py-2 bg-white hover:bg-slate-100 text-slate-600 text-sm font-semibold rounded-lg transition-colors"
                                                >
                                                    View Result
                                                </Link>
                                            ) : (
                                                <span className="px-4 py-2 bg-white text-slate-600 text-sm rounded-lg">
                                                    No result yet
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Show accepted comments if any */}
                                    {station.acceptance_is_final && station.acceptance_comments && (
                                        <div className="px-5 pb-4">
                                            <div className="p-3 bg-white rounded-lg border border-slate-200">
                                                <span className="text-xs text-slate-500 font-semibold">Your comment: </span>
                                                <span className="text-slate-600 text-xs">{station.acceptance_comments}</span>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}