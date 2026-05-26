import { Badge, Button, DataTable, PageHeader, Pagination } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function PartyRepresentatives({ auth, representatives = {}, flash }) {
    const [deletingId, setDeletingId] = useState(null);
    const rows = representatives.data ?? [];

    const handleDelete = (rep) => {
        if (!window.confirm(`Remove "${rep.user?.name || 'this representative'}" as a party representative? This cannot be undone.`)) return;
        setDeletingId(rep.id);
        router.delete(`/admin/party-representatives/${rep.id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    const columns = [
        {
            key: 'representative',
            header: 'Representative',
            render: (rep) => (
                <div>
                    <div className="font-semibold text-iec-navy">{rep.user?.name || 'Unassigned'}</div>
                    <div className="text-xs text-slate-500">{rep.user?.email || 'No email on record'}</div>
                </div>
            ),
        },
        {
            key: 'party',
            header: 'Party',
            render: (rep) => rep.political_party?.name || 'No party',
        },
        {
            key: 'designation',
            header: 'Designation',
            render: (rep) => rep.designation || 'Not set',
        },
        {
            key: 'accreditation',
            header: 'Accreditation',
            render: (rep) => <span className="font-mono text-xs text-slate-600">{rep.accreditation_number || '-'}</span>,
        },
        {
            key: 'stations',
            header: 'Stations',
            align: 'center',
            render: (rep) => rep.polling_stations?.length || 0,
        },
        {
            key: 'status',
            header: 'Status',
            render: (rep) => <Badge tone={rep.is_active ? 'teal' : 'rose'}>{rep.is_active ? 'Active' : 'Inactive'}</Badge>,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (rep) => (
                <div className="flex justify-end gap-2">
                    <Button href={`/admin/party-representatives/${rep.id}/edit`} variant="secondary">
                        Edit
                    </Button>
                    <Button
                        onClick={() => handleDelete(rep)}
                        disabled={deletingId === rep.id}
                        variant="danger"
                    >
                        {deletingId === rep.id ? 'Removing…' : 'Remove'}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Party Representatives"
                    description={`${representatives.total ?? rows.length} accredited representatives`}
                    actions={<Button href="/admin/party-representatives/create">Add Representative</Button>}
                />

                {flash?.success && (
                    <div className="mb-5 p-4 bg-green-50 border border-green-300 rounded-lg text-green-700 text-sm font-medium">
                        ✓ {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-5 p-4 bg-red-50 border border-red-300 rounded-lg text-red-700 text-sm font-medium">
                        ⚠ {flash.error}
                    </div>
                )}

                <DataTable
                    columns={columns}
                    rows={rows}
                    empty="No party representatives found"
                />
                <Pagination links={representatives.links} />
            </div>
        </AppLayout>
    );
}