import AppLayout from '@/Layouts/AppLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback, useMemo } from 'react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { electionTypeLabel, publicElectionTitle } from '@/Utils/publicElection';
import LeafletMap from '@/Components/Map/LeafletMap';

// ── Helpers ───────────────────────────────────────────────────────────────────
function numeric(v) { return Number(v || 0); }

// ── Candidate Row ─────────────────────────────────────────────────────────────
function CandidateRow({ candidate, rank, totalValidVotes }) {
    const pct = totalValidVotes > 0
        ? (numeric(candidate.total_votes) / numeric(totalValidVotes)) * 100
        : 0;
    const pctDisplay = pct.toFixed(2);
    const isLeading = rank === 1;
    const color = (candidate.party_color || '#64748b').split(',')[0].trim();
    const [imgError, setImgError] = useState(false);

    return (
        <div className={`flex items-center gap-3 px-5 py-3 border-b border-gray-100 last:border-b-0 transition-colors ${
            isLeading ? 'bg-emerald-50/60' : 'hover:bg-gray-50/50'
        }`}>
            <div className="w-5 text-center flex-shrink-0">
                <span className={`text-sm font-mono ${isLeading ? 'font-bold text-amber-500' : 'text-gray-300'}`}>
                    {rank}
                </span>
            </div>

            <div className="flex-shrink-0">
                {candidate.photo_url && !imgError ? (
                    <img
                        src={candidate.photo_url}
                        alt={candidate.name}
                        className="w-10 h-10 rounded-full object-cover border-2 flex-shrink-0"
                        style={{ borderColor: color }}
                        onError={() => setImgError(true)}
                    />
                ) : (
                    <div
                        className="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold ring-1 ring-gray-200"
                        style={{ backgroundColor: color }}
                    >
                        {candidate.name?.charAt(0)?.toUpperCase() || '?'}
                    </div>
                )}
            </div>

            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-0.5">
                    <span className={`text-sm truncate ${isLeading ? 'font-bold text-gray-900' : 'font-semibold text-gray-800'}`}>
                        {candidate.name}
                    </span>
                    {isLeading && (
                        <span className="flex-shrink-0 text-[10px] font-bold text-emerald-700 bg-emerald-100 border border-emerald-200 px-1.5 py-0.5 rounded-sm uppercase tracking-wide">
                            Leading
                        </span>
                    )}
                </div>
                <p className="text-xs text-gray-400 mb-1.5 truncate">
                    <span className="font-medium text-gray-500">{candidate.party_abbr}</span>
                    {candidate.party_name && candidate.party_name !== 'Independent' && ` — ${candidate.party_name}`}
                </p>
                <div className="h-1.5 bg-gray-100 overflow-hidden">
                    <div
                        className="h-full transition-all duration-500"
                        style={{ width: `${Math.min(100, pct)}%`, backgroundColor: color }}
                    />
                </div>
            </div>

            <div className="text-right flex-shrink-0 min-w-[90px]">
                <div className="text-base font-bold text-gray-900 tabular-nums">
                    {numeric(candidate.total_votes).toLocaleString()}
                </div>
                <div className="text-xs text-gray-500 tabular-nums font-medium">{pctDisplay}%</div>
            </div>
        </div>
    );
}

