import { useDeferredValue, useMemo, useState, useCallback } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import LeafletMap from '@/Components/Map/LeafletMap';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { publicElectionTitle } from '@/Utils/publicElection';
import SearchableSelect from '@/Components/SearchableSelect';

// ── Helpers ───────────────────────────────────────────────────────────────────
const STATUS_FILTERS = [
    { key: 'all',          label: 'All',          color: '#64748b' },
    { key: 'not_reported', label: 'Not Reported',  color: '#94a3b8' },
    { key: 'submitted',    label: 'Submitted',     color: '#ef4444' },
    { key: 'in_progress',  label: 'Under Review',  color: '#f59e0b' },
    { key: 'certified',    label: 'Certified',     color: '#22c55e' },
];

function stationCategory(station) {
    const s = station.status;
    if (s === 'nationally_certified') return 'certified';
    if (['ward_certified', 'pending_constituency', 'constituency_certified',
         'pending_admin_area', 'admin_area_certified', 'pending_national'].includes(s)) return 'in_progress';
    if (['submitted', 'pending_ward', 'pending_party_acceptance'].includes(s)) return 'submitted';
    return 'not_reported';
}

function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
}

function buildOptions(stations, field) {
    const counts = new Map();
    stations.forEach(s => {
        const v = s[field];
        if (v) counts.set(v, (counts.get(v) || 0) + 1);
    });
    return Array.from(counts, ([value, count]) => ({ value, count }))
        .sort((a, b) => compareText(a.value, b.value))
        .map(o => ({ value: o.value, label: `${o.value} (${o.count})` }));
}

// ── Sidebar toggle button ─────────────────────────────────────────────────────
function SidebarToggle({ collapsed, onToggle }) {
    return (
        <button
            onClick={onToggle}
            className={[
                'absolute z-[500] top-1/2 -translate-y-1/2 w-6 h-14 flex items-center justify-center',
                'bg-white border border-slate-200 shadow-md rounded-r-lg transition-all hover:bg-slate-50',
                collapsed ? 'left-0' : 'left-72',
            ].join(' ')}
            title={collapsed ? 'Expand filters' : 'Collapse filters'}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
            <svg
                className={`w-3 h-3 text-slate-500 transition-transform ${collapsed ? '' : 'rotate-180'}`}
                viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"
            >
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 18l-6-6 6-6" />
            </svg>
        </button>
    );
}

// ── Stat chip (top bar) ───────────────────────────────────────────────────────
function StatChip({ color, label, count }) {
    return (
        <div className="flex items-center gap-1.5 bg-slate-800 rounded-lg px-2.5 py-1.5 border border-slate-700">
            <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: color }} />
            <span className="text-xs text-slate-400">{label}</span>
            <span className="text-xs font-bold tabular-nums" style={{ color }}>
                {count.toLocaleString()}
            </span>
        </div>
    );
}

