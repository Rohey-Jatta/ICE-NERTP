import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';
import { useNotifications, ToastContainer } from '@/Components/Notifications';

const StatCard = ({ value, label, color, sub }) => (
    <div className="bg-white rounded-xl p-6 border border-slate-200 flex flex-col">
        <div className={`text-3xl font-bold mb-1 ${color}`}>{value}</div>
        <div className="text-slate-500 text-sm">{label}</div>
        {sub && <div className="text-slate-600 text-xs mt-1">{sub}</div>}
    </div>
);

export default function PartyDashboard({ auth, party, assignedStations = [], statistics = {}, noAssignment = false }) {
    const [refreshing, setRefreshing] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    const { toasts, removeNotification, notify } = useNotifications();

    // Auto-refresh every 30 seconds
    useAutoRefreshWithVisibility({
        url: '/party/dashboard',
        interval: 30000,
        preserveScroll: true,
        preserveState: true,
        onBeforeRefresh: () => setRefreshing(true),
        onAfterRefresh: () => {
            setRefreshing(false);
            setLastRefreshTime(new Date());
            notify.info('Dashboard updated');
        },
    });

    if (noAssignment) {
        return (
            <AppLayout user={auth.user}>
                <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />
                <div className="container mx-auto px-4 py-16 flex items-center justify-center min-h-[60vh]">
                    <div className="text-center p-12 bg-white rounded-2xl border border-slate-200 max-w-lg">
                        <div className="text-6xl mb-4">🚫</div>
                        <h1 className="text-2xl font-bold text-iec-navy mb-3">No Polling Stations Assigned</h1>
                        <p className="text-slate-500 text-sm leading-relaxed">
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
            <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />
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
                            <h1 className="text-3xl font-bold text-iec-navy">Party Representative Dashboard</h1>
                            <p className="text-slate-500 mt-0.5">
                                {party?.name}
                                {party?.abbreviation && (
                                    <span className="ml-2 px-2 py-0.5 rounded text-xs font-mono bg-white text-slate-600">
                                        {party.abbreviation}
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>
                    <p className="text-slate-500 text-sm ml-6">
                        You are assigned to <strong className="text-slate-600">{assignedStations.length}</strong> polling station{assignedStations.length !== 1 ? 's' : ''}.
                        Your role is to <strong className="text-slate-600">review and accept or dispute election results</strong>.
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    <StatCard
                        value={statistics.totalStations || 0}
                        label="Assigned Stations"
                        color="text-iec-navy"
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
                        color="text-iec-pink-600"
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
                    <div className="mb-6 p-4 bg-pink-500/10 border border-pink-500/40 rounded-xl flex items-center gap-3">
                        <span className="w-3 h-3 bg-pink-400 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-pink-00 flex-1">
                            <strong>{statistics.pendingAcceptance}</strong> result{statistics.pendingAcceptance > 1 ? 's' : ''} from
                            your assigned stations are awaiting your decision.
                        </p>
                        <Link
                            href="/party/pending-acceptance"
                            className="px-4 py-2 bg-pink-500 hover:bg-pink-400 text-white text-sm font-bold rounded-lg whitespace-nowrap"
                        >
                            Review Now →
                        </Link>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <Link
                        href="/party/pending-acceptance"
                        className="group p-6 bg-pink-500/10 hover:bg-pink-500/20 border border-pink-500/30 rounded-xl transition-all"
                    >
                        <div className="flex items-center gap-3 mb-2">
                            <div className="w-10 h-10 bg-pink-500/20 rounded-xl flex items-center justify-center text-pink-600 text-xl">⚖️</div>
                            <div>
                                <div className="font-bold text-iec-navy text-lg">Review Pending Results</div>
                                <div className="text-pink-600 text-sm">
                                    {statistics.pendingAcceptance > 0
                                        ? `${statistics.pendingAcceptance} result${statistics.pendingAcceptance > 1 ? 's' : ''} awaiting your decision`
                                        : 'All results reviewed — nothing pending'}
                                </div>
                            </div>
                        </div>
                        <p className="text-slate-600 text-xs mt-2 ml-13">
                            Accept, accept with reservation, or dispute results from your assigned polling stations.
                        </p>
                    </Link>

                    <Link
                        href="/party/stations"
                        className="group p-6 bg-slate-100 hover:bg-white/60 border border-slate-200 rounded-xl transition-all"
                    >
                        <div className="flex items-center gap-3 mb-2">
                            <div className="w-10 h-10 bg-iec-pink-500/20 rounded-xl flex items-center justify-center text-iec-pink-600 text-xl">🗳️</div>
                            <div>
                                <div className="font-bold text-iec-navy text-lg">My Assigned Stations</div>
                                <div className="text-iec-pink-600 text-sm">{assignedStations.length} stations</div>
                            </div>
                        </div>
                        <p className="text-slate-500 text-xs mt-2">
                            View all polling stations you are assigned to monitor and review.
                        </p>
                    </Link>
                </div>

                {/* Assigned Stations quick list */}
                {assignedStations.length > 0 && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200">
                        <h2 className="text-lg font-bold text-iec-navy mb-4">Your Polling Stations</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {assignedStations.map((station) => (
                                <div key={station.id}
                                     className="bg-white rounded-lg p-4 border border-slate-200">
                                    <div className="font-semibold text-iec-navy text-sm">{station.name}</div>
                                    <div className="text-slate-500 text-xs font-mono mt-0.5">{station.code}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
