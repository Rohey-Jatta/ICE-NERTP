import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function WardDashboard({ auth, ward, pendingResults, statistics }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Ward Approver Dashboard</h1>
                
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2">Ì≥ç</div>
                        <div className="text-2xl font-bold text-white">{ward?.name || 'N/A'}</div>
                        <div className="text-gray-400 text-sm">Your Ward</div>
                    </div>
                    
                    <div className="bg-amber-900/40 rounded-xl p-6 border border-amber-700/50">
                        <div className="text-4xl mb-2">‚è≥</div>
                        <div className="text-2xl font-bold text-amber-300">{pendingResults || 0}</div>
                        <div className="text-gray-400 text-sm">Pending Approval</div>
                    </div>
                    
                    <div className="bg-teal-900/40 rounded-xl p-6 border border-teal-700/50">
                        <div className="text-4xl mb-2">‚úì</div>
                        <div className="text-2xl font-bold text-teal-300">{statistics?.approved || 0}</div>
                        <div className="text-gray-400 text-sm">Approved Today</div>
                    </div>
                    
                    <div className="bg-red-900/40 rounded-xl p-6 border border-red-700/50">
                        <div className="text-4xl mb-2">‚úó</div>
                        <div className="text-2xl font-bold text-red-300">{statistics?.rejected || 0}</div>
                        <div className="text-gray-400 text-sm">Rejected Today</div>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                    <h2 className="text-2xl font-bold text-white mb-6">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Link
                            href="/ward/approval-queue"
                            className="p-6 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                        >
                            <div className="text-3xl mb-2">Ì≥ã</div>
                            <div className="text-xl font-bold text-white">Approval Queue</div>
                            <div className="text-teal-200 text-sm">{pendingResults} results pending</div>
                        </Link>
                        
                        <Link
                            href="/ward/analytics"
                            className="p-6 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                        >
                            <div className="text-3xl mb-2">Ì≥ä</div>
                            <div className="text-xl font-bold text-white">Analytics</div>
                            <div className="text-gray-300 text-sm">View ward statistics</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
