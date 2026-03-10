import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function PollingStations({ auth, stations = [] }) {
    const handleRegister = () => router.visit('/admin/polling-stations/create');
    const handleEdit = (id) => router.visit(`/admin/polling-stations/${id}/edit`);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">Polling Station Management</h1>
                    <button onClick={handleRegister} className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        + Register Station
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {stations.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="text-left text-gray-400 py-3">Code</th>
                                        <th className="text-left text-gray-400 py-3">Name</th>
                                        <th className="text-left text-gray-400 py-3">Ward</th>
                                        <th className="text-right text-gray-400 py-3">Registered Voters</th>
                                        <th className="text-center text-gray-400 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stations.map((station) => (
                                        <tr key={station.id} className="border-b border-slate-700/50">
                                            <td className="py-4 text-white font-mono">{station.code}</td>
                                            <td className="py-4 text-white">{station.name}</td>
                                            <td className="py-4 text-white">{station.ward}</td>
                                            <td className="py-4 text-right text-white">{station.voters}</td>
                                            <td className="py-4 text-center">
                                                <button onClick={() => handleEdit(station.id)} className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
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
                            <p className="text-gray-400">Polling stations will be loaded from database</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
