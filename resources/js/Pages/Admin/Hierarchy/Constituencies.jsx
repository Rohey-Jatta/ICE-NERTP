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

export default function Constituencies({ auth, constituencies = [], flash }) {
    const handleDelete = (constituency) => {
        if (!window.confirm(`Delete constituency "${constituency.name}"? This cannot be undone.`)) return;

        router.delete(`/admin/hierarchy/constituencies/${constituency.id}`, {
            preserveScroll: true,
            onError: (errors) => alert(errors?.error || 'Failed to delete constituency.'),
        });
    };

    const columns = [
        {
            key: 'code',
            header: 'Code',
            render: (constituency) => <span className="font-mono text-xs text-slate-600">{constituency.code || '-'}</span>,
        },
        {
            key: 'name',
            header: 'Name',
            render: (constituency) => <span className="font-semibold text-iec-navy">{constituency.name}</span>,
        },
        {
            key: 'area',
            header: 'Administrative Area',
            render: (constituency) => constituency.parent_name || '-',
        },
        {
            key: 'wards',
            header: 'Wards',
            align: 'right',
            render: (constituency) => constituency.children_count ?? 0,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (constituency) => (
                <div className="flex justify-end gap-2">
                    <Button href={`/admin/hierarchy/constituencies/${constituency.id}/edit`} variant="secondary">Edit</Button>
                    <Button onClick={() => handleDelete(constituency)} variant="danger">Delete</Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Constituencies"
                    description="Constituency records grouped by administrative area."
                    actions={<Button href="/admin/hierarchy/constituencies/create">Register Constituency</Button>}
                />
                <Flash flash={flash} />
                <DataTable columns={columns} rows={constituencies} empty="No constituencies registered yet" />
            </div>
        </AppLayout>
    );
}
