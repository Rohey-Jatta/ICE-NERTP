import { Badge, Button, DataTable, PageHeader, Pagination } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';

export default function PartyRepresentatives({ auth, representatives = {} }) {
    const rows = representatives.data ?? [];

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
                <Button href={`/admin/party-representatives/${rep.id}/edit`} variant="secondary">
                    Edit
                </Button>
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
