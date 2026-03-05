import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function Users({ auth, users = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">User Management</h1>
                    <button className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        + Add New User
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {users.length > 0 ? (
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
                                    {users.map((user) => (
                                        <tr key={user.id} className="border-b border-slate-700/50">
                                            <td className="py-4 text-white">{user.name}</td>
                                            <td className="py-4 text-white">{user.email}</td>
                                            <td className="py-4 text-white">{user.role}</td>
                                            <td className="py-4 text-center">
                                                <span className="px-3 py-1 bg-teal-500/20 text-teal-300 rounded-full text-sm">
                                                    Active
                                                </span>
                                            </td>
                                            <td className="py-4 text-center">
                                                <button className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="text-center py-12">
                            <p className="text-gray-400 mb-4">No users found</p>
                            <button className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                                Add First User
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
