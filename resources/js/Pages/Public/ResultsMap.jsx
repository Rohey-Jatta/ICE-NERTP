import { useDeferredValue, useMemo, useState, useCallback } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import LeafletMap from '@/Components/Map/LeafletMap';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { publicElectionTitle } from '@/Utils/publicElection';
import SearchableSelect from '@/Components/SearchableSelect';
import { RESULT_STATUS, RESULT_STATUS_MAP_COLORS, getPublicStationCategory } from '@/Utils/resultStatus';

// ── Helpers ───────────────────────────────────────────────────────────────────
function numeric(v) { return Number(v || 0); }
function firstColor(value, fallback = '#94a3b8') { return (value || fallback).split(',')[0].trim(); }
function initials(name = '') {
    const p = String(name).trim().split(/\s+/).filter(Boolean);
    if (!p.length) return '?';
    if (p.length === 1) return p[0].slice(0, 2).toUpperCase();
    return `${p[0][0]}${p[p.length - 1][0]}`.toUpperCase();
}

const STATUS_FILTERS = [
    { key: 'all',          label: 'All',          color: '#64748b' },
    { key: 'not_reported', label: 'Not Published', color: RESULT_STATUS_MAP_COLORS[RESULT_STATUS.NOT_REPORTED] },
    { key: 'certified',    label: 'Certified',    color: RESULT_STATUS_MAP_COLORS[RESULT_STATUS.NATIONALLY_CERTIFIED] },
];

function stationCategory(station) {
    return getPublicStationCategory(station.status);
}

function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
}

function buildOptions(stations, field) {
    const counts = new Map();
    stations.forEach((s) => { const v = s[field]; if (v) counts.set(v, (counts.get(v) || 0) + 1); });
    return Array.from(counts, ([value, count]) => ({ value, count }))
        .sort((a, b) => compareText(a.value, b.value))
        .map((o) => ({ value: o.value, label: `${o.value} (${o.count})` }));
}

// ── Candidate avatar ──────────────────────────────────────────────────────────
function Avatar({ candidate, size = 48 }) {
    const color = firstColor(candidate.color);
    const [err, setErr] = useState(false);
    if (candidate.photo_url && !err) {
        return (
            <img src={candidate.photo_url} alt={candidate.name} onError={() => setErr(true)}
                 className="flex-shrink-0 rounded-full object-cover" style={{ width: size, height: size, boxShadow: `0 0 0 3px #fff, 0 0 0 4px ${color}` }} />
        );
    }
    return (
        <div className="grid flex-shrink-0 place-items-center rounded-full font-bold text-white"
             style={{ width: size, height: size, fontSize: size * 0.34, backgroundColor: color, boxShadow: `0 0 0 3px #fff, 0 0 0 4px ${color}` }}>
            {initials(candidate.name)}
        </div>
    );
}

// ── National scorecard (CNN-style header) ─────────────────────────────────────
function ScoreCard({ national, election }) {
    const cands = national?.candidates || [];
    const top = cands.slice(0, 4);
    const totalVotes = numeric(national?.total_votes);
    const reporting = numeric(national?.reporting_pct);

    if (cands.length === 0) {
        return (
            <div className="border-b border-slate-200 bg-white px-4 py-4">
                <p className="text-center text-sm text-slate-500">No results have been reported yet for {publicElectionTitle(election)}.</p>
            </div>
        );
    }

    return (
        <div className="border-b border-slate-200 bg-white">
            <div className="mx-auto max-w-[1240px] px-4 py-4">
                {/* Candidate cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {top.map((c, i) => {
                        const color = firstColor(c.color);
                        return (
                            <div key={c.name} className={`flex items-center gap-3 rounded-xl border p-3 ${i === 0 ? 'border-slate-300 bg-slate-50' : 'border-slate-200 bg-white'}`}>
                                <Avatar candidate={c} size={46} />
                                <div className="min-w-0">
                                    <div className="flex items-center gap-1.5">
                                        <span className="font-serif text-2xl font-bold leading-none tabular-nums text-slate-900">{c.pct}%</span>
                                        {i === 0 && <span className="rounded bg-[#e61a6e] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">Lead</span>}
                                    </div>
                                    <div className="mt-1 truncate text-sm font-bold text-slate-800">{c.name}</div>
                                    <div className="truncate text-xs text-slate-500">
                                        <span className="font-semibold" style={{ color }}>{c.party}</span> · {numeric(c.votes).toLocaleString()} votes
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Stacked share bar */}
                <div className="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100">
                    {cands.map((c) => (
                        <div key={c.name} title={`${c.party} ${c.pct}%`} style={{ width: `${c.pct}%`, backgroundColor: firstColor(c.color) }} />
                    ))}
                </div>
                <div className="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span className="tabular-nums">{totalVotes.toLocaleString()} valid votes counted</span>
                    <span className="font-semibold text-slate-600 tabular-nums">{reporting}% of stations reporting</span>
                </div>
            </div>
        </div>
    );
}

