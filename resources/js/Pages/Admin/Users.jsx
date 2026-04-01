import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function Users({ auth, users = [] }) {
    const handleAddUser = () => router.visit('/admin/users/create');
    const handleEdit = (id) => router.visit(`/admin/users/${id}/edit`);
    const handleAddFirstUser = () => router.visit('/admin/users/create');

    const getRoleDisplay = (user) => {
        if (user.roles && user.roles.length > 0) {
            return user.roles[0].name.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        return 'No Role';
    };

    const getStatusColor = (status) => {
        switch (status) {
            case 'active': return 'bg-green-500/20 text-green-300';
            case 'inactive': return 'bg-yellow-500/20 text-yellow-300';
            case 'suspended': return 'bg-red-500/20 text-red-300';
            default: return 'bg-gray-500/20 text-gray-300';
        }
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">User Management</h1>
                    <button type="button" onClick={handleAddUser} className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg cursor-pointer">
                        Add New User
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {users.data && users.data.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="text-left text-gray-400 py-3">Name</th>
                                        <th className="text-left text-gray-400 py-3">Email</th>
                                        <th className="text-left text-gray-400 py-3">Role</th>
                                        <th className="text-center text-gray-400 py-3">Status</th>
                                        <th className="text-center text-gray-400 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="border-b border-slate-700/50">
                                            <td className="py-4 text-white">{user.name}</td>
                                            <td className="py-4 text-white">{user.email}</td>
                                            <td className="py-4 text-white">{getRoleDisplay(user)}</td>
                                            <td className="py-4 text-center">
                                                <span className={`px-3 py-1 rounded-full text-sm ${getStatusColor(user.status)}`}>
                                                    {user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                                                </span>
                                            </td>
                                            <td className="py-4 text-center">
                                                <button onClick={() => handleEdit(user.id)} className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* Pagination — guard against null URLs */}
                            {users.links && (
                                <div className="mt-6 flex justify-center">
                                    <div className="flex space-x-1">
                                        {users.links.map((link, index) =>
                                            link.url ? (
                                                <Link
                                                    key={index}
                                                    href={link.url}
                                                    className={`px-3 py-2 text-sm rounded-lg ${
                                                        link.active
                                                            ? 'bg-teal-600 text-white'
                                                            : 'bg-slate-700 text-gray-300 hover:bg-slate-600'
                                                    }`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ) : (
                                                <span
                                                    key={index}
                                                    className="px-3 py-2 text-sm rounded-lg bg-slate-800 text-gray-600 cursor-not-allowed"
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            )
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-center py-12">
                            <p className="text-gray-400 mb-4">No users found</p>
                            <button type="button" onClick={handleAddFirstUser} className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg cursor-pointer">
                                Add First User
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
