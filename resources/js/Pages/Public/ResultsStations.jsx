import { useDeferredValue, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { PublicElectionHeader } from '@/Components/PublicElectionHeader';

const STATUS_CONFIG = {
    nationally_certified: { label: 'Certified', bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-700' },
    admin_area_certified: { label: 'Area certified', bg: 'bg-sky-50', border: 'border-sky-200', text: 'text-sky-700' },
    constituency_certified: { label: 'Constituency certified', bg: 'bg-cyan-50', border: 'border-cyan-200', text: 'text-cyan-700' },
    ward_certified: { label: 'Ward certified', bg: 'bg-indigo-50', border: 'border-indigo-200', text: 'text-indigo-700' },
    pending_national: { label: 'Pending', bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700' },
    pending_admin_area: { label: 'Pending', bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700' },
    pending_constituency: { label: 'Pending', bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700' },
    pending_ward: { label: 'Pending', bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700' },
    pending_party_acceptance: { label: 'Pending', bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700' },
    submitted: { label: 'Submitted', bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700' },
    rejected: { label: 'Returned', bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700' },
    not_reported: { label: 'Not reported', bg: 'bg-slate-50', border: 'border-slate-200', text: 'text-slate-600' },
};

const FILTERS = [
    { key: 'all', label: 'All' },
    { key: 'reported', label: 'Reported' },
    { key: 'certified', label: 'Certified' },
    { key: 'not_reported', label: 'Not reported' },
];

const STATIONS_PAGE_SIZE = 20;

function numeric(value) {
    return Number(value || 0);
}

function stationCategory(station) {
    if (station.status === 'nationally_certified') return 'certified';
    if (station.status === 'not_reported' || station.total_votes_cast == null) return 'not_reported';
    return 'reported';
}

function stationTurnout(station) {
    if (station.total_votes_cast == null || numeric(station.registered_voters) === 0) return null;
    return ((numeric(station.total_votes_cast) / numeric(station.registered_voters)) * 100).toFixed(1);
}

function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), undefined, {
        numeric: true,
        sensitivity: 'base',
    });
}

function sortStationsForBrowsing(a, b) {
    return compareText(a.admin_area_name, b.admin_area_name)
        || compareText(a.constituency_name, b.constituency_name)
        || compareText(a.ward_name, b.ward_name)
        || compareText(a.code, b.code)
        || compareText(a.name, b.name);
}

function buildLocationOptions(stations, fieldName) {
    const counts = new Map();

    stations.forEach((station) => {
        const value = station[fieldName];
        if (!value) return;
        counts.set(value, (counts.get(value) || 0) + 1);
    });

    return Array.from(counts, ([value, count]) => ({ value, count }))
        .sort((a, b) => compareText(a.value, b.value));
}

function SummaryStat({ label, value, accent = 'text-slate-950', helper }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className={`text-2xl font-extrabold ${accent}`}>{value}</div>
            <div className="mt-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">{label}</div>
            {helper && <div className="mt-1 text-xs font-medium text-slate-400">{helper}</div>}
        </div>
    );
}

function StatusPill({ status }) {
    const statusInfo = STATUS_CONFIG[status] || STATUS_CONFIG.not_reported;
    return (
        <span className={`inline-flex rounded-md border px-2.5 py-1 text-xs font-bold ${statusInfo.bg} ${statusInfo.border} ${statusInfo.text}`}>
            {statusInfo.label}
        </span>
    );
}

function StationRow({ station }) {
    const turnout = stationTurnout(station);
    const hierarchy = [station.admin_area_name, station.constituency_name, station.ward_name].filter(Boolean).join(' / ');

    return (
        <article className="w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all [contain-intrinsic-size:0_340px] [content-visibility:auto] hover:border-slate-300">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                <div className="min-w-0">
                    <h3 className="text-sm font-extrabold leading-snug text-slate-950 sm:truncate">{station.name}</h3>
                    <p className="mt-1 break-words text-xs leading-relaxed text-slate-500 sm:truncate">
                        <span className="font-mono">{station.code}</span>
                        {hierarchy && <> | {hierarchy}</>}
                    </p>
                </div>
                <div className="shrink-0">
                    <StatusPill status={station.status} />
                </div>
            </div>

            <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                <div className="min-w-0 rounded-lg bg-slate-50 p-2">
                    <div className="text-sm font-extrabold text-slate-950">{numeric(station.total_votes_cast).toLocaleString()}</div>
                    <div className="text-[0.65rem] font-bold uppercase tracking-wide text-slate-400">Cast</div>
                </div>
                <div className="min-w-0 rounded-lg bg-slate-50 p-2">
                    <div className="text-sm font-extrabold text-emerald-600">{numeric(station.valid_votes).toLocaleString()}</div>
                    <div className="text-[0.65rem] font-bold uppercase tracking-wide text-slate-400">Valid</div>
                </div>
                <div className="min-w-0 rounded-lg bg-slate-50 p-2">
                    <div className="text-sm font-extrabold text-sky-700">{turnout ? `${turnout}%` : '-'}</div>
                    <div className="text-[0.65rem] font-bold uppercase tracking-wide text-slate-400">Turnout</div>
                </div>
            </div>

            {station.candidate_votes?.length > 0 && (
                <div className="mt-4 border-t border-slate-100 pt-3">
                    <div className="mb-2 text-[0.65rem] font-bold uppercase tracking-[0.14em] text-slate-400">
                        Candidate results
                    </div>
                    <CandidateBars station={station} compact />
                </div>
            )}
        </article>
    );
}

function CandidateBars({ station, compact = false }) {
    const totalVotes = numeric(station?.valid_votes);
    const candidates = station?.candidate_votes || [];

    if (candidates.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                Candidate totals are not available for this station yet.
            </div>
        );
    }

    return (
        <div className={compact ? 'space-y-2.5' : 'space-y-4'}>
            {candidates.map((candidate, idx) => {
                const pct = totalVotes > 0 ? ((numeric(candidate.votes) / totalVotes) * 100).toFixed(1) : '0.0';
                const color = (candidate.party_color || '#64748b').split(',')[0].trim();

                return (
                    <div key={`${candidate.candidate_name}-${idx}`}>
                        <div className={`${compact ? 'mb-1' : 'mb-1.5'} flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3`}>
                            <div className="flex min-w-0 items-center gap-2">
                                <span className={`${compact ? 'h-2 w-2' : 'h-2.5 w-2.5'} flex-shrink-0 rounded-full`} style={{ backgroundColor: color }} />
                                <span className={`truncate font-bold text-slate-900 ${compact ? 'text-xs' : 'text-sm'}`}>{candidate.candidate_name}</span>
                                <span className="flex-shrink-0 text-xs font-semibold text-slate-400">{candidate.party_abbr}</span>
                            </div>
                            <div className="flex flex-shrink-0 items-baseline justify-between gap-2 sm:justify-start">
                                <span className={`${compact ? 'text-xs' : 'text-sm'} font-extrabold text-slate-950`}>{numeric(candidate.votes).toLocaleString()}</span>
                                <span className="text-xs text-slate-500">{pct}%</span>
                            </div>
                        </div>
                        <div className={`${compact ? 'h-1.5' : 'h-2'} overflow-hidden rounded-full bg-slate-100`}>
                            <div className="h-full rounded-full" style={{ width: `${pct}%`, backgroundColor: color }} />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function EmptyStationsState() {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <h3 className="text-lg font-extrabold text-slate-950">No stations match these filters</h3>
            <p className="mt-2 text-sm text-slate-500">Try a different search term or status filter.</p>
        </div>
    );
}

function StationSearch({
    searchTerm,
    setSearchTerm,
    selectedRegion,
    setSelectedRegion,
    selectedConstituency,
    setSelectedConstituency,
    regionOptions,
    constituencyOptions,
    totalStations,
    constituencyScopeCount,
    statusFilter,
    setStatusFilter,
    counts,
}) {
    return (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="grid gap-3">
                <label className="block text-xs font-bold uppercase tracking-[0.16em] text-slate-500" htmlFor="station-search">
                    Find station
                </label>
                <input
                    id="station-search"
                    type="search"
                    value={searchTerm}
                    onChange={(event) => setSearchTerm(event.target.value)}
                    placeholder="Name, code, ward, constituency"
                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-iec-pink-300 focus:bg-white focus:ring-2 focus:ring-iec-pink-100"
                />
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <label className="block">
                    <span className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-slate-500">Region</span>
                    <select
                        value={selectedRegion}
                        onChange={(event) => setSelectedRegion(event.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none transition focus:border-iec-pink-300 focus:ring-2 focus:ring-iec-pink-100"
                    >
                        <option value="all">All regions ({totalStations})</option>
                        {regionOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.value} ({option.count})
                            </option>
                        ))}
                    </select>
                </label>

                <label className="block">
                    <span className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-slate-500">Constituency</span>
                    <select
                        value={selectedConstituency}
                        onChange={(event) => setSelectedConstituency(event.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none transition focus:border-iec-pink-300 focus:ring-2 focus:ring-iec-pink-100"
                    >
                        <option value="all">All ({constituencyScopeCount})</option>
                        {constituencyOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.value} ({option.count})
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                {FILTERS.map((filter) => (
                    <button
                        key={filter.key}
                        type="button"
                        onClick={() => setStatusFilter(filter.key)}
                        className={`flex min-w-0 items-center justify-between gap-2 rounded-md border px-3 py-2 text-xs font-bold transition sm:justify-start sm:py-1.5 ${
                            statusFilter === filter.key
                                ? 'border-iec-pink-500 bg-iec-pink-500 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-iec-pink-200 hover:text-iec-pink-600'
                        }`}
                    >
                        <span className="truncate">{filter.label}</span>
                        <span className="shrink-0 opacity-75">{counts[filter.key] ?? 0}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}

function StationListFooter({ visibleCount, totalCount, onLoadMore }) {
    if (totalCount <= visibleCount) {
        return null;
    }

    const nextCount = Math.min(STATIONS_PAGE_SIZE, totalCount - visibleCount);

    return (
        <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-center">
            <p className="text-sm font-semibold text-slate-600">
                Showing {visibleCount.toLocaleString()} of {totalCount.toLocaleString()} matching stations.
            </p>
            <button
                type="button"
                onClick={onLoadMore}
                className="mt-3 inline-flex w-full justify-center rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-iec-pink-300 hover:text-iec-pink-600 sm:w-auto"
            >
                Show {nextCount.toLocaleString()} more
            </button>
        </div>
    );
}

export default function ResultsStations({
    election,
    elections = [],
    selectedElectionId,
    stations,
    isPublished = false,
}) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedRegion, setSelectedRegion] = useState('all');
    const [selectedConstituency, setSelectedConstituency] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [visibleStationCount, setVisibleStationCount] = useState(STATIONS_PAGE_SIZE);
    const deferredSearchTerm = useDeferredValue(searchTerm);
    useInertiaPrefetch([`/results${param}`, `/results/map${param}`]);

    const stationList = stations || [];
    const summary = useMemo(() => {
        const reported = stationList.filter((station) => stationCategory(station) !== 'not_reported');
        const certified = stationList.filter((station) => stationCategory(station) === 'certified');
        const notReported = stationList.filter((station) => stationCategory(station) === 'not_reported');
        const totalRegistered = stationList.reduce((sum, station) => sum + numeric(station.registered_voters), 0);
        const votesCast = reported.reduce((sum, station) => sum + numeric(station.total_votes_cast), 0);
        const validVotes = reported.reduce((sum, station) => sum + numeric(station.valid_votes), 0);
        const turnout = totalRegistered > 0 ? ((votesCast / totalRegistered) * 100).toFixed(1) : '0.0';

        return {
            totalStations: stationList.length,
            reported: reported.length,
            certified: certified.length,
            notReported: notReported.length,
            totalRegistered,
            votesCast,
            validVotes,
            turnout,
        };
    }, [stationList]);

    const regionOptions = useMemo(
        () => buildLocationOptions(stationList, 'admin_area_name'),
        [stationList]
    );

    const regionScopedStations = useMemo(() => {
        if (selectedRegion === 'all') return stationList;
        return stationList.filter((station) => station.admin_area_name === selectedRegion);
    }, [stationList, selectedRegion]);

    const constituencyOptions = useMemo(
        () => buildLocationOptions(regionScopedStations, 'constituency_name'),
        [regionScopedStations]
    );

    const locationFilteredStations = useMemo(() => {
        return regionScopedStations.filter((station) => (
            selectedConstituency === 'all' || station.constituency_name === selectedConstituency
        ));
    }, [regionScopedStations, selectedConstituency]);

    const locationSummary = useMemo(() => {
        const reported = locationFilteredStations.filter((station) => stationCategory(station) !== 'not_reported');
        const certified = locationFilteredStations.filter((station) => stationCategory(station) === 'certified');
        const notReported = locationFilteredStations.filter((station) => stationCategory(station) === 'not_reported');

        return {
            all: locationFilteredStations.length,
            reported: reported.length,
            certified: certified.length,
            not_reported: notReported.length,
        };
    }, [locationFilteredStations]);

    const filterCounts = {
        all: locationSummary.all,
        reported: locationSummary.reported,
        certified: locationSummary.certified,
        not_reported: locationSummary.not_reported,
    };

    const filteredStations = useMemo(() => {
        const query = deferredSearchTerm.trim().toLowerCase();
        return locationFilteredStations.filter((station) => {
            const category = stationCategory(station);
            const matchesStatus = statusFilter === 'all' || category === statusFilter;
            const haystack = [
                station.name,
                station.code,
                station.admin_area_name,
                station.constituency_name,
                station.ward_name,
            ].filter(Boolean).join(' ').toLowerCase();
            return matchesStatus && (!query || haystack.includes(query));
        }).slice().sort(sortStationsForBrowsing);
    }, [locationFilteredStations, deferredSearchTerm, statusFilter]);

    const visibleStations = useMemo(
        () => filteredStations.slice(0, visibleStationCount),
        [filteredStations, visibleStationCount]
    );

    const handleSearchTermChange = (value) => {
        setSearchTerm(value);
        setVisibleStationCount(STATIONS_PAGE_SIZE);
    };

    const handleRegionChange = (value) => {
        setSelectedRegion(value);
        setSelectedConstituency('all');
        setVisibleStationCount(STATIONS_PAGE_SIZE);
    };

    const handleConstituencyChange = (value) => {
        setSelectedConstituency(value);
        setVisibleStationCount(STATIONS_PAGE_SIZE);
    };

    const handleStatusFilterChange = (value) => {
        setStatusFilter(value);
        setVisibleStationCount(STATIONS_PAGE_SIZE);
    };

    const handleLoadMoreStations = () => {
        setVisibleStationCount((count) => Math.min(count + STATIONS_PAGE_SIZE, filteredStations.length));
    };

    if (!election) {
        return (
            <AppLayout>
                <div className="bg-slate-50">
                    <PublicElectionHeader
                        title="Polling stations"
                        description="Station status will appear once an election is configured for public display."
                    />
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <div className="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                            <h1 className="text-2xl font-bold text-slate-950">No results available</h1>
                            <p className="mt-3 text-slate-600">There is no active public election at this time.</p>
                            <Link
                                href="/"
                                className="mt-6 inline-flex rounded-md bg-iec-pink-600 px-5 py-3 text-sm font-bold text-white hover:bg-iec-pink-700"
                            >
                                Back home
                            </Link>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="bg-slate-50">
                <PublicElectionHeader
                    election={election}
                    elections={elections}
                    selectedElectionId={selectedElectionId}
                    basePath="/results/stations"
                    description="Search station-level reporting, inspect vote totals, and review candidate distribution for the selected election."
                />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                        <SummaryStat label="Total stations" value={summary.totalStations.toLocaleString()} />
                        <SummaryStat label="Reported" value={summary.reported.toLocaleString()} accent="text-emerald-600" />
                        <SummaryStat label="Certified" value={summary.certified.toLocaleString()} accent="text-green-700" />
                        <SummaryStat label="Not reported" value={summary.notReported.toLocaleString()} accent="text-amber-600" />
                        <SummaryStat label="Votes cast" value={summary.votesCast.toLocaleString()} helper={`${summary.validVotes.toLocaleString()} valid`} />
                        <SummaryStat label="Turnout" value={`${summary.turnout}%`} accent="text-sky-700" helper={`${summary.totalRegistered.toLocaleString()} registered`} />
                    </div>

                    {!isPublished && (
                        <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-center text-sm font-semibold text-amber-800">
                            Displaying provisional station status. Photos and party representative decisions will be visible once results are officially published.
                        </div>
                    )}

                    <section className="mt-6 min-w-0 space-y-5">
                        <div className="min-w-0">
                            <StationSearch
                                searchTerm={searchTerm}
                                setSearchTerm={handleSearchTermChange}
                                selectedRegion={selectedRegion}
                                setSelectedRegion={handleRegionChange}
                                selectedConstituency={selectedConstituency}
                                setSelectedConstituency={handleConstituencyChange}
                                regionOptions={regionOptions}
                                constituencyOptions={constituencyOptions}
                                totalStations={summary.totalStations}
                                constituencyScopeCount={regionScopedStations.length}
                                statusFilter={statusFilter}
                                setStatusFilter={handleStatusFilterChange}
                                counts={filterCounts}
                            />
                        </div>

                        <div className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                            <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between sm:gap-3">
                                <div className="min-w-0">
                                    <h2 className="text-xl font-extrabold text-slate-950">Polling stations</h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {Math.min(visibleStationCount, filteredStations.length).toLocaleString()} of {filteredStations.length.toLocaleString()} matching stations shown.
                                    </p>
                                    <p className="mt-1 text-xs font-semibold text-slate-400">
                                        Sorted by region, constituency, ward, then station code.
                                    </p>
                                </div>
                            </div>

                            <div className={searchTerm !== deferredSearchTerm ? 'opacity-60' : ''}>
                                {filteredStations.length === 0 ? (
                                    <EmptyStationsState />
                                ) : (
                                    <>
                                        <div className="grid min-w-0 grid-cols-1 gap-3 xl:grid-cols-2">
                                            {visibleStations.map((station) => (
                                                <StationRow
                                                    key={station.id}
                                                    station={station}
                                                />
                                            ))}
                                        </div>
                                        <div className="mt-3">
                                            <StationListFooter
                                                visibleCount={visibleStations.length}
                                                totalCount={filteredStations.length}
                                                onLoadMore={handleLoadMoreStations}
                                            />
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
