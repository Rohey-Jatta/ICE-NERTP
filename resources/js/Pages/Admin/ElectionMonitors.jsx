import { Badge, Button, DataTable, PageHeader, Pagination } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { useState } from 'react';

const monitorType = (type) => (type ? type.replace(/_/g, ' ') : 'Not set');

export default function ElectionMonitors({ auth, monitors = {}, flash }) {
    const [deletingId, setDeletingId] = useState(null);
    const rows = monitors.data ?? [];

    const handleDelete = (monitor) => {
        if (!window.confirm(`Remove "${monitor.user?.name || 'this monitor'}" as an election monitor? This cannot be undone.`)) return;
        setDeletingId(monitor.id);
        router.delete(`/admin/election-monitors/${monitor.id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    const columns = [
        {
            key: 'monitor',
            header: 'Monitor',
            render: (monitor) => (
                <div>
                    <div className="font-semibold text-iec-navy">{monitor.user?.name || 'Unassigned'}</div>
                    <div className="text-xs text-slate-500">{monitor.user?.email || 'No email on record'}</div>
                </div>
            ),
        },
        {
            key: 'organization',
            header: 'Organization',
            render: (monitor) => monitor.organization || 'Independent',
        },
        {
            key: 'type',
            header: 'Type',
            render: (monitor) => <span className="capitalize">{monitorType(monitor.type)}</span>,
        },
        {
            key: 'accreditation',
            header: 'Accreditation',
            render: (monitor) => <span className="font-mono text-xs text-slate-600">{monitor.accreditation_number || '-'}</span>,
        },
        {
            key: 'stations',
            header: 'Stations',
            align: 'center',
            render: (monitor) => monitor.polling_stations?.length || 0,
        },
        {
            key: 'status',
            header: 'Status',
            render: (monitor) => <Badge tone={monitor.is_active ? 'teal' : 'rose'}>{monitor.is_active ? 'Active' : 'Inactive'}</Badge>,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (monitor) => (
                <div className="flex justify-end gap-2">
                    <Button href={`/admin/election-monitors/${monitor.id}/edit`} variant="secondary">
                        Edit
                    </Button>
                    <Button
                        onClick={() => handleDelete(monitor)}
                        disabled={deletingId === monitor.id}
                        variant="danger"
                    >
                        {deletingId === monitor.id ? 'Removing…' : 'Remove'}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Election Monitors"
                    description={`${monitors.total ?? rows.length} accredited monitors`}
                    actions={<Button href="/admin/election-monitors/create">Add Monitor</Button>}
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
                    empty="No election monitors found"
                />
                <Pagination links={monitors.links} />
            </div>
        </AppLayout>
    );
}