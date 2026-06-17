import { useDeferredValue, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import SearchableSelect from '@/Components/SearchableSelect';
import { publicElectionTitle } from '@/Utils/publicElection';
import { RESULT_STATUS, RESULT_STATUS_PUBLIC_LABELS } from '@/Utils/resultStatus';

function numeric(value) {
    return Number(value || 0);
}

function firstColor(value, fallback = '#6b7280') {
    return (value || fallback).split(',')[0].trim();
}

// Collapse the fine-grained certification_status into the three public buckets.
// NOTE: this is purely STATUS-based now (not "do we have a total_votes_cast"),
// because vote figures, the result sheet, and party reactions are only ever
// sent by the backend once a result has been NATIONALLY CERTIFIED. A station
// can be "provisional" (in the pipeline) while still showing no figures.
function stationBucket(station) {
    if (station.status === RESULT_STATUS.NATIONALLY_CERTIFIED) return 'certified';
    if (!station.status || station.status === RESULT_STATUS.NOT_REPORTED) return 'pending';
    return 'provisional';
}

function stationTurnout(station) {
    if (station.total_votes_cast == null || numeric(station.registered_voters) === 0) return null;
    return ((numeric(station.total_votes_cast) / numeric(station.registered_voters)) * 100).toFixed(1);
}

function stationHierarchy(station) {
    return [station.admin_area_name, station.constituency_name, station.ward_name].filter(Boolean).join(' · ');
}

function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
}

const STATIONS_PAGE_SIZE = 40;

function sortStationsForBrowsing(a, b) {
    return compareText(a.admin_area_name, b.admin_area_name)
        || compareText(a.constituency_name, b.constituency_name)
        || compareText(a.ward_name, b.ward_name)
        || compareText(a.code, b.code)
        || compareText(a.name, b.name);
}

function buildRegionOptions(stations) {
    const counts = new Map();
    stations.forEach((station) => {
        const value = station.admin_area_name;
        if (!value) return;
        counts.set(value, (counts.get(value) || 0) + 1);
    });
    return Array.from(counts, ([value, count]) => ({ value, count })).sort((a, b) => compareText(a.value, b.value));
}

const BUCKET_CHIP = {
    certified: { label: 'Certified', cls: 'bg-[#e2f2ea] text-[#0e8c5a]', dot: 'bg-[#0e8c5a]' },
    provisional: { label: 'Provisional', cls: 'bg-[#fef3c7] text-[#b45309]', dot: 'bg-[#b45309]' },
    pending: { label: 'Pending', cls: 'bg-[#f5f6f8] text-[#5f6773]', dot: 'bg-[#8b95a3]' },
};

function StatusChip({ bucket }) {
    const c = BUCKET_CHIP[bucket] || BUCKET_CHIP.pending;
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${c.cls}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${c.dot}`} />
            {c.label}
        </span>
    );
}

// ── Filter pill ───────────────────────────────────────────────────────────────
function FilterPill({ active, onClick, children, count }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 text-[13px] font-medium transition ${
                active
                    ? 'border-[#0e1014] bg-[#0e1014] text-white'
                    : 'border-[#e6e8ec] bg-white text-[#1f2329] hover:border-[#353b45]'
            }`}
        >
            {children}
            {count != null && (
                <span className={`rounded-lg px-1.5 py-px text-[11px] ${active ? 'bg-white/15 text-white/80' : 'bg-[#f5f6f8] text-[#5f6773]'}`}>
                    {count}
                </span>
            )}
        </button>
    );
}

