import { useDeferredValue, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import LeafletMap from '@/Components/Map/LeafletMap';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { PublicElectionHeader } from '@/Components/PublicElectionHeader';
import { publicElectionTitle } from '@/Utils/publicElection';

const FILTERS = [
    { key: 'all', label: 'All' },
    { key: 'reported', label: 'Reported' },
    { key: 'certified', label: 'Certified' },
    { key: 'not_reported', label: 'Not reported' },
];

function numeric(value) {
    return Number(value || 0);
}

function stationCategory(station) {
    if (station.status === 'nationally_certified') return 'certified';
    if (station.status === 'not_reported' || station.total_votes_cast == null) return 'not_reported';
    return 'reported';
}

function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), undefined, {
        numeric: true,
        sensitivity: 'base',
    });
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

function MapFilters({
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
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="grid gap-3 lg:grid-cols-[1.2fr_0.9fr_0.9fr]">
                <label className="block">
                    <span className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Find location</span>
                    <input
                        type="search"
                        value={searchTerm}
                        onChange={(event) => setSearchTerm(event.target.value)}
                        placeholder="Station, code, ward, constituency"
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-iec-pink-300 focus:bg-white focus:ring-2 focus:ring-iec-pink-100"
                    />
                </label>

                <label className="block">
                    <span className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Region</span>
                    <select
                        value={selectedRegion}
                        onChange={(event) => setSelectedRegion(event.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-iec-pink-300 focus:ring-2 focus:ring-iec-pink-100"
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
                    <span className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Constituency</span>
                    <select
                        value={selectedConstituency}
                        onChange={(event) => setSelectedConstituency(event.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-iec-pink-300 focus:ring-2 focus:ring-iec-pink-100"
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
                        className={`flex min-w-0 items-center justify-between gap-2 rounded-md border px-3 py-2 text-xs font-bold transition sm:justify-start ${
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

export default function ResultsMap({ election, elections = [], selectedElectionId, stations = [] }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedRegion, setSelectedRegion] = useState('all');
    const [selectedConstituency, setSelectedConstituency] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const deferredSearchTerm = useDeferredValue(searchTerm);
    useInertiaPrefetch([`/results${param}`, `/results/stations${param}`]);

    const stationList = stations || [];
    const summary = useMemo(() => {
        const reported = stationList.filter((station) => stationCategory(station) !== 'not_reported');
        const certified = stationList.filter((station) => stationCategory(station) === 'certified');
        const notReported = stationList.filter((station) => stationCategory(station) === 'not_reported');
        const votesCast = reported.reduce((sum, station) => sum + numeric(station.total_votes_cast), 0);

        return {
            totalStations: stationList.length,
            reported: reported.length,
            certified: certified.length,
            notReported: notReported.length,
            votesCast,
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

    const locationCounts = useMemo(() => {
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
        });
    }, [locationFilteredStations, deferredSearchTerm, statusFilter]);

    const handleSearchTermChange = (value) => {
        setSearchTerm(value);
    };

    const handleRegionChange = (value) => {
        setSelectedRegion(value);
        setSelectedConstituency('all');
    };

    if (!election) {
        return (
            <AppLayout>
                <div className="bg-slate-50">
                    <PublicElectionHeader
                        title="Results map"
                        description="A polling station map will appear once an election is configured for public display."
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
                    basePath="/results/map"
                    title={publicElectionTitle(election)}
                    description="Explore polling station reporting by geography, region, constituency, and certification status."
                />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <SummaryStat label="Mapped stations" value={summary.totalStations.toLocaleString()} />
                        <SummaryStat label="Currently shown" value={filteredStations.length.toLocaleString()} accent="text-iec-pink-600" />
                        <SummaryStat label="Reported" value={summary.reported.toLocaleString()} accent="text-emerald-600" />
                        <SummaryStat label="Certified" value={summary.certified.toLocaleString()} accent="text-green-700" />
                        <SummaryStat label="Votes cast" value={summary.votesCast.toLocaleString()} helper={`${summary.notReported.toLocaleString()} not reported`} />
                    </div>

                    <div className="mt-6">
                        <MapFilters
                            searchTerm={searchTerm}
                            setSearchTerm={handleSearchTermChange}
                            selectedRegion={selectedRegion}
                            setSelectedRegion={handleRegionChange}
                            selectedConstituency={selectedConstituency}
                            setSelectedConstituency={setSelectedConstituency}
                            regionOptions={regionOptions}
                            constituencyOptions={constituencyOptions}
                            totalStations={summary.totalStations}
                            constituencyScopeCount={regionScopedStations.length}
                            statusFilter={statusFilter}
                            setStatusFilter={setStatusFilter}
                            counts={locationCounts}
                        />
                    </div>

                    <div className="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        <div className="mb-3 flex flex-col gap-1 px-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 className="text-xl font-extrabold text-slate-950">Geographic coverage</h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Showing {filteredStations.length.toLocaleString()} of {stationList.length.toLocaleString()} mapped polling stations.
                                </p>
                            </div>
                            <p className="text-xs font-semibold text-slate-400">
                                Select a marker to inspect station totals and candidate votes.
                            </p>
                        </div>
                        <LeafletMap stations={filteredStations} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