function PipelineBreakdown({ pipeline = {}, totalStations = 0 }) {
    const submitted = Number(pipeline.submitted ?? 0);
    const underReview = Number(pipeline.under_review ?? 0);
    const certified = Number(pipeline.certified ?? 0);
    const notReported = Math.max(0, totalStations - submitted - underReview - certified);

    const statusItems = [
        { label: 'Submitted', value: submitted, color: 'text-blue-700', bg: 'bg-blue-50', border: 'border-blue-200' },
        { label: 'Under Review', value: underReview, color: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-200' },
        { label: 'Certified', value: certified, color: 'text-emerald-700', bg: 'bg-emerald-50', border: 'border-emerald-200' },
        { label: 'Not Reported', value: notReported, color: 'text-slate-500', bg: 'bg-slate-50', border: 'border-slate-200' },
    ];

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 mb-3">
                Certification Pipeline
            </p>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {statusItems.map((item) => (
                    <div key={item.label} className={`rounded-lg border px-3 py-2 text-center ${item.bg} ${item.border}`}>
                        <div className={`text-lg font-extrabold tabular-nums ${item.color}`}>
                            {item.value.toLocaleString()}
                        </div>
                        <div className="text-xs text-slate-500">{item.label}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Main Component ────────────────────────────────────────────────────────────
export default function Results({ election, elections = [], selectedElectionId, stats, pipeline, candidates, message }) {
    const { url } = usePage();
    const isHome   = url?.split('?')[0] === '/';
    const basePath = isHome ? '/' : '/results';
    const param    = selectedElectionId ? `?election=${selectedElectionId}` : '';

    const [mapStations, setMapStations]       = useState([]);
    const [mapLoading, setMapLoading]         = useState(true);
    const [mapError, setMapError]             = useState(false);
    const [isDropdownOpen, setIsDropdownOpen] = useState(false);
    const [searchTerm, setSearchTerm]         = useState('');
    const [lastRefreshed, setLastRefreshed]   = useState(new Date());
    const [isRefreshing, setIsRefreshing]     = useState(false);

    useInertiaPrefetch([`/results/map${param}`, `/results/stations${param}`]);

    // ── Fetch embedded map data ───────────────────────────────────────────────
    const fetchMapData = useCallback(() => {
        if (!election?.id) { setMapLoading(false); return; }
        fetch(`/api/public/map-stations?election=${election.id}`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(data => { setMapStations(Array.isArray(data.stations) ? data.stations : []); setMapLoading(false); setMapError(false); })
            .catch(() => { setMapError(true); setMapLoading(false); });
    }, [election?.id]);

    useEffect(() => {
        setMapLoading(true);
        fetchMapData();
    }, [fetchMapData]);

    // ── Auto-refresh every 60 seconds ─────────────────────────────────────────
    // Keeps the public page current without requiring a manual reload.
    // Only reloads the data props — preserves scroll position.
    useEffect(() => {
        if (!election?.id) return;
        const timer = setInterval(() => {
            setIsRefreshing(true);
            router.reload({
                only: ['election', 'stats', 'pipeline', 'candidates', 'message'],
                preserveScroll: true,
                onSuccess: () => {
                    setLastRefreshed(new Date());
                    setIsRefreshing(false);
                    fetchMapData(); // also refresh map data
                },
                onError: () => setIsRefreshing(false),
            });
        }, 60_000);
        return () => clearInterval(timer);
    }, [election?.id, fetchMapData]);

    // Derived statistics
    // Compute an aggregated, deduped candidate list client-side as a safeguard
    // against duplicate candidate rows coming from the backend.
    const aggregatedCandidates = useMemo(() => {
        if (!Array.isArray(candidates)) return [];
        const map = new Map();
        // Secondary index: map normalized name+party to the canonical map key
        const nameIndex = new Map();
        for (const c of candidates) {
            if (!c) continue;

            const safeName = (c.name || '').toString().trim().toLowerCase();
            const safeParty = (c.party_abbr || '').toString().trim().toLowerCase();
            const votes = Number(c.total_votes || 0);

            const idKey = (typeof c.id !== 'undefined' && c.id !== null) ? String(c.id) : null;
            const nameKey = `${safeName}|${safeParty}`;

            // If we've already seen a record with same name+party, merge into it
            let targetKey = null;
            if (nameIndex.has(nameKey)) {
                targetKey = nameIndex.get(nameKey);
            } else if (idKey && map.has(idKey)) {
                targetKey = idKey;
                nameIndex.set(nameKey, targetKey);
            } else if (idKey) {
                targetKey = idKey;
                nameIndex.set(nameKey, targetKey);
            } else if (map.size > 0 && map.has(nameKey)) {
                targetKey = nameKey;
                nameIndex.set(nameKey, targetKey);
            } else {
                targetKey = nameKey;
                nameIndex.set(nameKey, targetKey);
            }

            if (map.has(targetKey)) {
                const ex = map.get(targetKey);
                ex.total_votes = Number(ex.total_votes || 0) + votes;
            } else {
                map.set(targetKey, Object.assign({}, c, { total_votes: votes, name: c.name || '', party_abbr: c.party_abbr || '' }));
            }
        }
        return Array.from(map.values()).sort((a, b) => Number(b.total_votes) - Number(a.total_votes));
    }, [candidates]);

    const totalValidVotes    = aggregatedCandidates.length > 0
        ? aggregatedCandidates.reduce((s, c) => s + Number(c.total_votes || 0), 0)
        : numeric(stats?.valid_votes);

    // Debug: log incoming candidates and aggregated result to browser console
    useEffect(() => {
        try {
            console.debug('[Public/Results] candidates prop:', candidates);
            console.debug('[Public/Results] aggregatedCandidates:', aggregatedCandidates);
        } catch (e) {
            // ignore in environments without console
        }
    }, [candidates, aggregatedCandidates]);
    const totalStations      = numeric(stats?.total_stations);
    const stationsCertified  = numeric(stats?.stations_reported);
    const totalRegistered    = numeric(stats?.total_registered);
    const totalCast          = numeric(stats?.total_cast);
    const turnoutPct         = totalRegistered > 0 ? ((totalCast / totalRegistered) * 100).toFixed(1) : '0.0';
    const certPct            = totalStations > 0 ? Math.round((stationsCertified / totalStations) * 100) : 0;
    const hasStats           = stats && stationsCertified > 0;
    const hasCandidates      = Array.isArray(aggregatedCandidates) && aggregatedCandidates.length > 0;
    const isCertified        = election?.status === 'certified';

    // ── No election configured ────────────────────────────────────────────────
    if (!election) {
        return (
            <AppLayout>
                <div className="bg-gray-50 min-h-screen flex items-center justify-center p-8">
                    <div className="text-center max-w-sm bg-white border border-gray-200 p-10">
                        <div className="text-4xl mb-4">🏛️</div>
                        <h1 className="text-xl font-bold text-gray-800 mb-2">No Active Election</h1>
                        <p className="text-sm text-gray-500">
                            No election is currently configured for public display.
                        </p>
                        {elections.length > 0 && (
                            <div className="mt-4 flex flex-wrap justify-center gap-2">
                                {elections.map(el => (
                                    <button
                                        key={el.id}
                                        onClick={() => router.get(basePath, { election: el.id })}
                                        className="px-3 py-1.5 text-xs border border-gray-300 text-gray-600 hover:border-gray-500 transition-colors rounded-sm"
                                    >
                                        {publicElectionTitle(el)}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="bg-gray-50 min-h-screen">

                {/* ── ELECTION BANNER ───────────────────────────────────────── */}
                <div className="bg-white border-b border-gray-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                        <div className="flex flex-wrap items-start justify-between gap-4">

                            {/* Election title + status badges */}
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2 mb-2">
                                    {/* Publication state */}
                                    <span className={`inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 border rounded-sm ${
                                        isCertified
                                            ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                                            : certPct > 0
                                            ? 'bg-blue-50 border-blue-300 text-blue-800'
                                            : 'bg-amber-50 border-amber-300 text-amber-800'
                                    }`}>
                                        {isCertified
                                            ? '✓ Official Published Results'
                                            : certPct > 0
                                            ? '⟳ Certification In Progress'
                                            : '○ Awaiting Results'}
                                    </span>
                                    <span className="text-xs text-gray-500 bg-gray-100 border border-gray-200 px-2.5 py-1 rounded-sm">
                                        {electionTypeLabel(election)}
                                    </span>
                                    {hasStats && (
                                        <span className="text-xs text-gray-500 bg-gray-100 border border-gray-200 px-2.5 py-1 rounded-sm tabular-nums">
                                            {stationsCertified.toLocaleString()} / {totalStations.toLocaleString()} stations certified
                                        </span>
                                    )}
                                    {/* Last refreshed indicator */}
                                    <span className={`text-xs px-2.5 py-1 rounded-sm border transition-colors ${
                                        isRefreshing
                                            ? 'bg-blue-50 border-blue-200 text-blue-600 animate-pulse'
                                            : 'bg-slate-50 border-slate-200 text-slate-400'
                                    }`}>
                                        {isRefreshing ? '⟳ Updating…' : `Updated ${lastRefreshed.toLocaleTimeString()}`}
                                    </span>
                                </div>
                                <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight leading-tight">
                                    {publicElectionTitle(election)}
                                </h1>
                                <p className="text-sm text-gray-500 mt-1">
                                    Independent Electoral Commission · The Gambia
                                </p>
                            </div>

                            {/* Election switcher */}
                            {elections.length > 1 && (
                                <div className="flex-shrink-0 w-full sm:w-auto">
                                    <p className="text-xs font-medium text-gray-400 mb-2 uppercase tracking-wide">Select Election</p>
                                    <div className="relative w-full sm:w-72">
                                        <button
                                            onClick={() => setIsDropdownOpen(!isDropdownOpen)}
                                            className="w-full px-4 py-2 text-left text-sm font-medium bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent transition-colors flex items-center justify-between"
                                        >
                                            <span className="truncate">
                                                {election ? publicElectionTitle(election) : 'Choose an election...'}
                                            </span>
                                            <svg className={`w-4 h-4 text-gray-400 transition-transform ${isDropdownOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                            </svg>
                                        </button>

                                        {isDropdownOpen && (
                                            <div className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg">
                                                <div className="p-2 border-b border-gray-200">
                                                    <input
                                                        type="text"
                                                        placeholder="Search elections..."
                                                        value={searchTerm}
                                                        onChange={(e) => setSearchTerm(e.target.value)}
                                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                                                        autoFocus
                                                    />
                                                </div>
                                                <div className="max-h-64 overflow-y-auto">
                                                    {elections
                                                        .filter(el => publicElectionTitle(el).toLowerCase().includes(searchTerm.toLowerCase()))
                                                        .map(el => (
                                                            <button
                                                                key={el.id}
                                                                onClick={() => {
                                                                    router.get(basePath, { election: el.id }, { preserveScroll: false });
                                                                    setIsDropdownOpen(false);
                                                                    setSearchTerm('');
                                                                }}
                                                                className={`w-full text-left px-4 py-2.5 text-sm border-b border-gray-100 last:border-b-0 transition-colors ${
                                                                    selectedElectionId === el.id
                                                                        ? 'bg-pink-50 text-pink-900 font-semibold'
                                                                        : 'text-gray-700 hover:bg-gray-50'
                                                                }`}
                                                            >
                                                                <div className="flex items-center justify-between">
                                                                    <span>{publicElectionTitle(el)}</span>
                                                                    {selectedElectionId === el.id && (
                                                                        <svg className="w-4 h-4 text-pink-600" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                                        </svg>
                                                                    )}
                                                                </div>
                                                            </button>
                                                        ))}
                                                    {elections.filter(el => publicElectionTitle(el).toLowerCase().includes(searchTerm.toLowerCase())).length === 0 && (
                                                        <div className="px-4 py-6 text-center text-sm text-gray-500">No elections found</div>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {isDropdownOpen && (
                                            <div
                                                className="fixed inset-0 z-40"
                                                onClick={() => { setIsDropdownOpen(false); setSearchTerm(''); }}
                                            />
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* ── NO RESULTS STATE ──────────────────────────────────────── */}
                {!hasStats && (
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">

                        <div className="bg-white border border-gray-200 p-12 max-w-md mx-auto text-center">
                            <div className="text-4xl mb-4">🗳️</div>
                            <h2 className="text-lg font-bold text-gray-800 mb-2">
                                Awaiting Certified Results
                            </h2>
                            <p className="text-sm text-gray-500 leading-relaxed">
                                {message || 'Results will appear here as polling stations are officially certified by the IEC Chairman.'}
                            </p>

                            <p className="text-xs text-gray-400 mt-2">
                                Page auto-refreshes every 60 seconds.
                            </p>
                        </div>
                    </div>
                )}

                {/* ── MAIN RESULTS DASHBOARD ────────────────────────────────── */}
                {hasStats && (
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-8 space-y-4">



                        {/* ── CANDIDATE RESULTS TABLE ───────────────────────── */}
                        {hasCandidates && (
                            <div className="bg-white border border-gray-200 shadow-sm">
                                <div className="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
                                    <div>
                                        <h2 className="text-xs font-bold uppercase tracking-widest text-gray-500">
                                            Candidate Results
                                        </h2>
                                        <p className="text-xs text-gray-400 mt-0.5">
                                            Official vote totals · {stationsCertified.toLocaleString()} certified stations
                                        </p>
                                    </div>
                                    <div className="text-right text-xs hidden sm:block">
                                        <div className="text-gray-500">
                                            <span className="font-semibold text-emerald-700 tabular-nums">
                                                    {numeric(totalValidVotes).toLocaleString()}
                                                </span>{' '}valid votes
                                        </div>
                                        <div className="text-gray-500">
                                            <span className="font-semibold text-amber-600 tabular-nums">
                                                {numeric(stats?.rejected_votes).toLocaleString()}
                                            </span>{' '}rejected
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3 px-5 py-2 border-b border-gray-100 bg-gray-50/50">
                                    <div className="w-5 text-xs font-bold uppercase tracking-wider text-gray-300">#</div>
                                    <div className="w-10" />
                                    <div className="flex-1 text-xs font-bold uppercase tracking-wider text-gray-400">Candidate</div>
                                    <div className="min-w-[90px] text-right text-xs font-bold uppercase tracking-wider text-gray-400">Votes</div>
                                </div>

                                {aggregatedCandidates.map((candidate, idx) => (
                                    <CandidateRow
                                        key={candidate.id}
                                        candidate={candidate}
                                        rank={idx + 1}
                                        totalValidVotes={totalValidVotes}
                                    />
                                ))}
                            </div>
                        )}

                        {/* ── STATS PANEL + LIVE MAP ────────────────────────── */}
                        <div className="grid grid-cols-1 lg:grid-cols-5 bg-white border border-gray-200 shadow-sm overflow-hidden">

                            {/* LEFT: statistics */}
                            <div className="lg:col-span-2 border-b lg:border-b-0 lg:border-r border-gray-200 flex flex-col">

                                <div className="px-5 py-2.5 bg-gray-50 border-b border-gray-200 flex-shrink-0">
                                    <span className="text-xs font-bold uppercase tracking-widest text-gray-400">
                                        Results Summary
                                    </span>
                                </div>

                                {/* Key stats */}
                                <div className="grid grid-cols-2 border-b border-gray-100 flex-shrink-0">
                                    {[
                                        { label: 'Registered Voters', value: totalRegistered.toLocaleString(),              accent: 'text-gray-900',   borderR: true,  borderB: true  },
                                        { label: 'Votes Cast',        value: totalCast.toLocaleString(),                    accent: 'text-[var(--iec-pink)]', sub: `${turnoutPct}% turnout`, borderR: false, borderB: true  },
                                        { label: 'Valid Votes',       value: numeric(stats?.valid_votes).toLocaleString(),   accent: 'text-emerald-700', borderR: true,  borderB: false },
                                        { label: 'Rejected Ballots',  value: numeric(stats?.rejected_votes).toLocaleString(), accent: 'text-gray-700',   borderR: false, borderB: false },
                                    ].map(s => (
                                        <div key={s.label} className={`px-5 py-4 ${s.borderR ? 'border-r border-gray-100' : ''} ${s.borderB ? 'border-b border-gray-100' : ''}`}>
                                            <div className="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">{s.label}</div>
                                            <div className={`text-xl font-bold tabular-nums ${s.accent}`}>{s.value}</div>
                                            {s.sub && <div className="text-xs text-gray-400 mt-0.5">{s.sub}</div>}
                                        </div>
                                    ))}
                                </div>

                                {/* Turnout bar */}
                                <div className="px-5 py-4 border-b border-gray-100 flex-shrink-0">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-xs font-medium text-gray-400 uppercase tracking-wide">Voter Turnout</span>
                                        <span className="text-sm font-bold text-gray-900 tabular-nums">{turnoutPct}%</span>
                                    </div>
                                    <div className="h-2 bg-gray-100 overflow-hidden rounded-sm">
                                        <div className="h-full bg-sky-500 rounded-sm transition-all duration-700" style={{ width: `${Math.min(100, parseFloat(turnoutPct))}%` }} />
                                    </div>
                                    <p className="text-xs text-gray-400 mt-1.5 tabular-nums">
                                        {totalCast.toLocaleString()} of {totalRegistered.toLocaleString()} registered voters
                                    </p>
                                </div>

                                {/* Certification progress */}
                                <div className="px-5 py-4 border-b border-gray-100 flex-shrink-0">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-xs font-medium text-gray-400 uppercase tracking-wide">Certification Progress</span>
                                        <span className={`text-sm font-bold tabular-nums ${certPct === 100 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                            {certPct}%
                                        </span>
                                    </div>
                                    <div className="h-2 bg-gray-100 overflow-hidden rounded-sm">
                                        <div
                                            className={`h-full rounded-sm transition-all duration-700 ${certPct === 100 ? 'bg-emerald-500' : 'bg-amber-500'}`}
                                            style={{ width: `${certPct}%` }}
                                        />
                                    </div>
                                    <p className="text-xs text-gray-400 mt-1.5 tabular-nums">
                                        {stationsCertified.toLocaleString()} of {totalStations.toLocaleString()} polling stations nationally certified
                                    </p>
                                </div>

                                {/* Navigation links */}
                                <div className="mt-auto px-5 py-4 space-y-2 flex-shrink-0 border-t border-gray-100">
                                    <Link
                                        href={`/results/map${param}`}
                                        className="flex items-center justify-between w-full px-3 py-2 text-sm text-gray-700 border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors group"
                                    >
                                        <span>Full Interactive Map</span>
                                        <span className="text-gray-400 group-hover:translate-x-0.5 transition-transform">→</span>
                                    </Link>
                                    <Link
                                        href={`/results/stations${param}`}
                                        className="flex items-center justify-between w-full px-3 py-2 text-sm text-gray-700 border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors group"
                                    >
                                        <span>Station-by-Station Results</span>
                                        <span className="text-gray-400 group-hover:translate-x-0.5 transition-transform">→</span>
                                    </Link>
                                </div>
                            </div>

                            {/* RIGHT: Embedded live map */}
                            <div className="lg:col-span-3 relative bg-slate-900 overflow-hidden" style={{ minHeight: '480px' }}>
                                <div className="absolute top-0 left-0 right-0 z-30 flex items-center justify-between px-4 h-9 bg-slate-900/98 border-b border-slate-800 flex-shrink-0">
                                    <div className="flex items-center gap-2">
                                        <span className={`w-2 h-2 rounded-full flex-shrink-0 ${mapLoading ? 'bg-amber-400 animate-pulse' : 'bg-emerald-400'}`} />
                                        <span className="text-xs font-semibold text-white">Live Polling Station Map</span>
                                        {!mapLoading && !mapError && (
                                            <span className="hidden sm:inline text-xs text-slate-500">· {mapStations.length.toLocaleString()} stations</span>
                                        )}
                                    </div>
                                    <Link href={`/results/map${param}`} className="text-xs text-slate-400 hover:text-white transition-colors">
                                        Expand →
                                    </Link>
                                </div>

                                {mapLoading && (
                                    <div className="absolute inset-0 z-20 flex items-center justify-center bg-slate-900/80">
                                        <div className="text-center">
                                            <div className="w-8 h-8 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
                                            <p className="text-xs text-slate-400">Loading station data…</p>
                                        </div>
                                    </div>
                                )}

                                {mapError && !mapLoading && (
                                    <div className="absolute inset-0 z-20 flex items-center justify-center bg-slate-900/80">
                                        <div className="text-center p-6">
                                            <p className="text-slate-400 text-sm mb-3">Map data unavailable</p>
                                            <Link href={`/results/map${param}`} className="text-xs font-semibold text-emerald-400 hover:text-emerald-300 underline">
                                                Open full map →
                                            </Link>
                                        </div>
                                    </div>
                                )}

                                <div className="absolute top-9 left-0 right-0 bottom-0">
                                    <LeafletMap stations={mapStations} height="100%" />
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