// ── Station list row ──────────────────────────────────────────────────────────
function StationItem({ station, isActive, onSelect }) {
    const bucket = stationBucket(station);
    const leader = station.candidate_votes?.[0] || null;
    const leaderPct = leader && numeric(station.valid_votes) > 0
        ? ((numeric(leader.votes) / numeric(station.valid_votes)) * 100).toFixed(1)
        : null;

    return (
        <button
            type="button"
            onClick={onSelect}
            className={`grid w-full grid-cols-[1fr_auto] items-center gap-3 border-b border-[#f1f3f5] px-7 py-4 text-left transition sm:grid-cols-[1fr_140px_96px_92px] ${
                isActive ? 'border-l-[3px] border-l-[#e61a6e] bg-[#fff5fa] pl-[25px]' : 'hover:bg-[#f8f9fb]'
            }`}
        >
            <div className="min-w-0">
                <div className="truncate text-[14.5px] font-semibold text-[#0e1014]">{station.name}</div>
                <div className="mt-0.5 flex items-center gap-1.5 truncate text-[12.5px] text-[#5f6773]">
                    <span className="truncate">{station.admin_area_name || 'Unassigned'}</span>
                    <span className="text-[#e6e8ec]">·</span>
                    <span className="font-mono text-[12px]">{station.code}</span>
                </div>
            </div>

            <div className="hidden min-w-0 items-center gap-2 text-[13px] sm:flex">
                {!leader ? (
                    <span className="text-[12px] text-[#5f6773]">—</span>
                ) : (
                    <>
                        <span className="h-7 w-1 flex-shrink-0 rounded-[2px]" style={{ backgroundColor: firstColor(leader.party_color) }} />
                        <div className="min-w-0">
                            <div className="truncate font-semibold text-[#1f2329]">{leader.party_abbr}</div>
                            {leaderPct && <div className="text-[11.5px] text-[#5f6773]">{leaderPct}%</div>}
                        </div>
                    </>
                )}
            </div>

            <div className="hidden text-[12px] tabular-nums text-[#5f6773] sm:block">
                {station.valid_votes == null ? '—' : `${numeric(station.valid_votes).toLocaleString()} valid`}
            </div>

            <div className="flex justify-end">
                <StatusChip bucket={bucket} />
            </div>
        </button>
    );
}

// ── Detail panel ──────────────────────────────────────────────────────────────
function CertificationTimeline({ bucket }) {
    const steps = [
        { title: 'Results submitted', sub: 'Polling officer files the count', state: bucket !== 'pending' ? 'done' : 'pending' },
        { title: 'Validation passed', sub: 'System checks math and signatures', state: bucket !== 'pending' ? 'done' : 'pending' },
        { title: 'Regional review', sub: 'Returning officers compare and sign', state: bucket === 'certified' ? 'done' : bucket === 'provisional' ? 'active' : 'pending' },
        { title: 'Nationally certified', sub: 'IEC certifies and publishes', state: bucket === 'certified' ? 'done' : 'pending' },
    ];

    return (
        <div className="flex flex-col gap-3.5">
            {steps.map((step) => (
                <div key={step.title} className="grid grid-cols-[14px_1fr] items-start gap-3 text-[13px]">
                    <span
                        className={`mt-1 h-2.5 w-2.5 rounded-full ${
                            step.state === 'done' ? 'bg-[#0e8c5a]' : step.state === 'active' ? 'bg-[#e61a6e]' : 'border border-[#e6e8ec] bg-[#f5f6f8]'
                        }`}
                    />
                    <div>
                        <b className="block text-[13px] font-semibold text-[#0e1014]">{step.title}</b>
                        <span className="text-[12px] text-[#5f6773]">{step.sub}</span>
                    </div>
                </div>
            ))}
        </div>
    );
}

