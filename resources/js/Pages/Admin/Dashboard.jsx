import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

const formatNumber = (value) =>
    new Intl.NumberFormat().format(value || 0);

// ── Stat icons (SVG paths) ─────────────────────────────────────────────────
const STAT_ICONS = {
    users:    'M16 11c1.657 0 3-1.79 3-4s-1.343-4-3-4-3 1.79-3 4 1.343 4 3 4zm-8 0c1.657 0 3-1.79 3-4S9.657 3 8 3 5 4.79 5 7s1.343 4 3 4zm0 2c-2.67 0-8 1.337-8 4v2h16v-2c0-2.663-5.33-4-8-4zm8 0c-.29 0-.616.02-.97.056 1.236.89 1.97 2.083 1.97 3.444V19h7v-2c0-2.663-5.33-4-8-4z',
    stations: 'M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7zm0 9.5A2.5 2.5 0 1112 6a2.5 2.5 0 010 5.5z',
    election: 'M7 2v2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2V2h-2v2H9V2H7zm12 8H5v10h14V10z',
    status:   'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
};

const StatIcon = ({ path, filled = true }) => (
    <svg
        viewBox="0 0 24 24"
        fill={filled ? 'currentColor' : 'none'}
        stroke={filled ? 'none' : 'currentColor'}
        strokeWidth={filled ? 0 : 1.5}
        className="stat-card-icon"
        aria-hidden="true"
    >
        <path strokeLinecap="round" strokeLinejoin="round" d={path} />
    </svg>
);

// ── Stat Card ──────────────────────────────────────────────────────────────
const StatCard = ({ label, value, detail, variant, icon, delay = 0 }) => (
    <div
        className={`stat-card-new stat-card-${variant}`}
        style={{ animationDelay: `${delay}ms` }}
    >
        <StatIcon path={STAT_ICONS[icon]} />
        <div className="stat-card-number">{value}</div>
        <div className="stat-card-label">{label}</div>
        <div className="stat-card-detail">{detail}</div>
    </div>
);

// ── Action Tile ────────────────────────────────────────────────────────────
const ActionTile = ({ href, title, description }) => (
    <Link href={href} className="action-tile">
        <div className="action-tile-title">{title}</div>
        <div className="action-tile-desc">{description}</div>
    </Link>
);

// ── Readiness Item ─────────────────────────────────────────────────────────
const ReadinessItem = ({ label, ready, value }) => (
    <div className="readiness-item">
        <div className="readiness-item-left">
            <span className={`readiness-dot ${ready ? 'readiness-dot-ready' : 'readiness-dot-warn'}`} />
            <span className="readiness-label">{label}</span>
        </div>
        <span className={`readiness-badge ${ready ? 'readiness-badge-ready' : 'readiness-badge-warn'}`}>
            {value}
        </span>
    </div>
);

