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

export default function AdminAreas({ auth, adminAreas = [], flash }) {
    const handleDelete = (area) => {
        if (!window.confirm(`Delete admin area "${area.name}"? This cannot be undone.`)) return;

        router.delete(`/admin/hierarchy/admin-areas/${area.id}`, {
            preserveScroll: true,
            onError: (errors) => alert(errors?.error || 'Failed to delete admin area.'),
        });
    };

    const columns = [
        {
            key: 'code',
            header: 'Code',
            render: (area) => <span className="font-mono text-xs text-slate-600">{area.code || '-'}</span>,
        },
        {
            key: 'name',
            header: 'Name',
            render: (area) => <span className="font-semibold text-iec-navy">{area.name}</span>,
        },
        {
            key: 'election',
            header: 'Election',
            render: (area) => area.election_name || '-',
        },
        {
            key: 'constituencies',
            header: 'Constituencies',
            align: 'right',
            render: (area) => area.children_count ?? 0,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (area) => (
                <div className="flex justify-end gap-2">
                    <Button href={`/admin/hierarchy/admin-areas/${area.id}/edit`} variant="secondary">Edit</Button>
                    <Button onClick={() => handleDelete(area)} variant="danger">Delete</Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Administrative Areas"
                    description="Top-level administrative regions for election geography."
                    actions={<Button href="/admin/hierarchy/admin-areas/create">Register Area</Button>}
                />
                <Flash flash={flash} />
                <DataTable columns={columns} rows={adminAreas} empty="No administrative areas registered yet" />
            </div>
        </AppLayout>
    );
}
