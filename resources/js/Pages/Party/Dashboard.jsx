import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

const StatCard = ({ value, label, color, sub }) => (
    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 flex flex-col">
        <div className={`text-3xl font-bold mb-1 ${color}`}>{value}</div>
        <div className="text-gray-400 text-sm">{label}</div>
        {sub && <div className="text-gray-600 text-xs mt-1">{sub}</div>}
    </div>
);

export default function PartyDashboard({ auth, party, assignedStations = [], statistics = {}, noAssignment = false }) {

    if (noAssignment) {
        return (
            <AppLayout user={auth.user}>
                <div className="container mx-auto px-4 py-16 flex items-center justify-center min-h-[60vh]">
                    <div className="text-center p-12 bg-slate-800/40 rounded-2xl border border-slate-700/50 max-w-lg">
                        <div className="text-6xl mb-4">🚫</div>
                        <h1 className="text-2xl font-bold text-white mb-3">No Polling Stations Assigned</h1>
                        <p className="text-gray-400 text-sm leading-relaxed">
                            You have not been assigned to any polling stations yet.
                            Please contact the IEC Administrator to set up your assignment.
                        </p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const partyColor = party?.color?.split(',')[0] || '#6b7280';

    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center gap-4 mb-2">
                        {/* Party color stripe */}
                        <div
                            className="w-1.5 h-10 rounded-full flex-shrink-0"
                            style={{
                                background: party?.color?.includes(',')
                                    ? `linear-gradient(to bottom, ${party.color})`
                                    : partyColor
                            }}
                        />
                        <div>
                            <h1 className="text-3xl font-bold text-white">Party Representative Dashboard</h1>
                            <p className="text-gray-400 mt-0.5">
                                {party?.name}
                                {party?.abbreviation && (
                                    <span className="ml-2 px-2 py-0.5 rounded text-xs font-mono bg-slate-700 text-gray-300">
                                        {party.abbreviation}
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>
                    <p className="text-gray-500 text-sm ml-6">
                        You are assigned to <strong className="text-gray-300">{assignedStations.length}</strong> polling station{assignedStations.length !== 1 ? 's' : ''}.
                        Your role is to <strong className="text-gray-300">review and accept or dispute election results</strong>.
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    <StatCard
                        value={statistics.totalStations || 0}
                        label="Assigned Stations"
                        color="text-white"
                    />
                    <StatCard
                        value={statistics.pendingAcceptance || 0}
                        label="Awaiting Your Decision"
                        color="text-amber-300"
                        sub="results needing review"
                    />
                    <StatCard
                        value={statistics.accepted || 0}
                        label="Accepted"
                        color="text-teal-300"
                    />
                    <StatCard
                        value={statistics.acceptedWithReservation || 0}
                        label="Accepted w/ Reservation"
                        color="text-yellow-300"
                    />
                    <StatCard
                        value={statistics.disputed || 0}
                        label="Disputed / Rejected"
                        color="text-red-300"
                    />
                </div>

                {/* Pending alert */}
                {statistics.pendingAcceptance > 0 && (
                    <div className="mb-6 p-4 bg-amber-500/10 border border-amber-500/40 rounded-xl flex items-center gap-3">
                        <span className="w-3 h-3 bg-amber-400 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-amber-300 flex-1">
                            <strong>{statistics.pendingAcceptance}</strong> result{statistics.pendingAcceptance > 1 ? 's' : ''} from
                            your assigned stations are awaiting your decision.
                        </p>
                        <Link
                            href="/party/pending-acceptance"
                            className="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-white text-sm font-bold rounded-lg whitespace-nowrap"
                        >
                            Review Now →
                        </Link>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <Link
                        href="/party/pending-acceptance"
                        className="group p-6 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 rounded-xl transition-all"
                    >
                        <div className="flex items-center gap-3 mb-2">
                            <div className="w-10 h-10 bg-amber-500/20 rounded-xl flex items-center justify-center text-amber-400 text-xl">⚖️</div>
                            <div>
                                <div className="font-bold text-white text-lg">Review Pending Results</div>
                                <div className="text-amber-300 text-sm">
                                    {statistics.pendingAcceptance > 0
                                        ? `${statistics.pendingAcceptance} result${statistics.pendingAcceptance > 1 ? 's' : ''} awaiting your decision`
                                        : 'All results reviewed — nothing pending'}
                                </div>
                            </div>
                        </div>
                        <p className="text-gray-400 text-xs mt-2 ml-13">
                            Accept, accept with reservation, or dispute results from your assigned polling stations.
                        </p>
                    </Link>

                    <Link
                        href="/party/stations"
                        className="group p-6 bg-slate-700/40 hover:bg-slate-700/60 border border-slate-600/50 rounded-xl transition-all"
                    >
                        <div className="flex items-center gap-3 mb-2">
                            <div className="w-10 h-10 bg-teal-500/20 rounded-xl flex items-center justify-center text-teal-400 text-xl">🗳️</div>
                            <div>
                                <div className="font-bold text-white text-lg">My Assigned Stations</div>
                                <div className="text-teal-300 text-sm">{assignedStations.length} stations</div>
                            </div>
                        </div>
                        <p className="text-gray-400 text-xs mt-2">
                            View all polling stations you are assigned to monitor and review.
                        </p>
                    </Link>
                </div>

                {/* Assigned Stations quick list */}
                {assignedStations.length > 0 && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-lg font-bold text-white mb-4">Your Polling Stations</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {assignedStations.map((station) => (
                                <div key={station.id}
                                     className="bg-slate-900/50 rounded-lg p-4 border border-slate-700/30">
                                    <div className="font-semibold text-white text-sm">{station.name}</div>
                                    <div className="text-gray-500 text-xs font-mono mt-0.5">{station.code}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}