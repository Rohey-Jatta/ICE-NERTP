import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function Elections({ auth, elections = [], flash }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-white">Election Management</h1>
                    </div>
                    <Link href="/admin/elections/create" className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        + Create New Election
                    </Link>
                </div>

                {flash?.success && (
                    <div className="mb-6 p-4 bg-green-500/20 border border-green-500/50 rounded-lg text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="space-y-4">
                    {elections.length > 0 ? (
                        elections.map((election) => (
                            <div key={election.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex justify-between items-start">
                                    <div>
                                        <h3 className="text-xl font-bold text-white mb-2">{election.name}</h3>
                                        <p className="text-gray-400 capitalize">{election.type?.replace('_', ' ')} — {election.date}</p>
                                    </div>
                                    <span className={`px-4 py-2 rounded-lg text-sm font-semibold ${
                                        election.status === 'active'
                                            ? 'bg-teal-500/20 text-teal-300'
                                            : election.status === 'certified'
                                            ? 'bg-green-500/20 text-green-300'
                                            : 'bg-gray-500/20 text-gray-300'
                                    }`}>
                                        {election.status}
                                    </span>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400 mb-4">No elections configured yet.</p>
                            <Link href="/admin/elections/create" className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg inline-block">
                                Create First Election
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}