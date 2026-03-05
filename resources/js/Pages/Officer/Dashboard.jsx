import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function OfficerDashboard({ auth, station, submissions }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Polling Officer Dashboard</h1>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2">Ì≥ç</div>
                        <div className="text-2xl font-bold text-white">{station?.name || 'N/A'}</div>
                        <div className="text-gray-400 text-sm">Your Polling Station</div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2">Ì±•</div>
                        <div className="text-2xl font-bold text-white">{station?.registered_voters || 0}</div>
                        <div className="text-gray-400 text-sm">Registered Voters</div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2">Ì≥Ñ</div>
                        <div className="text-2xl font-bold text-white">{submissions?.length || 0}</div>
                        <div className="text-gray-400 text-sm">Submissions</div>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                    <h2 className="text-2xl font-bold text-white mb-6">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Link
                            href="/officer/results/submit"
                            className="p-6 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                        >
                            <div className="text-3xl mb-2">‚ûï</div>
                            <div className="text-xl font-bold text-white">Submit Results</div>
                            <div className="text-teal-200 text-sm">Enter vote counts</div>
                        </Link>

                        <Link
                            href="/officer/submissions"
                            className="p-6 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                        >
                            <div className="text-3xl mb-2">Ì≥ã</div>
                            <div className="text-xl font-bold text-white">My Submissions</div>
                            <div className="text-gray-300 text-sm">View submitted results</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
