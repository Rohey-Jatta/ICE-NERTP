import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const ROLE_DISPLAY = {
    'iec-administrator':     { label: 'IEC Administrator',     color: 'bg-teal-500/20 text-teal-300 border border-teal-500/30' },
    'iec-chairman':          { label: 'IEC Chairman',          color: 'bg-red-500/20 text-red-300 border border-red-500/30' },
    'admin-area-approver':   { label: 'Admin Area Approver',   color: 'bg-orange-500/20 text-orange-300 border border-orange-500/30' },
    'constituency-approver': { label: 'Constituency Approver', color: 'bg-purple-500/20 text-purple-300 border border-purple-500/30' },
    'ward-approver':         { label: 'Ward Approver',         color: 'bg-pink-500/20 text-pink-300 border border-pink-500/30' },
    'polling-officer':       { label: 'Polling Officer',       color: 'bg-blue-500/20 text-blue-300 border border-blue-500/30' },
    'party-representative':  { label: 'Party Representative',  color: 'bg-green-500/20 text-green-300 border border-green-500/30' },
    'election-monitor':      { label: 'Election Monitor',      color: 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30' },
};

const ROLE_ORDER = [
    'iec-administrator',
    'iec-chairman',
    'admin-area-approver',
    'constituency-approver',
    'ward-approver',
    'polling-officer',
    'party-representative',
    'election-monitor',
];

export default function Users({ auth, users = [] }) {
    const [deletingId, setDeletingId] = useState(null);

    const handleAddUser = () => router.visit('/admin/users/create');
    const handleEdit = (id) => router.visit(`/admin/users/${id}/edit`);

    const handleDelete = (user) => {
        if (!window.confirm(`Are you sure you want to delete user "${user.name}"? This cannot be undone.`)) return;
        setDeletingId(user.id);
        router.delete(`/admin/users/${user.id}`, {
            preserveScroll: true,
            onSuccess:  () => setDeletingId(null),
            onError:    () => { setDeletingId(null); alert('Failed to delete user. Please try again.'); },
            onFinish:   () => setDeletingId(null),
        });
    };

    const getRoleInfo = (user) => {
        if (user.roles && user.roles.length > 0) {
            const roleName = user.roles[0].name;
            return ROLE_DISPLAY[roleName] ?? {
                label: roleName.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                color: 'bg-gray-500/20 text-gray-300 border border-gray-500/30',
            };
        }
        return { label: 'No Role', color: 'bg-gray-500/20 text-gray-400 border border-gray-500/30' };
    };

    const getStatusColor = (status) => {
        switch (status) {
            case 'active':    return 'bg-green-500/20 text-green-300';
            case 'inactive':  return 'bg-yellow-500/20 text-yellow-300';
            case 'suspended': return 'bg-red-500/20 text-red-300';
            default:          return 'bg-gray-500/20 text-gray-300';
        }
    };

    // Group users by role for visual separation
    const userData = users.data ?? [];
    const groupedUsers = ROLE_ORDER.reduce((acc, role) => {
        const group = userData.filter(u => u.roles?.[0]?.name === role);
        if (group.length > 0) acc.push({ role, users: group });
        return acc;
    }, []);
    // Users with no recognised role
    const ungrouped = userData.filter(u => !ROLE_ORDER.includes(u.roles?.[0]?.name));
    if (ungrouped.length > 0) groupedUsers.push({ role: null, users: ungrouped });

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-white">User Management</h1>
                        <p className="text-gray-400 text-sm mt-1">
                            showing {userData.length} user{userData.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <button type="button" onClick={handleAddUser}
                        className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg cursor-pointer">
                        Add New User
                    </button>
                </div>

                {userData.length > 0 ? (
                    <div className="space-y-6">
                        {groupedUsers.map(({ role, users: groupUsers }) => {
                            const roleInfo = role
                                ? (ROLE_DISPLAY[role] ?? { label: role, color: 'bg-gray-500/20 text-gray-300 border border-gray-500/30' })
                                : { label: 'No Role Assigned', color: 'bg-gray-500/20 text-gray-400 border border-gray-500/30' };

                            return (
                                <div key={role ?? 'no-role'} className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">

                                    {/* Role group header */}
                                    <div className="flex items-center gap-3 px-6 py-3 bg-slate-900/40 border-b border-slate-700/50">
                                        <span className={`px-3 py-1 rounded-full text-xs font-bold ${roleInfo.color}`}>
                                            {roleInfo.label}
                                        </span>
                                        <span className="text-gray-500 text-sm">
                                            {groupUsers.length} user{groupUsers.length !== 1 ? 's' : ''}
                                        </span>
                                    </div>

                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b border-slate-700/50">
                                                    <th className="text-left text-gray-400 py-3 px-6 text-sm font-semibold">Name</th>
                                                    <th className="text-left text-gray-400 py-3 px-4 text-sm font-semibold">Email</th>
                                                    <th className="text-left text-gray-400 py-3 px-4 text-sm font-semibold">Phone</th>
                                                    <th className="text-center text-gray-400 py-3 px-4 text-sm font-semibold">Status</th>
                                                    <th className="text-center text-gray-400 py-3 px-4 text-sm font-semibold">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {groupUsers.map((user) => (
                                                    <tr key={user.id} className="border-b border-slate-700/30 hover:bg-slate-700/20 transition-colors">
                                                        <td className="py-3 px-6 text-white font-medium">{user.name}</td>
                                                        <td className="py-3 px-4 text-gray-300 text-sm">{user.email}</td>
                                                        <td className="py-3 px-4 text-gray-400 text-sm font-mono">{user.phone || '—'}</td>
                                                        <td className="py-3 px-4 text-center">
                                                            <span className={`px-3 py-1 rounded-full text-xs font-semibold ${getStatusColor(user.status)}`}>
                                                                {user.status?.charAt(0).toUpperCase() + user.status?.slice(1)}
                                                            </span>
                                                        </td>
                                                        <td className="py-3 px-4 text-center">
                                                            <div className="flex items-center justify-center gap-2">
                                                                <button
                                                                    onClick={() => handleEdit(user.id)}
                                                                    className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-colors"
                                                                >
                                                                    Edit
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDelete(user)}
                                                                    disabled={deletingId === user.id || user.id === auth?.user?.id}
                                                                    title={user.id === auth?.user?.id ? 'Cannot delete your own account' : 'Delete user'}
                                                                    className="px-4 py-1.5 bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors"
                                                                >
                                                                    {deletingId === user.id ? 'Deleting…' : 'Delete'}
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                        <p className="text-gray-400 mb-4">No users found</p>
                        <button type="button" onClick={handleAddUser}
                            className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg cursor-pointer">
                            Add First User
                        </button>
                    </div>
                )}

                {/* Pagination */}
                {users.links && users.last_page > 1 && (
                    <div className="mt-6 flex justify-center">
                        <div className="flex space-x-1">
                            {users.links.map((link, index) =>
                                link.url ? (
                                    <Link key={index} href={link.url}
                                        className={`px-3 py-2 text-sm rounded-lg ${
                                            link.active
                                                ? 'bg-teal-600 text-white'
                                                : 'bg-slate-700 text-gray-300 hover:bg-slate-600'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={index}
                                        className="px-3 py-2 text-sm rounded-lg bg-slate-800 text-gray-600 cursor-not-allowed"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