// ── Main component ────────────────────────────────────────────────────────────
export default function ResultsMap({ election, elections = [], selectedElectionId, stations = [] }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';

    const [searchTerm,      setSearchTerm]      = useState('');
    const [selectedRegion,  setSelectedRegion]  = useState('all');
    const [selectedConst,   setSelectedConst]   = useState('all');
    const [statusFilter,    setStatusFilter]    = useState('all');
    const [sidebarOpen,     setSidebarOpen]     = useState(false);       // mobile overlay
    const [sidebarCollapsed,setSidebarCollapsed] = useState(false);      // desktop collapse

    const deferredSearch = useDeferredValue(searchTerm);
    useInertiaPrefetch([`/results${param}`, `/results/stations${param}`]);

    const stationList = stations || [];

    // ── Summary counts ────────────────────────────────────────────────────────
    const summary = useMemo(() => ({
        total:       stationList.length,
        certified:   stationList.filter(s => stationCategory(s) === 'certified').length,
        inProgress:  stationList.filter(s => stationCategory(s) === 'in_progress').length,
        submitted:   stationList.filter(s => stationCategory(s) === 'submitted').length,
        notReported: stationList.filter(s => stationCategory(s) === 'not_reported').length,
    }), [stationList]);

    // ── Dropdown options ──────────────────────────────────────────────────────
    const regionOptions = useMemo(() => buildOptions(stationList, 'admin_area_name'), [stationList]);

    const regionScoped = useMemo(() => (
        selectedRegion === 'all' ? stationList : stationList.filter(s => s.admin_area_name === selectedRegion)
    ), [stationList, selectedRegion]);

    const constOptions = useMemo(() => buildOptions(regionScoped, 'constituency_name'), [regionScoped]);

    const locationFiltered = useMemo(() => (
        regionScoped.filter(s => selectedConst === 'all' || s.constituency_name === selectedConst)
    ), [regionScoped, selectedConst]);

    const filterCounts = useMemo(() => ({
        all:          locationFiltered.length,
        not_reported: locationFiltered.filter(s => stationCategory(s) === 'not_reported').length,
        submitted:    locationFiltered.filter(s => stationCategory(s) === 'submitted').length,
        in_progress:  locationFiltered.filter(s => stationCategory(s) === 'in_progress').length,
        certified:    locationFiltered.filter(s => stationCategory(s) === 'certified').length,
    }), [locationFiltered]);

    const filteredStations = useMemo(() => {
        const q = deferredSearch.trim().toLowerCase();
        return locationFiltered.filter(s => {
            const catMatch  = statusFilter === 'all' || stationCategory(s) === statusFilter;
            const haystack  = [s.name, s.code, s.admin_area_name, s.constituency_name, s.ward_name].filter(Boolean).join(' ').toLowerCase();
            return catMatch && (!q || haystack.includes(q));
        });
    }, [locationFiltered, deferredSearch, statusFilter]);

    const handleRegionChange = useCallback((val) => {
        setSelectedRegion(val);
        setSelectedConst('all');
    }, []);

    const clearFilters = useCallback(() => {
        setSearchTerm('');
        setSelectedRegion('all');
        setSelectedConst('all');
        setStatusFilter('all');
    }, []);

    const hasFilters = searchTerm || selectedRegion !== 'all' || selectedConst !== 'all' || statusFilter !== 'all';

    if (!election) {
        return (
            <AppLayout>
                <div className="flex flex-col items-center justify-center min-h-[70vh] bg-slate-950 p-8">
                    <div className="text-6xl mb-4">🗺️</div>
                    <h1 className="text-2xl font-bold text-white mb-2">No election available</h1>
                    <p className="text-slate-400 mb-6">No active election is configured for public display.</p>
                    <Link href="/" className="bg-[var(--iec-pink)] text-white font-bold px-6 py-3 rounded-xl hover:bg-[var(--iec-pink-dark)]">
                        Back to Home
                    </Link>
                </div>
            </AppLayout>
        );
    }

    // ── Render ────────────────────────────────────────────────────────────────
    return (
        <AppLayout>
            <div className="flex flex-col bg-slate-950" style={{ minHeight: 'calc(100vh - 64px)' }}>

                {/* ── Top info bar ─────────────────────────────────────────── */}
                <div className="bg-slate-900 border-b border-slate-800 px-4 py-3 flex-shrink-0">
                    <div className="flex flex-wrap items-center gap-3 justify-between">

                        {/* Left — election info */}
                        <div className="flex items-center gap-3 min-w-0">
                            <div className="hidden sm:block">
                                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                                    IEC Live Map
                                </p>
                                <p className="text-sm font-bold text-white truncate max-w-xs">
                                    {publicElectionTitle(election)}
                                </p>
                            </div>

                            {/* Election switcher (searchable) */}
                            {elections.length > 1 && (
                                <SearchableSelect
                                    value={String(selectedElectionId || '')}
                                    onChange={val => router.get('/results/map', { election: val }, { preserveScroll: false })}
                                    options={elections.map(el => ({ value: String(el.id), label: publicElectionTitle(el) }))}
                                    placeholder="Select election"
                                    className="text-xs min-w-[160px]"
                                />
                            )}
                        </div>

                        {/* Right — stat chips */}
                        <div className="flex items-center gap-2 flex-wrap">
                            <StatChip color="#22c55e" label="Certified"   count={summary.certified}   />
                            <StatChip color="#f59e0b" label="In Review"   count={summary.inProgress}  />
                            <StatChip color="#ef4444" label="Submitted"   count={summary.submitted}   />
                            <StatChip color="#94a3b8" label="Unreported"  count={summary.notReported} />

                            <span className="text-xs text-slate-500 hidden md:block">
                                {filteredStations.length.toLocaleString()} / {summary.total.toLocaleString()} shown
                            </span>

                            {/* Mobile sidebar toggle */}
                            <button
                                onClick={() => setSidebarOpen(v => !v)}
                                className="lg:hidden ml-2 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-1.5 text-xs font-semibold"
                            >
                                ⚙ Filters
                            </button>

                            <Link
                                href={`/results${param}`}
                                className="hidden sm:flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
                            >
                                ← Results
                            </Link>
                        </div>
                    </div>
                </div>

                {/* ── Body: sidebar + map ───────────────────────────────────── */}
                <div className="flex flex-1 min-h-0 relative" style={{ height: 'calc(100vh - 130px)' }}>

                    {/* Desktop sidebar toggle (chevron on edge) */}
                    <div className="hidden lg:block">
                        <SidebarToggle
                            collapsed={sidebarCollapsed}
                            onToggle={() => setSidebarCollapsed(v => !v)}
                        />
                    </div>

                    {/* ── Sidebar ─────────────────────────────────────────── */}
                    <div
                        className={[
                            // Desktop: slide in/out with transition
                            'hidden lg:flex flex-col',
                            'bg-slate-900 border-r border-slate-800 flex-shrink-0',
                            'transition-all duration-200 ease-in-out overflow-hidden',
                            sidebarCollapsed ? 'w-0 border-r-0' : 'w-72',
                        ].join(' ')}
                    >
                        <SidebarContent
                            searchTerm={searchTerm}
                            setSearchTerm={setSearchTerm}
                            selectedRegion={selectedRegion}
                            setSelectedRegion={handleRegionChange}
                            selectedConst={selectedConst}
                            setSelectedConst={setSelectedConst}
                            regionOptions={regionOptions}
                            constOptions={constOptions}
                            statusFilter={statusFilter}
                            setStatusFilter={setStatusFilter}
                            filterCounts={filterCounts}
                            hasFilters={hasFilters}
                            clearFilters={clearFilters}
                            totalCount={summary.total}
                            regionScopedCount={regionScoped.length}
                        />
                    </div>

                    {/* Mobile sidebar overlay */}
                    {sidebarOpen && (
                        <div className="fixed inset-0 z-50 lg:hidden">
                            <button
                                className="absolute inset-0 bg-black/50"
                                onClick={() => setSidebarOpen(false)}
                                aria-label="Close sidebar"
                            />
                            <div className="relative h-full w-72 bg-slate-900 flex flex-col shadow-2xl">
                                <div className="flex items-center justify-between px-4 py-3 border-b border-slate-800">
                                    <span className="text-xs font-bold uppercase tracking-widest text-slate-400">Filters</span>
                                    <button onClick={() => setSidebarOpen(false)} className="text-slate-500 hover:text-white text-lg">✕</button>
                                </div>
                                <SidebarContent
                                    searchTerm={searchTerm}
                                    setSearchTerm={setSearchTerm}
                                    selectedRegion={selectedRegion}
                                    setSelectedRegion={handleRegionChange}
                                    selectedConst={selectedConst}
                                    setSelectedConst={setSelectedConst}
                                    regionOptions={regionOptions}
                                    constOptions={constOptions}
                                    statusFilter={statusFilter}
                                    setStatusFilter={setStatusFilter}
                                    filterCounts={filterCounts}
                                    hasFilters={hasFilters}
                                    clearFilters={clearFilters}
                                    totalCount={summary.total}
                                    regionScopedCount={regionScoped.length}
                                />
                            </div>
                        </div>
                    )}

                    {/* ── Map ─────────────────────────────────────────────── */}
                    <div className="flex-1 relative min-w-0">
                        {searchTerm !== deferredSearch && (
                            <div className="absolute top-4 left-1/2 -translate-x-1/2 z-[1000] bg-slate-900/90 text-white text-xs px-3 py-1.5 rounded-full border border-slate-700">
                                Filtering…
                            </div>
                        )}
                        <LeafletMap stations={filteredStations} height="100%" />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

// ── Sidebar content (shared between desktop + mobile) ─────────────────────────
function SidebarContent({
    searchTerm, setSearchTerm,
    selectedRegion, setSelectedRegion,
    selectedConst, setSelectedConst,
    regionOptions, constOptions,
    statusFilter, setStatusFilter,
    filterCounts, hasFilters, clearFilters,
    totalCount, regionScopedCount,
}) {
    return (
        <div className="flex flex-col flex-1 overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-800 hidden lg:flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-widest text-slate-400">Filters</span>
                {hasFilters && (
                    <button onClick={clearFilters} className="text-xs text-slate-500 hover:text-white">
                        Clear all
                    </button>
                )}
            </div>

            <div className="p-4 space-y-5 flex-1 overflow-y-auto">

                {/* Search */}
                <div>
                    <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                        Search Station
                    </label>
                    <input
                        type="search"
                        value={searchTerm}
                        onChange={e => setSearchTerm(e.target.value)}
                        placeholder="Name, code, ward…"
                        className="w-full bg-slate-800 border border-slate-700 text-white text-sm rounded-lg px-3 py-2 placeholder-slate-500 focus:outline-none focus:border-slate-500"
                    />
                </div>

                {/* Region */}
                <div>
                    <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                        Region
                    </label>
                    <SearchableSelect
                        value={selectedRegion}
                        onChange={setSelectedRegion}
                        options={[
                            { value: 'all', label: `All Regions (${totalCount})` },
                            ...regionOptions,
                        ]}
                        placeholder="All Regions"
                        className="w-full text-sm"
                    />
                </div>

                {/* Constituency */}
                <div>
                    <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                        Constituency
                    </label>
                    <SearchableSelect
                        value={selectedConst}
                        onChange={setSelectedConst}
                        options={[
                            { value: 'all', label: `All (${regionScopedCount})` },
                            ...constOptions,
                        ]}
                        placeholder="All Constituencies"
                        className="w-full text-sm"
                    />
                </div>

                {/* Status filter */}
                <div>
                    <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">
                        Result Status
                    </label>
                    <div className="space-y-1.5">
                        {STATUS_FILTERS.map(f => (
                            <button
                                key={f.key}
                                onClick={() => setStatusFilter(f.key)}
                                className={[
                                    'w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all border',
                                    statusFilter === f.key
                                        ? 'border-transparent text-white'
                                        : 'border-slate-700 text-slate-400 hover:border-slate-600 hover:text-slate-200',
                                ].join(' ')}
                                style={statusFilter === f.key
                                    ? { backgroundColor: f.color + '33', borderColor: f.color + '88' }
                                    : {}}
                            >
                                <div className="flex items-center gap-2.5">
                                    <span className="w-3 h-3 rounded-full flex-shrink-0"
                                        style={{ backgroundColor: f.key === 'all' ? '#64748b' : f.color }} />
                                    {f.label}
                                </div>
                                <span className="text-xs tabular-nums font-bold"
                                    style={{ color: statusFilter === f.key ? f.color : '#64748b' }}>
                                    {filterCounts[f.key] ?? 0}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Clear button */}
                {hasFilters && (
                    <button
                        onClick={clearFilters}
                        className="w-full py-2 border border-slate-700 text-slate-400 hover:text-white hover:border-slate-500 rounded-lg text-sm font-semibold transition-colors"
                    >
                        ✕ Clear Filters
                    </button>
                )}
            </div>

            {/* Legend footer */}
            <div className="p-4 border-t border-slate-800 flex-shrink-0">
                <p className="text-[10px] text-slate-600 leading-relaxed">
                    🔴 Submitted · 🟡 Under review · 🟢 IEC Chairman certified
                </p>
            </div>
        </div>
    );
}