import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function AdminAreas({ auth, adminAreas = [], flash }) {

    const handleDelete = (area) => {
        if (!window.confirm(`Delete admin area "${area.name}"? This cannot be undone.`)) return;
        router.delete(`/admin/hierarchy/admin-areas/${area.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="mb-6">
                    <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Dashboard
                    </Link>
                    <div className="flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-white">Administrative Area Management</h1>
                            <p className="text-gray-400 text-sm mt-1">Top-level administrative regions (Level 1)</p>
                        </div>
                        <Link href="/admin/hierarchy/admin-areas/create"
                            className="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg">
                            + Register Administrative Area
                        </Link>
                    </div>
                </div>

                {flash?.success && (
                    <div className="mb-6 p-4 bg-green-500/20 border border-green-500/50 rounded-lg text-green-300">{flash.success}</div>
                )}
                {flash?.error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300">{flash.error}</div>
                )}

                <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
                    {adminAreas.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700 bg-slate-900/40">
                                        <th className="text-left text-gray-400 py-4 px-5">Code</th>
                                        <th className="text-left text-gray-400 py-4 px-5">Name</th>
                                        <th className="text-left text-gray-400 py-4 px-5">Election</th>
                                        <th className="text-right text-gray-400 py-4 px-5">Constituencies</th>
                                        <th className="text-center text-gray-400 py-4 px-5">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {adminAreas.map((area) => (
                                        <tr key={area.id} className="border-b border-slate-700/50 hover:bg-slate-700/20">
                                            <td className="py-4 px-5 text-white font-mono">{area.code || '—'}</td>
                                            <td className="py-4 px-5 text-white font-semibold">{area.name}</td>
                                            <td className="py-4 px-5 text-gray-400">{area.election_name || '—'}</td>
                                            <td className="py-4 px-5 text-right text-white">{area.children_count ?? 0}</td>
                                            <td className="py-4 px-5">
                                                <div className="flex items-center justify-center gap-2">
                                                    <Link href={`/admin/hierarchy/admin-areas/${area.id}/edit`}
                                                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                                                        Edit
                                                    </Link>
                                                    <button onClick={() => handleDelete(area)}
                                                        className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="text-center py-16">
                            <div className="text-5xl mb-4">🗺️</div>
                            <p className="text-gray-400 mb-4">No administrative areas registered yet.</p>
                            <Link href="/admin/hierarchy/admin-areas/create"
                                className="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg inline-block">
                                Register First Administrative Area
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}