import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function Parties({ auth, parties = [], flash }) {
    const handleRegister = () => router.visit('/admin/parties/create');
    const handleEdit = (id) => router.visit(`/admin/parties/${id}/edit`);
    const handleManageCandidates = (id) => router.visit(`/admin/parties/${id}/candidates`);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="mb-6">
                    <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Dashboard
                    </Link>
                    <div className="flex justify-between items-center">
                        <h1 className="text-3xl font-bold text-white">Political Party Management</h1>
                        <button onClick={handleRegister} className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                            + Register Party
                        </button>
                    </div>
                </div>

                {flash?.success && (
                    <div className="mb-6 p-4 bg-green-500/20 border border-green-500/50 rounded-lg text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300">
                        {flash.error}
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {parties.length > 0 ? (
                        parties.map((party) => (
                            <div key={party.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex items-center gap-3 mb-2">
                                    {party.color && (
                                        <div
                                            className="w-4 h-4 rounded-full border border-slate-600 flex-shrink-0"
                                            style={{ backgroundColor: party.color }}
                                        />
                                    )}
                                    <h3 className="text-xl font-bold text-white">{party.name}</h3>
                                </div>
                                <p className="text-gray-400 text-sm mb-1">{party.abbreviation}</p>
                                {party.leader_name && (
                                    <p className="text-gray-500 text-sm mb-4">Leader: {party.leader_name}</p>
                                )}
                                <div className="flex gap-3 mt-4">
                                    <button
                                        onClick={() => handleEdit(party.id)}
                                        className="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg"
                                    >
                                        Edit Details
                                    </button>
                                    <button
                                        onClick={() => handleManageCandidates(party.id)}
                                        className="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg"
                                    >
                                        Manage Candidates
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-2 bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400 mb-4">No political parties registered yet.</p>
                            <button
                                onClick={handleRegister}
                                className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg"
                            >
                                Register First Party
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