// ── Shared sub-components ─────────────────────────────────────────────────────

function BackBreadcrumb({ crumbs, onNav }) {
    // crumbs: [{ label, level }]  — click navigates back to that level
    return (
        <div className="flex items-center gap-1.5 border-b border-slate-200 px-4 py-3 text-xs">
            {crumbs.map((c, i) => (
                <span key={i} className="flex items-center gap-1.5">
                    {i > 0 && <span className="text-slate-300">/</span>}
                    <button onClick={() => onNav(c.level)}
                            className="font-semibold text-slate-500 transition hover:text-slate-900">
                        {c.label}
                    </button>
                </span>
            ))}
        </div>
    );
}

function DrillHeader({ eyebrow, title, stats }) {
    return (
        <div className="px-5 pt-4 pb-3">
            <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">{eyebrow}</div>
            <h3 className="mt-0.5 font-serif text-xl font-bold tracking-tight text-slate-900 leading-snug">{title}</h3>
            <div className="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-500 tabular-nums">
                {stats.map((s, i) => <span key={i}>{s}</span>)}
            </div>
        </div>
    );
}

function CandidateBar({ c }) {
    const color = firstColor(c.color);
    return (
        <div>
            <div className="mb-1 flex items-center justify-between gap-2 text-sm">
                <span className="flex min-w-0 items-center gap-2">
                    <span className="h-2.5 w-2.5 flex-shrink-0 rounded-full" style={{ backgroundColor: color }} />
                    <span className="truncate font-semibold text-slate-800">{c.name}</span>
                    <span className="flex-shrink-0 text-xs text-slate-400">{c.party}</span>
                </span>
                <span className="flex-shrink-0 tabular-nums">
                    <b className="text-slate-900">{numeric(c.votes).toLocaleString()}</b>
                    <span className="ml-1.5 text-xs text-slate-500">{c.pct}%</span>
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                <div className="h-full rounded-full" style={{ width: `${Math.max(1, c.pct)}%`, backgroundColor: color }} />
            </div>
        </div>
    );
}

function DrillRow({ color, title, sub, right, onClick }) {
    return (
        <button onClick={onClick}
                className="flex w-full items-center gap-3 border-b border-slate-100 px-5 py-3 text-left transition hover:bg-slate-50 active:bg-slate-100">
            <span className="h-9 w-1.5 flex-shrink-0 rounded-full" style={{ backgroundColor: color || '#cbd5e1' }} />
            <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-bold text-slate-900">{title}</div>
                <div className="truncate text-xs text-slate-500">{sub}</div>
            </div>
            <div className="flex flex-shrink-0 items-center gap-1.5 text-xs text-slate-400">
                <span className="tabular-nums">{right}</span>
                <svg className="h-3.5 w-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 18l6-6-6-6" />
                </svg>
            </div>
        </button>
    );
}

