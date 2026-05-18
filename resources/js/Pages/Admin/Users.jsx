import AppLayout from '@/Layouts/AppLayout';
import { router, useForm } from '@inertiajs/react';
import { Button, Badge, DataTable, Field, PageHeader, Pagination, Toolbar, inputClass, roleLabel } from '@/Components/AdminUI';
import { useState } from 'react';
import { can } from '@/Utils/permissions';

const ROLE_OPTIONS = [
    'iec-administrator',
    'iec-chairman',
    'admin-area-approver',
    'constituency-approver',
    'ward-approver',
    'polling-officer',
    'party-representative',
    'election-monitor',
];

const statusTone = (status) => {
    if (status === 'active') return 'teal';
    if (status === 'suspended') return 'rose';
    if (status === 'inactive') return 'amber';
    return 'slate';
};

export default function Users({ auth, users = {}, filters = {} }) {
    const [deletingId, setDeletingId] = useState(null);
    const currentUser = auth?.user;
    const canAssignRoles = can(currentUser, 'assign-roles');
    const canDeleteUsers = can(currentUser, 'deactivate-user');
    const userData = users.data ?? [];

    const { data, setData, get, processing } = useForm({
        search: filters.search || '',
        role: filters.role || '',
        status: filters.status || '',
    });

    const applyFilters = (event) => {
        event.preventDefault();
        get('/admin/users', { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        router.get('/admin/users', {}, { preserveState: false, replace: true });
    };

    const handleDelete = (user) => {
        if (!window.confirm(`Delete user "${user.name}"? This cannot be undone.`)) return;
        setDeletingId(user.id);
        router.delete(`/admin/users/${user.id}`, {
            preserveScroll: true,
            onError: () => alert('Failed to delete user. Please try again.'),
            onFinish: () => setDeletingId(null),
        });
    };

    const columns = [
        {
            key: 'name',
            header: 'User',
            render: (user) => (
                <div>
                    <div className="ws-row-strong">{user.name}</div>
                    <div className="ws-row-muted mt-0.5">ID {user.id}</div>
                </div>
            ),
        },
        {
            key: 'role',
            header: 'Role',
            render: (user) => <Badge tone="blue">{roleLabel(user.roles?.[0]?.name)}</Badge>,
        },
        {
            key: 'email',
            header: 'Email',
            render: (user) => user.email,
        },
        {
            key: 'phone',
            header: 'Phone',
            render: (user) => <span className="ws-row-mono">{user.phone || '—'}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (user) => <Badge tone={statusTone(user.status)}>{roleLabel(user.status)}</Badge>,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (user) => (
                <div className="flex justify-end gap-2">
                        <Button onClick={() => handleEdit(user.id)} variant="secondary">Edit</Button>

                        <Button onClick={() => handleDelete(user)} disabled={deletingId === user.id} variant="danger">
                        {deletingId === user.id ? 'Deleting...' : 'Delete'}
                        </Button>

                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container">
                <PageHeader
                    title="User Management"
                    description={`${users.total ?? userData.length} users across all roles`}
                    actions={<Button href="/admin/users/create">Add New User</Button>}
                />

                <form onSubmit={applyFilters}>
                    <Toolbar>
                        <Field label="Search">
                            <input
                                type="search"
                                value={data.search}
                                onChange={(event) => setData('search', event.target.value)}
                                placeholder="Name, email, or phone"
                                className={inputClass}
                            />
                        </Field>
                        <Field label="Role">
                            <select value={data.role} onChange={(event) => setData('role', event.target.value)} className={inputClass}>
                                <option value="">All roles</option>
                                {ROLE_OPTIONS.map((role) => <option key={role} value={role}>{roleLabel(role)}</option>)}
                            </select>
                        </Field>
                        <Field label="Status">
                            <select value={data.status} onChange={(event) => setData('status', event.target.value)} className={inputClass}>
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </Field>
                        <div className="flex items-end gap-2">
                            <Button type="submit" disabled={processing} className="flex-1">{processing ? 'Applying...' : 'Apply'}</Button>
                            <Button variant="secondary" onClick={clearFilters}>Clear</Button>
                        </div>
                    </Toolbar>
                </form>

                <DataTable columns={columns} rows={userData} empty="No users match the current filters." />
                <Pagination links={users.links} />
            </div>
        </AppLayout>
    );
}
