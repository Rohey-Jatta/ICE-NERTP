import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function PartyDashboard({ auth, party, assignedStations, statistics }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Party Representative Dashboard</h1>
                <p className="text-gray-400 mb-8">{party?.name || 'Political Party'}</p>
                
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2">Ì∑≥Ô∏è</div>
                        <div className="text-2xl font-bold text-white">{assignedStations?.length || 0}</div>
                        <div className="text-gray-400 text-sm">Assigned Stations</div>
                    </div>
                    
                    <div className="bg-amber-900/40 rounded-xl p-6 border border-amber-700/50">
                        <div className="text-4xl mb-2">‚è≥</div>
                        <div className="text-2xl font-bold text-amber-300">{statistics?.pendingAcceptance || 0}</div>
                        <div className="text-gray-400 text-sm">Pending Acceptance</div>
                    </div>
                    
                    <div className="bg-teal-900/40 rounded-xl p-6 border border-teal-700/50">
                        <div className="text-4xl mb-2">‚úì</div>
                        <div className="text-2xl font-bold text-teal-300">{statistics?.accepted || 0}</div>
                        <div className="text-gray-400 text-sm">Accepted</div>
                    </div>
                    
                    <div className="bg-red-900/40 rounded-xl p-6 border border-red-700/50">
                        <div className="text-4xl mb-2">‚ö†Ô∏è</div>
                        <div className="text-2xl font-bold text-red-300">{statistics?.disputed || 0}</div>
                        <div className="text-gray-400 text-sm">Disputed</div>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                    <h2 className="text-2xl font-bold text-white mb-6">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/party/stations"
                            className="p-6 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                        >
                            <div className="text-3xl mb-2">Ì∑≥Ô∏è</div>
                            <div className="text-xl font-bold text-white">My Stations</div>
                            <div className="text-teal-200 text-sm">View assigned stations</div>
                        </Link>
                        
                        <Link
                            href="/party/pending-acceptance"
                            className="p-6 bg-amber-700 hover:bg-amber-600 rounded-lg transition-colors"
                        >
                            <div className="text-3xl mb-2">‚è≥</div>
                            <div className="text-xl font-bold text-white">Pending Review</div>
                            <div className="text-amber-200 text-sm">{statistics?.pendingAcceptance} results</div>
                        </Link>
                        
                        <Link
                            href="/party/dashboard-overview"
                            className="p-6 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                        >
                            <div className="text-3xl mb-2">Ì≥ä</div>
                            <div className="text-xl font-bold text-white">Party Dashboard</div>
                            <div className="text-gray-300 text-sm">View party analytics</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