// ── 3-level drill panel ───────────────────────────────────────────────────────
// drill: { region, constituency, ward }  — null at each unselected level
function DrillPanel({ regions, drill, onDrill, stations, param }) {
    const { region: selRegion, constituency: selCon, ward: selWard } = drill;

    const region = selRegion ? regions.find((r) => r.name === selRegion) : null;
    const con    = region && selCon ? region.constituencies?.find((c) => c.name === selCon) : null;
    const ward   = con && selWard   ? con.wards?.find((w) => w.name === selWard)           : null;

    // ── Level 3: Ward selected → list its stations ───────────────────────────
    if (ward) {
        const wardStations = stations.filter(
            (s) => s.admin_area_name === selRegion &&
                   s.constituency_name === selCon &&
                   s.ward_name === selWard
        );
        return (
            <div className="flex h-full flex-col">
                <BackBreadcrumb
                    crumbs={[
                        { label: 'Regions', level: 'root' },
                        { label: selRegion, level: 'region' },
                        { label: selCon, level: 'constituency' },
                    ]}
                    onNav={(lvl) => {
                        if (lvl === 'root')         onDrill({ region: null,      constituency: null, ward: null });
                        if (lvl === 'region')       onDrill({ region: selRegion, constituency: null, ward: null });
                        if (lvl === 'constituency') onDrill({ region: selRegion, constituency: selCon, ward: null });
                    }}
                />
                <DrillHeader
                    eyebrow={`${selCon} · Ward`}
                    title={selWard}
                    stats={[
                        `${ward.reporting_pct}% reporting`,
                        `${numeric(ward.total_stations)} stations`,
                        `${numeric(ward.total_votes).toLocaleString()} votes`,
                    ]}
                />
                {ward.leader && (
                    <div className="px-5 pb-3">
                        <span className="text-xs font-semibold" style={{ color: firstColor(ward.leader.color) }}>
                            {ward.leader.party}
                        </span>
                        <span className="text-xs text-slate-500"> leads · {ward.leader_pct}%</span>
                    </div>
                )}
                <div className="border-t border-slate-100 px-5 py-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                    Polling stations ({wardStations.length})
                </div>
                <div className="flex-1 overflow-y-auto">
                    {wardStations.length === 0 ? (
                        <p className="px-5 py-4 text-sm text-slate-500">No stations with coordinates in this ward.</p>
                    ) : (
                        wardStations.map((s) => {
                            const isReported = s.status !== 'not_reported' && s.total_votes_cast != null;
                            const statusColor = RESULT_STATUS_MAP_COLORS[s.status] || RESULT_STATUS_MAP_COLORS[RESULT_STATUS.NOT_REPORTED];
                            return (
                                <div key={s.id} className="flex items-start gap-3 border-b border-slate-100 px-5 py-3">
                                    <span className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full" style={{ backgroundColor: statusColor }} />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-semibold text-slate-800">{s.name}</div>
                                        <div className="font-mono text-xs text-slate-400">{s.code}</div>
                                    </div>
                                    <div className="flex-shrink-0 text-right text-xs tabular-nums text-slate-500">
                                        {isReported ? numeric(s.valid_votes).toLocaleString() + ' valid' : '—'}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        );
    }

    // ── Level 2: Constituency selected → list its wards ──────────────────────
    if (con) {
        return (
            <div className="flex h-full flex-col">
                <BackBreadcrumb
                    crumbs={[
                        { label: 'Regions', level: 'root' },
                        { label: selRegion, level: 'region' },
                    ]}
                    onNav={(lvl) => {
                        if (lvl === 'root')   onDrill({ region: null,      constituency: null, ward: null });
                        if (lvl === 'region') onDrill({ region: selRegion, constituency: null, ward: null });
                    }}
                />
                <DrillHeader
                    eyebrow={`${selRegion} · Constituency`}
                    title={selCon}
                    stats={[
                        `${con.reporting_pct}% reporting`,
                        `${numeric(con.total_stations)} stations`,
                        `${numeric(con.total_votes).toLocaleString()} votes`,
                    ]}
                />
                <div className="border-t border-slate-100 px-5 py-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                    Wards ({con.wards?.length ?? 0})
                </div>
                <div className="flex-1 overflow-y-auto">
                    {(con.wards ?? []).map((w) => (
                        <DrillRow
                            key={w.name}
                            color={w.leader ? firstColor(w.leader.color) : null}
                            title={w.name}
                            sub={w.leader
                                ? `${w.leader.party} leads · ${w.leader_pct}%`
                                : 'Awaiting results'}
                            right={`${w.total_stations} stn · ${w.reporting_pct}%`}
                            onClick={() => onDrill({ region: selRegion, constituency: selCon, ward: w.name })}
                        />
                    ))}
                </div>
            </div>
        );
    }

    // ── Level 1: Region selected → list its constituencies ───────────────────
    if (region) {
        return (
            <div className="flex h-full flex-col">
                <BackBreadcrumb
                    crumbs={[{ label: 'Regions', level: 'root' }]}
                    onNav={() => onDrill({ region: null, constituency: null, ward: null })}
                />
                <DrillHeader
                    eyebrow="Administrative Region"
                    title={selRegion}
                    stats={[
                        `${region.reporting_pct}% reporting`,
                        `${numeric(region.reported_stations)} / ${numeric(region.total_stations)} stations`,
                        `${numeric(region.total_votes).toLocaleString()} votes`,
                    ]}
                />
                {region.candidates?.length > 0 && (
                    <div className="space-y-3 border-t border-slate-100 px-5 py-3">
                        {region.candidates.map((c) => <CandidateBar key={c.name} c={c} />)}
                    </div>
                )}
                <div className="border-t border-slate-100 px-5 py-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                    Constituencies ({region.constituencies?.length ?? 0})
                </div>
                <div className="flex-1 overflow-y-auto">
                    {(region.constituencies ?? []).map((c) => (
                        <DrillRow
                            key={c.name}
                            color={c.leader ? firstColor(c.leader.color) : null}
                            title={c.name}
                            sub={c.leader
                                ? `${c.leader.party} leads · ${c.leader_pct}%`
                                : 'Awaiting results'}
                            right={`${c.total_stations} stn · ${c.reporting_pct}%`}
                            onClick={() => onDrill({ region: selRegion, constituency: c.name, ward: null })}
                        />
                    ))}
                </div>
                <div className="border-t border-slate-200 p-3">
                    <Link href={`${param ? `/results/stations${param}` : '/results/stations'}`}
                          className="text-xs font-semibold text-[#e61a6e] hover:underline">
                        View all stations in this region →
                    </Link>
                </div>
            </div>
        );
    }

    // ── Level 0: All regions list ─────────────────────────────────────────────
    const sorted = [...regions].sort((a, b) => numeric(b.total_votes) - numeric(a.total_votes));
    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-slate-200 px-5 py-3">
                <span className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                    Regions · tap to drill down
                </span>
            </div>
            <div className="flex-1 overflow-y-auto">
                {sorted.map((r) => (
                    <DrillRow
                        key={r.name}
                        color={r.leader ? firstColor(r.leader.color) : null}
                        title={r.name}
                        sub={r.leader
                            ? `${r.leader.party} leads · ${r.leader.pct}%`
                            : 'Awaiting results'}
                        right={`${r.reporting_pct}%`}
                        onClick={() => onDrill({ region: r.name, constituency: null, ward: null })}
                    />
                ))}
            </div>
        </div>
    );
}

// ── Main ──────────────────────────────────────────────────────────────────────
export default function ResultsMap({ election, elections = [], selectedElectionId, stations = [], regions = [], national = null }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';

    const [mode,        setMode]        = useState('regions');   // 'regions' | 'stations'
    // drill state: which region / constituency / ward is selected
    const [drill,       setDrill]       = useState({ region: null, constituency: null, ward: null });
    const [searchTerm,  setSearchTerm]  = useState('');
    const [selStationReg, setSelStationReg] = useState('all');
    const [selConst,    setSelConst]    = useState('all');
    const [statusFilter,setStatusFilter]= useState('all');
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const selectedRegion = drill.region;

    const deferredSearch = useDeferredValue(searchTerm);
    useInertiaPrefetch([`/results${param}`, `/results/stations${param}`]);

    const stationList = stations || [];

    // Station-mode filtering (unchanged behaviour)
    const regionOptions = useMemo(() => buildOptions(stationList, 'admin_area_name'), [stationList]);
    const regionScoped = useMemo(() => (
        selStationReg === 'all' ? stationList : stationList.filter((s) => s.admin_area_name === selStationReg)
    ), [stationList, selStationReg]);
    const constOptions = useMemo(() => buildOptions(regionScoped, 'constituency_name'), [regionScoped]);
    const locationFiltered = useMemo(() => (
        regionScoped.filter((s) => selConst === 'all' || s.constituency_name === selConst)
    ), [regionScoped, selConst]);
    const filterCounts = useMemo(() => ({
        all:          locationFiltered.length,
        not_reported: locationFiltered.filter((s) => stationCategory(s) === 'not_reported').length,
        submitted:    locationFiltered.filter((s) => stationCategory(s) === 'submitted').length,
        in_progress:  locationFiltered.filter((s) => stationCategory(s) === 'in_progress').length,
        certified:    locationFiltered.filter((s) => stationCategory(s) === 'certified').length,
    }), [locationFiltered]);
    const filteredStations = useMemo(() => {
        const q = deferredSearch.trim().toLowerCase();
        return locationFiltered.filter((s) => {
            const catMatch = statusFilter === 'all' || stationCategory(s) === statusFilter;
            const haystack = [s.name, s.code, s.admin_area_name, s.constituency_name, s.ward_name].filter(Boolean).join(' ').toLowerCase();
            return catMatch && (!q || haystack.includes(q));
        });
    }, [locationFiltered, deferredSearch, statusFilter]);

    const handleStationRegion = useCallback((val) => { setSelStationReg(val); setSelConst('all'); }, []);
    const clearFilters = useCallback(() => { setSearchTerm(''); setSelStationReg('all'); setSelConst('all'); setStatusFilter('all'); }, []);
    const hasFilters = searchTerm || selStationReg !== 'all' || selConst !== 'all' || statusFilter !== 'all';

    // Map-click on a region polygon → select that region (level 1)
    const handleRegionClick = useCallback((regionName) => {
        setDrill({ region: regionName, constituency: null, ward: null });
    }, []);

    // Drill stations: filter by the deepest selected level
    const drillStations = useMemo(() => {
        if (!drill.region) return [];
        let filtered = stationList.filter((s) => s.admin_area_name === drill.region);
        if (drill.constituency) filtered = filtered.filter((s) => s.constituency_name === drill.constituency);
        if (drill.ward)         filtered = filtered.filter((s) => s.ward_name === drill.ward);
        return filtered;
    }, [stationList, drill]);

    if (!election) {
        return (
            <AppLayout>
                <div className="flex min-h-[70vh] flex-col items-center justify-center bg-slate-50 p-8">
                    <div className="text-6xl mb-4">🗺️</div>
                    <h1 className="mb-2 text-2xl font-bold text-slate-900">No election available</h1>
                    <p className="mb-6 text-slate-500">No active election is configured for public display.</p>
                    <Link href="/" className="rounded-xl bg-[#e61a6e] px-6 py-3 font-bold text-white hover:bg-[#b81259]">Back to Home</Link>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="flex flex-col bg-white font-sans" style={{ minHeight: 'calc(100vh - 64px)' }}>
                {/* ── Top bar ─────────────────────────────────────────────── */}
                <div className="border-b border-slate-200 bg-white px-4 py-3">
                    <div className="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#e61a6e]">IEC Live Results Map</p>
                            <p className="truncate text-base font-bold text-slate-900">{publicElectionTitle(election)}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            {/* Mode toggle */}
                            <div className="flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                                {[['regions', 'Regions'], ['stations', 'Stations']].map(([key, label]) => (
                                    <button key={key} onClick={() => setMode(key)}
                                            className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${mode === key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}>
                                        {label}
                                    </button>
                                ))}
                            </div>
                            {elections.length > 1 && (
                                <SearchableSelect
                                    value={String(selectedElectionId || '')}
                                    onChange={(val) => router.get('/results/map', { election: val }, { preserveScroll: false })}
                                    options={elections.map((el) => ({ value: String(el.id), label: publicElectionTitle(el) }))}
                                    placeholder="Select election"
                                    className="min-w-[160px] text-xs"
                                />
                            )}
                            {mode === 'stations' && (
                                <button onClick={() => setSidebarOpen((v) => !v)} className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 lg:hidden">⚙ Filters</button>
                            )}
                            <Link href={`/results${param}`} className="hidden items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 sm:flex">← Results</Link>
                        </div>
                    </div>
                </div>

                {/* ── National scorecard ──────────────────────────────────── */}
                <ScoreCard national={national} election={election} />

                {/* ── Body: map + panel ───────────────────────────────────── */}
                <div className="relative flex flex-1 min-h-0" style={{ height: 'calc(100vh - 230px)' }}>
                    {/* Map */}
                    <div className="relative min-w-0 flex-1">
                        {mode === 'stations' && searchTerm !== deferredSearch && (
                            <div className="absolute left-1/2 top-4 z-[1000] -translate-x-1/2 rounded-full border border-slate-200 bg-white/90 px-3 py-1.5 text-xs text-slate-600">Filtering…</div>
                        )}
                        <LeafletMap
                            mode={mode}
                            regions={regions}
                            stations={filteredStations}
                            drillStations={drillStations}
                            selectedRegion={selectedRegion}
                            drillLevel={drill.ward ? 'ward' : drill.constituency ? 'constituency' : drill.region ? 'region' : null}
                            onRegionClick={handleRegionClick}
                            height="100%"
                        />
                    </div>

                    {/* Right panel */}
                    {mode === 'regions' ? (
                        <aside className="hidden w-[360px] flex-shrink-0 border-l border-slate-200 bg-white md:block">
                            <DrillPanel
                                regions={regions}
                                drill={drill}
                                onDrill={setDrill}
                                stations={stationList}
                                param={param}
                            />
                        </aside>
                    ) : (
                        <aside className="hidden w-72 flex-shrink-0 border-l border-slate-200 bg-white lg:flex lg:flex-col">
                            <SidebarContent
                                searchTerm={searchTerm} setSearchTerm={setSearchTerm}
                                selectedRegion={selStationReg} setSelectedRegion={handleStationRegion}
                                selectedConst={selConst} setSelectedConst={setSelConst}
                                regionOptions={regionOptions} constOptions={constOptions}
                                statusFilter={statusFilter} setStatusFilter={setStatusFilter}
                                filterCounts={filterCounts} hasFilters={hasFilters} clearFilters={clearFilters}
                                totalCount={stationList.length} regionScopedCount={regionScoped.length}
                            />
                        </aside>
                    )}

                    {/* Mobile region panel (regions mode) */}
                    {mode === 'regions' && (
                        <div className="border-t border-slate-200 bg-white md:hidden">
                            <div className="max-h-64 overflow-y-auto">
                                <DrillPanel
                                    regions={regions}
                                    drill={drill}
                                    onDrill={setDrill}
                                    stations={stationList}
                                    param={param}
                                />
                            </div>
                        </div>
                    )}

                    {/* Mobile filters overlay (stations mode) */}
                    {mode === 'stations' && sidebarOpen && (
                        <div className="fixed inset-0 z-50 lg:hidden">
                            <button className="absolute inset-0 bg-black/40" onClick={() => setSidebarOpen(false)} aria-label="Close filters" />
                            <div className="relative ml-auto flex h-full w-72 flex-col bg-white shadow-2xl">
                                <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                    <span className="text-xs font-bold uppercase tracking-widest text-slate-400">Filters</span>
                                    <button onClick={() => setSidebarOpen(false)} className="text-lg text-slate-400 hover:text-slate-900">✕</button>
                                </div>
                                <SidebarContent
                                    searchTerm={searchTerm} setSearchTerm={setSearchTerm}
                                    selectedRegion={selStationReg} setSelectedRegion={handleStationRegion}
                                    selectedConst={selConst} setSelectedConst={setSelConst}
                                    regionOptions={regionOptions} constOptions={constOptions}
                                    statusFilter={statusFilter} setStatusFilter={setStatusFilter}
                                    filterCounts={filterCounts} hasFilters={hasFilters} clearFilters={clearFilters}
                                    totalCount={stationList.length} regionScopedCount={regionScoped.length}
                                />
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

// ── Station filter sidebar (stations mode) ────────────────────────────────────
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
        <div className="flex flex-1 flex-col overflow-hidden">
            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <span className="text-xs font-bold uppercase tracking-widest text-slate-400">Filters</span>
                {hasFilters && <button onClick={clearFilters} className="text-xs text-slate-500 hover:text-slate-900">Clear all</button>}
            </div>
            <div className="flex-1 space-y-5 overflow-y-auto p-4">
                <div>
                    <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Search Station</label>
                    <input type="search" value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} placeholder="Name, code, ward…"
                           className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 outline-none focus:border-[#e61a6e] focus:bg-white" />
                </div>
                <div>
                    <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Region</label>
                    <SearchableSelect value={selectedRegion} onChange={setSelectedRegion}
                                      options={[{ value: 'all', label: `All Regions (${totalCount})` }, ...regionOptions]} placeholder="All Regions" className="w-full text-sm" />
                </div>
                <div>
                    <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Constituency</label>
                    <SearchableSelect value={selectedConst} onChange={setSelectedConst}
                                      options={[{ value: 'all', label: `All (${regionScopedCount})` }, ...constOptions]} placeholder="All Constituencies" className="w-full text-sm" />
                </div>
                <div>
                    <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Result Status</label>
                    <div className="space-y-1.5">
                        {STATUS_FILTERS.map((f) => (
                            <button key={f.key} onClick={() => setStatusFilter(f.key)}
                                    className={`flex w-full items-center justify-between rounded-lg border px-3 py-2 text-sm font-medium transition ${statusFilter === f.key ? 'border-transparent text-slate-900' : 'border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-800'}`}
                                    style={statusFilter === f.key ? { backgroundColor: f.color + '22', borderColor: f.color + '88' } : {}}>
                                <div className="flex items-center gap-2.5">
                                    <span className="h-3 w-3 flex-shrink-0 rounded-full" style={{ backgroundColor: f.key === 'all' ? '#64748b' : f.color }} />
                                    {f.label}
                                </div>
                                <span className="text-xs font-bold tabular-nums" style={{ color: statusFilter === f.key ? f.color : '#94a3b8' }}>{filterCounts[f.key] ?? 0}</span>
                            </button>
                        ))}
                    </div>
                </div>
                {hasFilters && (
                    <button onClick={clearFilters} className="w-full rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-500 transition hover:border-slate-400 hover:text-slate-900">✕ Clear Filters</button>
                )}
            </div>
            <div className="flex-shrink-0 border-t border-slate-200 p-4">
                <p className="text-[10px] leading-relaxed text-slate-400">🟠 Submitted · 🟡 Under review · 🟢 IEC Chairman certified</p>
            </div>
        </div>
    );
}
