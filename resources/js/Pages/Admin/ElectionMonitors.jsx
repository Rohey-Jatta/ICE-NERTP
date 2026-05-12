import { Badge, Button, DataTable, PageHeader, Pagination } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';

const monitorType = (type) => (type ? type.replace(/_/g, ' ') : 'Not set');

export default function ElectionMonitors({ auth, monitors = {} }) {
    const rows = monitors.data ?? [];

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
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Election Monitors"
                    description={`${monitors.total ?? rows.length} accredited monitors`}
                    actions={<Button href="/admin/election-monitors/create">Add Monitor</Button>}
                />

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
