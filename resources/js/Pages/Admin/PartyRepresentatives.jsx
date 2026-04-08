import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function PartyRepresentatives({ auth, representatives = [] }) {
    const handleAddRepresentative = () => router.visit('/admin/party-representatives/create');
    const handleEdit = (id) => router.visit(`/admin/party-representatives/${id}/edit`);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                         ← Back to Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Party Representatives Management</h1>
                    <button onClick={handleAddRepresentative} className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        + Add Party Representative
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {representatives.data && representatives.data.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="text-left text-gray-400 py-3">Representative</th>
                                        <th className="text-left text-gray-400 py-3">Party</th>
                                        <th className="text-left text-gray-400 py-3">Designation</th>
                                        <th className="text-left text-gray-400 py-3">Accreditation</th>
                                        <th className="text-center text-gray-400 py-3">Stations</th>
                                        <th className="text-center text-gray-400 py-3">Status</th>
                                        <th className="text-center text-gray-400 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {representatives.data.map((rep) => (
                                        <tr key={rep.id} className="border-b border-slate-700/50">
                                            <td className="py-4 text-white">
                                                <div>
                                                    <div className="font-medium">{rep.user?.name}</div>
                                                    <div className="text-gray-400 text-sm">{rep.user?.email}</div>
                                                </div>
                                            </td>
                                            <td className="py-4 text-white">{rep.political_party?.name}</td>
                                            <td className="py-4 text-white">{rep.designation || 'N/A'}</td>
                                            <td className="py-4 text-white font-mono text-sm">{rep.accreditation_number}</td>
                                            <td className="py-4 text-center text-white">{rep.polling_stations?.length || 0}</td>
                                            <td className="py-4 text-center">
                                                <span className={`px-3 py-1 rounded-full text-sm ${
                                                    rep.is_active
                                                        ? 'bg-green-500/20 text-green-300'
                                                        : 'bg-red-500/20 text-red-300'
                                                }`}>
                                                    {rep.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="py-4 text-center">
                                                <button
                                                    onClick={() => handleEdit(rep.id)}
                                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm cursor-pointer"
                                                >
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* Pagination */}
                            {representatives.links && (
                                <div className="mt-6 flex justify-center">
                                    <div className="flex space-x-1">
                                        {representatives.links.map((link, index) =>
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
                            <p className="text-gray-400 mb-4">No party representatives found</p>
                            <button onClick={handleAddRepresentative} className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                                Add First Representative
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