function StationDetail({ station }) {
    const bucket = stationBucket(station);
    const turnout = stationTurnout(station);
    const hierarchy = stationHierarchy(station);
    const isCertified = !!station.is_certified;
    const hasFigures = station.total_votes_cast != null;
    const validVotes = numeric(station.valid_votes);
    const candidates = station.candidate_votes || [];
    const [imgError, setImgError] = useState(false);

    return (
        <>
            <div className="border-b border-[#e6e8ec] bg-white px-7 py-6">
                <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">
                    {station.admin_area_name || 'Polling Station'} · Polling Station
                </div>
                <h2 className="my-1.5 font-serif text-[26px] font-bold tracking-[-0.02em] text-[#0e1014]">{station.name}</h2>
                <div className="text-[13px] text-[#5f6773]">
                    <span className="font-mono">{station.code}</span>
                    {hierarchy && <> · {hierarchy}</>}
                </div>
                <div className="mt-3.5 flex flex-wrap items-center gap-2.5">
                    <StatusChip bucket={bucket} />
                    <span className="text-[12px] text-[#5f6773]">{RESULT_STATUS_PUBLIC_LABELS[station.status] || 'Status unavailable'}</span>
                </div>
            </div>

            {/* Result sheet photo — only present once nationally certified */}
            <div className="border-b border-[#e6e8ec] px-7 py-5">
                <h4 className="mb-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Result Sheet</h4>
                {isCertified && station.photo_url && !imgError ? (
                    <img
                        src={station.photo_url}
                        alt={`Result sheet for ${station.name}`}
                        onError={() => setImgError(true)}
                        className="w-full rounded-[10px] border border-[#e6e8ec] object-cover"
                    />
                ) : (
                    <div className="grid aspect-[16/9] w-full place-items-center rounded-[10px] border border-[#e6e8ec] bg-gradient-to-br from-[#f5f6f8] to-[#f1f3f5] text-[#8b95a3]">
                        <div className="text-center">
                            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" strokeWidth="1.5" className="mx-auto opacity-50">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <circle cx="9" cy="11" r="2" />
                                <path d="m21 16-5-5-7 7" />
                            </svg>
                            <div className="mt-2 text-[12px] font-medium">
                                {isCertified ? 'No photo on file' : 'Available once nationally certified'}
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Totals */}
            <div className="border-b border-[#e6e8ec] px-7 py-5">
                <h4 className="mb-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Totals</h4>
                <div className="grid grid-cols-3 gap-3.5">
                    <div>
                        <div className="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-[#5f6773]">Registered</div>
                        <div className="font-serif text-[22px] font-bold leading-none tracking-tight tabular-nums text-[#0e1014]">{numeric(station.registered_voters).toLocaleString()}</div>
                        <div className="mt-1 text-[11.5px] text-[#5f6773]">Voters</div>
                    </div>
                    <div>
                        <div className="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-[#5f6773]">Cast</div>
                        <div className="font-serif text-[22px] font-bold leading-none tracking-tight tabular-nums text-[#0e1014]">{hasFigures ? numeric(station.total_votes_cast).toLocaleString() : '—'}</div>
                        <div className="mt-1 text-[11.5px] text-[#5f6773]">{turnout ? `${turnout}% turnout` : 'Not yet published'}</div>
                    </div>
                    <div>
                        <div className="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-[#5f6773]">Valid</div>
                        <div className="font-serif text-[22px] font-bold leading-none tracking-tight tabular-nums text-[#0e8c5a]">{hasFigures ? validVotes.toLocaleString() : '—'}</div>
                        <div className="mt-1 text-[11.5px] text-[#5f6773]">{hasFigures ? `${numeric(station.rejected_votes).toLocaleString()} rejected` : ''}</div>
                    </div>
                </div>
            </div>

            {/* Candidate breakdown */}
            <div className="border-b border-[#e6e8ec] px-7 py-5">
                <h4 className="mb-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Candidate Breakdown</h4>
                {candidates.length === 0 ? (
                    <p className="m-0 text-[13px] leading-6 text-[#5f6773]">
                        {bucket === 'provisional'
                            ? 'This result is moving through the certification pipeline. Candidate totals, the result sheet, and party responses publish once the IEC Chairman certifies it nationally.'
                            : 'This station has not yet reported results. Candidate totals will appear once results are submitted and validated.'}
                    </p>
                ) : (
                    candidates.map((c, idx) => {
                        const cPct = validVotes > 0 ? ((numeric(c.votes) / validVotes) * 100).toFixed(2) : '0.00';
                        const color = firstColor(c.party_color);
                        return (
                            <div key={`${c.candidate_name}-${idx}`} className="border-b border-[#f1f3f5] py-2.5 last:border-b-0">
                                <div className="grid grid-cols-[1fr_auto] items-center gap-3">
                                    <div className="flex min-w-0 items-center gap-2.5 text-[13.5px]">
                                        <span className="h-[22px] w-1.5 flex-shrink-0 rounded-[2px]" style={{ backgroundColor: color }} />
                                        <div className="min-w-0">
                                            <b className="font-semibold text-[#0e1014]">{c.candidate_name}</b>
                                            <span className="ml-1 text-[11.5px] text-[#5f6773]">· {c.party_abbr}</span>
                                        </div>
                                    </div>
                                    <div className="text-right tabular-nums">
                                        <b className="text-[14px] font-bold text-[#0e1014]">{numeric(c.votes).toLocaleString()}</b>
                                        <span className="block text-[11.5px] text-[#5f6773]">{cPct}%</span>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {/* Party acceptances — only present once nationally certified */}
            {isCertified && station.party_acceptances?.length > 0 && (
                <div className="border-b border-[#e6e8ec] px-7 py-5">
                    <h4 className="mb-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Party Acceptances</h4>
                    <div className="flex flex-col gap-2">
                        {station.party_acceptances.map((pa, idx) => (
                            <div key={`${pa.party_abbr}-${idx}`} className="flex items-center justify-between gap-3 text-[13px]">
                                <span className="truncate font-semibold text-[#1f2329]">{pa.party_abbr} <span className="font-normal text-[#5f6773]">· {pa.party_name}</span></span>
                                <span className={`flex-shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ${
                                    pa.status === 'accepted' ? 'bg-[#e2f2ea] text-[#0e8c5a]' : pa.status === 'rejected' ? 'bg-[#fde8e8] text-[#b91c1c]' : 'bg-[#fef3c7] text-[#b45309]'
                                }`}>
                                    {pa.status}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Certification timeline */}
            <div className="px-7 py-5">
                <h4 className="mb-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Certification Status</h4>
                <CertificationTimeline bucket={bucket} />
            </div>
        </>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────
export default function ResultsStations({ election, elections = [], selectedElectionId, stations }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [regionFilter, setRegionFilter] = useState('all');
    const [selectedId, setSelectedId] = useState(null);
    const [visibleCount, setVisibleCount] = useState(STATIONS_PAGE_SIZE);
    const deferredSearchTerm = useDeferredValue(searchTerm);
    useInertiaPrefetch([`/results${param}`, `/results/map${param}`]);

    const stationList = stations || [];

    const regionOptions = useMemo(() => buildRegionOptions(stationList), [stationList]);

    const regionScoped = useMemo(() => {
        if (regionFilter === 'all') return stationList;
        return stationList.filter((s) => s.admin_area_name === regionFilter);
    }, [stationList, regionFilter]);

    const counts = useMemo(() => {
        const acc = { all: regionScoped.length, certified: 0, provisional: 0, pending: 0 };
        regionScoped.forEach((s) => { acc[stationBucket(s)] += 1; });
        return acc;
    }, [regionScoped]);

    const filteredStations = useMemo(() => {
        const query = deferredSearchTerm.trim().toLowerCase();
        return regionScoped.filter((s) => {
            const matchesStatus = statusFilter === 'all' || stationBucket(s) === statusFilter;
            if (!matchesStatus) return false;
            if (!query) return true;
            const haystack = [s.name, s.code, s.admin_area_name, s.constituency_name, s.ward_name]
                .filter(Boolean).join(' ').toLowerCase();
            return haystack.includes(query);
        }).slice().sort(sortStationsForBrowsing);
    }, [regionScoped, deferredSearchTerm, statusFilter]);

    const visibleStations = useMemo(
        () => filteredStations.slice(0, visibleCount),
        [filteredStations, visibleCount]
    );

    const selected = useMemo(() => {
        return filteredStations.find((s) => s.id === selectedId) || filteredStations[0] || null;
    }, [filteredStations, selectedId]);

    const resetPaging = () => setVisibleCount(STATIONS_PAGE_SIZE);
    const handleSearch = (value) => { setSearchTerm(value); resetPaging(); };
    const handleStatus = (value) => { setStatusFilter(value); resetPaging(); };
    const handleRegion = (value) => { setRegionFilter(value); resetPaging(); };

    if (!election) {
        return (
            <AppLayout>
                <div className="flex min-h-screen items-center justify-center bg-[#fafafb] p-8">
                    <div className="max-w-sm rounded-[14px] border border-[#e6e8ec] bg-white p-10 text-center">
                        <h1 className="text-xl font-bold text-[#0e1014]">No results available</h1>
                        <p className="mt-3 text-sm text-[#5f6773]">There is no active public election at this time.</p>
                        <Link href="/" className="mt-6 inline-flex rounded-md bg-[#e61a6e] px-5 py-3 text-sm font-bold text-white hover:bg-[#b81259]">
                            Back home
                        </Link>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="bg-white font-sans text-[#0e1014]">
                {/* Slim context bar — election title + selector */}
                <div className="border-b border-[#e6e8ec] bg-white">
                    <div className="mx-auto flex max-w-[1240px] flex-col gap-3 px-7 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <div className="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#e61a6e]">Polling Stations</div>
                            <h1 className="truncate font-serif text-[22px] font-bold tracking-[-0.015em] text-[#0e1014]">{publicElectionTitle(election)}</h1>
                        </div>
                        {elections.length > 1 && (
                            <SearchableSelect
                                value={String(selectedElectionId || '')}
                                onChange={(val) => router.get('/results/stations', { election: val }, { preserveScroll: false })}
                                options={elections.map((el) => ({ value: String(el.id), label: publicElectionTitle(el) }))}
                                placeholder="Select election"
                                className="w-full sm:w-72 text-sm"
                            />
                        )}
                    </div>
                </div>

                <div className="lg:grid lg:grid-cols-[1fr_480px]">
                    {/* ── List pane ─────────────────────────────────────────── */}
                    <div className="flex flex-col border-r border-[#e6e8ec] lg:h-[calc(100vh-68px)]">
                        <div className="sticky top-0 z-[5] flex flex-wrap items-center gap-3 border-b border-[#e6e8ec] bg-white px-7 py-[18px]">
                            <div className="relative min-w-[240px] flex-1">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="absolute left-3 top-1/2 -translate-y-1/2 text-[#8b95a3]">
                                    <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                                </svg>
                                <input
                                    type="search"
                                    value={searchTerm}
                                    onChange={(e) => handleSearch(e.target.value)}
                                    placeholder="Search by station name, code, ward…"
                                    className="w-full rounded-lg border border-[#e6e8ec] bg-[#f5f6f8] py-2.5 pl-9 pr-3.5 text-sm text-[#0e1014] outline-none transition focus:border-[#e61a6e] focus:bg-white"
                                />
                            </div>
                            <FilterPill active={statusFilter === 'all'} onClick={() => handleStatus('all')} count={counts.all}>All</FilterPill>
                            <FilterPill active={statusFilter === 'certified'} onClick={() => handleStatus('certified')} count={counts.certified}>Certified</FilterPill>
                            <FilterPill active={statusFilter === 'provisional'} onClick={() => handleStatus('provisional')} count={counts.provisional}>Provisional</FilterPill>
                            <FilterPill active={statusFilter === 'pending'} onClick={() => handleStatus('pending')} count={counts.pending}>Pending</FilterPill>
                        </div>

                        {regionOptions.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 px-7 pt-3.5">
                                <span className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Region</span>
                                <FilterPill active={regionFilter === 'all'} onClick={() => handleRegion('all')}>All regions</FilterPill>
                                {regionOptions.map((r) => (
                                    <FilterPill key={r.value} active={regionFilter === r.value} onClick={() => handleRegion(r.value)}>
                                        {r.value}
                                    </FilterPill>
                                ))}
                            </div>
                        )}

                        <div className="px-7 py-3.5 text-[13px] text-[#5f6773]">
                            Showing <b className="text-[#1f2329]">{filteredStations.length.toLocaleString()}</b> of {regionScoped.length.toLocaleString()} stations
                        </div>

                        <div className={`flex-1 lg:overflow-y-auto ${searchTerm !== deferredSearchTerm ? 'opacity-60' : ''}`}>
                            {filteredStations.length === 0 ? (
                                <div className="grid place-items-center px-8 py-16 text-center text-[#5f6773]">
                                    <h3 className="m-0 font-serif text-xl font-semibold text-[#1f2329]">No stations match</h3>
                                    <p className="mt-1.5 max-w-xs text-sm">Try clearing your search or removing filters.</p>
                                </div>
                            ) : (
                                <>
                                    {visibleStations.map((station) => (
                                        <StationItem
                                            key={station.id}
                                            station={station}
                                            isActive={selected?.id === station.id}
                                            onSelect={() => setSelectedId(station.id)}
                                        />
                                    ))}
                                    {filteredStations.length > visibleStations.length && (
                                        <div className="px-7 py-4">
                                            <button
                                                type="button"
                                                onClick={() => setVisibleCount((c) => c + STATIONS_PAGE_SIZE)}
                                                className="w-full rounded-lg border border-[#e6e8ec] bg-white py-2.5 text-sm font-semibold text-[#1f2329] transition hover:border-[#353b45]"
                                            >
                                                Show {Math.min(STATIONS_PAGE_SIZE, filteredStations.length - visibleStations.length).toLocaleString()} more
                                                <span className="ml-1.5 text-[#5f6773]">· {visibleStations.length.toLocaleString()} of {filteredStations.length.toLocaleString()}</span>
                                            </button>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    </div>

                    {/* ── Detail pane ───────────────────────────────────────── */}
                    <aside className="bg-[#fafafb] lg:h-[calc(100vh-68px)] lg:overflow-y-auto">
                        {selected ? (
                            <StationDetail station={selected} />
                        ) : (
                            <div className="grid h-full place-items-center px-8 py-16 text-center text-[#5f6773]">
                                <p className="text-sm">Select a station to view its full breakdown.</p>
                            </div>
                        )}
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}