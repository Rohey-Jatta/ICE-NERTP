import { Button, DataTable, PageHeader } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';

function Flash({ flash }) {
    if (!flash?.success && !flash?.error) return null;

    return (
        <div className={`mb-5 rounded-lg border p-4 text-sm ${flash.success ? 'border-teal-500/40 bg-iec-pink-500/10 text-iec-pink-600' : 'border-rose-500/40 bg-rose-500/10 text-rose-300'}`}>
            {flash.success || flash.error}
        </div>
    );
}

export default function Wards({ auth, wards = [], flash }) {
    const handleDelete = (ward) => {
        if (!window.confirm(`Delete ward "${ward.name}"? This cannot be undone.`)) return;

        router.delete(`/admin/hierarchy/wards/${ward.id}`, {
            preserveScroll: true,
            onError: (errors) => alert(errors?.error || 'Failed to delete ward.'),
        });
    };

    const columns = [
        {
            key: 'code',
            header: 'Code',
            render: (ward) => <span className="font-mono text-xs text-slate-600">{ward.code || '-'}</span>,
        },
        {
            key: 'name',
            header: 'Name',
            render: (ward) => <span className="font-semibold text-iec-navy">{ward.name}</span>,
        },
        {
            key: 'constituency',
            header: 'Constituency',
            render: (ward) => ward.parent_name || '-',
        },
        {
            key: 'area',
            header: 'Administrative Area',
            render: (ward) => ward.grandparent_name || '-',
        },
        {
            key: 'stations',
            header: 'Polling Stations',
            align: 'right',
            render: (ward) => ward.stations_count ?? 0,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (ward) => (
                <div className="flex justify-end gap-2">
                    <Button href={`/admin/hierarchy/wards/${ward.id}/edit`} variant="secondary">Edit</Button>
                    <Button
                        onClick={() => handleDelete(ward)}
                        variant="danger"
                        disabled={ward.stations_count > 0}
                    >
                        Delete
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Wards"
                    description="Ward records within constituencies and linked polling-station counts."
                    actions={<Button href="/admin/hierarchy/wards/create">Register Ward</Button>}
                />
                <Flash flash={flash} />
                <DataTable columns={columns} rows={wards} empty="No wards registered yet" />
            </div>
        </AppLayout>
    );
}