// ── Page ───────────────────────────────────────────────────────────────────
export default function AdminDashboard({ auth, statistics, systemStatus }) {
    const stats = {
        totalUsers:      statistics?.totalUsers      || 0,
        totalStations:   statistics?.totalStations   || 0,
        activeElections: statistics?.activeElections || 0,
        systemStatus:    systemStatus?.status        || 'Running',
    };

    const isSystemRunning = stats.systemStatus?.toLowerCase() === 'running';

    const readinessItems = [
        {
            label: 'User provisioning',
            ready: stats.totalUsers > 0,
            value: stats.totalUsers > 0 ? 'Ready' : 'Needs setup',
        },
        {
            label: 'Polling station registry',
            ready: stats.totalStations > 0,
            value: stats.totalStations > 0 ? 'Ready' : 'Needs setup',
        },
        {
            label: 'Election configuration',
            ready: stats.activeElections > 0,
            value: stats.activeElections > 0 ? 'Active' : 'No active election',
        },
        {
            label: 'Application services',
            ready: isSystemRunning,
            value: stats.systemStatus,
        },
    ];

    return (
        <AppLayout user={auth.user}>
            <div className="ws-page">

                {/* ── Page Header ─────────────────────────────────────── */}
                <div className="ws-header">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div className="ws-header-eyebrow">Administrator Workspace</div>
                            <h1 className="ws-heading">Control Panel</h1>
                            <p className="ws-subheading">
                                Platform health, user coverage, station readiness, and election configuration at a glance.
                            </p>
                        </div>
                        <Link
                            href="/admin/users/create"
                            className="inline-flex items-center gap-2 self-start rounded bg-iec-pink-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-iec-pink-600 active:bg-iec-pink-700 shrink-0"
                        >
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                            </svg>
                            Create User
                        </Link>
                    </div>
                </div>

                <div className="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">

                    {/* ── Stat Cards ──────────────────────────────────── */}
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <StatCard
                            label="Total Users"
                            value={formatNumber(stats.totalUsers)}
                            detail="All active platform accounts"
                            variant="pink"
                            icon="users"
                            delay={0}
                        />
                        <StatCard
                            label="Polling Stations"
                            value={formatNumber(stats.totalStations)}
                            detail="Configured station records"
                            variant="blue"
                            icon="stations"
                            delay={60}
                        />
                        <StatCard
                            label="Active Elections"
                            value={formatNumber(stats.activeElections)}
                            detail="Currently open for workflow"
                            variant="amber"
                            icon="election"
                            delay={120}
                        />
                        <StatCard
                            label="System Status"
                            value={stats.systemStatus}
                            detail="Current application health"
                            variant="green"
                            icon="status"
                            delay={180}
                        />
                    </div>

                    {/* ── Shortcuts + Readiness ────────────────────────── */}
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_320px]">

                        {/* Operational shortcuts */}
                        <div className="ws-panel">
                            <div className="ws-panel-header">
                                <h2 className="ws-panel-title">Operational shortcuts</h2>
                            </div>
                            <div className="ws-panel-body grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <ActionTile
                                    href="/admin/users"
                                    title="Manage users"
                                    description="Accounts, roles, and access control"
                                />
                                <ActionTile
                                    href="/admin/elections"
                                    title="Election setup"
                                    description="Create and configure election records"
                                />
                                <ActionTile
                                    href="/admin/polling-stations"
                                    title="Polling stations"
                                    description="Station registry and officer assignments"
                                />
                                <ActionTile
                                    href="/admin/party-representatives"
                                    title="Party representatives"
                                    description="Party agents and station coverage"
                                />
                                <ActionTile
                                    href="/admin/election-monitors"
                                    title="Election monitors"
                                    description="Monitor accreditation and assignments"
                                />
                                <ActionTile
                                    href="/admin/audit-logs"
                                    title="Audit logs"
                                    description="Administrative and workflow activity"
                                />
                            </div>
                        </div>

                        {/* Readiness check */}
                        <div className="ws-panel">
                            <div className="ws-panel-header flex items-center justify-between">
                                <h2 className="ws-panel-title">Readiness check</h2>
                                <span className="text-[0.6rem] font-mono text-slate-600">
                                    {readinessItems.filter(i => i.ready).length}/{readinessItems.length} ready
                                </span>
                            </div>
                            <div className="ws-panel-body">
                                <div className="readiness-list">
                                    {readinessItems.map((item) => (
                                        <ReadinessItem key={item.label} {...item} />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ── Administrative Structure ─────────────────────── */}
                    <div className="ws-panel">
                        <div className="ws-panel-header">
                            <h2 className="ws-panel-title">Administrative structure</h2>
                        </div>
                        <div className="ws-panel-body grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <ActionTile
                                href="/admin/hierarchy/admin-areas"
                                title="Administrative areas"
                                description="Top-level regional units"
                            />
                            <ActionTile
                                href="/admin/hierarchy/constituencies"
                                title="Constituencies"
                                description="Subdivisions under administrative areas"
                            />
                            <ActionTile
                                href="/admin/hierarchy/wards"
                                title="Wards"
                                description="Ward-level approval and station grouping"
                            />
                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}
