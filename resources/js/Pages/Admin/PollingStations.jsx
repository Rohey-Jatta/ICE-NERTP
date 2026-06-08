import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { Button, DataTable, Field, PageHeader, Panel, Toolbar, inputClass } from '@/Components/AdminUI';
import { useMemo, useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

const PAGE_SIZE = 25;

export default function PollingStations({ auth, stations = [] }) {
    const [deletingId, setDeletingId] = useState(null);
    const [search, setSearch] = useState('');
    const [ward, setWard] = useState('');
    const [page, setPage] = useState(1);

    const wards = useMemo(() => (
        [...new Set(stations.map((station) => station.ward).filter(Boolean))].sort()
    ), [stations]);

    const filteredStations = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return stations.filter((station) => {
            const matchesSearch = !needle || [station.code, station.name, station.ward]
                .join(' ')
                .toLowerCase()
                .includes(needle);
            const matchesWard = !ward || station.ward === ward;
            return matchesSearch && matchesWard;
        });
    }, [stations, search, ward]);

    const pageCount = Math.max(1, Math.ceil(filteredStations.length / PAGE_SIZE));
    const safePage = Math.min(page, pageCount);
    const visibleStations = filteredStations.slice((safePage - 1) * PAGE_SIZE, safePage * PAGE_SIZE);
    const totalVoters = filteredStations.reduce((sum, station) => sum + Number(station.voters || 0), 0);

    const resetFilters = () => {
        setSearch('');
        setWard('');
        setPage(1);
    };

    const handleDelete = (station) => {
        if (!window.confirm(`Delete polling station "${station.name}" (${station.code})? This action cannot be undone.`)) return;
        setDeletingId(station.id);
        router.delete(`/admin/polling-stations/${station.id}`, {
            preserveScroll: true,
            onError: (errors) => alert(errors?.error || 'Failed to delete polling station.'),
            onFinish: () => setDeletingId(null),
        });
    };

    const columns = [
        {
            key: 'station',
            header: 'Station',
            render: (station) => (
                <div>
                    <div className="ws-row-strong">{station.name}</div>
                    <div className="ws-row-mono mt-0.5">{station.code}</div>
                </div>
            ),
        },
        { key: 'ward', header: 'Ward', render: (station) => station.ward || '—' },
        {
            key: 'voters',
            header: 'Registered Voters',
            align: 'right',
            render: (station) => Number(station.voters || 0).toLocaleString(),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (station) => (
                <div className="flex justify-end gap-2">
                    <Button href={`/admin/polling-stations/${station.id}/edit`} variant="secondary">Edit</Button>
                    <Button variant="danger" disabled={deletingId === station.id} onClick={() => handleDelete(station)}>
                        {deletingId === station.id ? 'Deleting...' : 'Delete'}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container">
                <PageHeader
                    title="Polling Station Management"
                    description={`${filteredStations.length.toLocaleString()} stations, ${totalVoters.toLocaleString()} registered voters in current view`}
                    actions={<Button href="/admin/polling-stations/create">Register Station</Button>}
                />

                <Toolbar>
                    <Field label="Search">
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => { setSearch(event.target.value); setPage(1); }}
                            placeholder="Code, station, or ward"
                            className={inputClass}
                        />
                    </Field>
                    <Field label="Ward">
                        <SearchableSelect
                            value={ward}
                            onChange={(val) => { setWard(val); setPage(1); }}
                            options={[{ value: '', label: 'All wards' }, ...wards.map((wardName) => ({ value: wardName, label: wardName }))]}
                            placeholder="Select ward"
                            className="w-full"
                        />
                    </Field>
                    <div className="flex flex-col justify-end">
                        <span className="ws-label">Current page</span>
                        <div className="text-sm font-medium text-slate-700">
                            {visibleStations.length} of {filteredStations.length.toLocaleString()} stations
                        </div>
                    </div>
                    <div className="ws-toolbar-actions">
                        <Button variant="secondary" onClick={resetFilters} className="w-full">Reset Filters</Button>
                    </div>
                </Toolbar>

                <DataTable columns={columns} rows={visibleStations} empty="No polling stations match the current filters." />

                <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">Page {safePage} of {pageCount}</p>
                    <div className="flex gap-2">
                        <Button variant="secondary" disabled={safePage <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Previous</Button>
                        <Button variant="secondary" disabled={safePage >= pageCount} onClick={() => setPage((value) => Math.min(pageCount, value + 1))}>Next</Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
