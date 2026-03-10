import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function AdminAreaApprovalQueue({ auth, constituencyResults = [] }) {
    const handleCertify = (id) => router.post(`/admin-area/certify/${id}`);
    const handleReject = (id) => router.post(`/admin-area/reject/${id}`);
    const handleView = (id) => router.visit(`/admin-area/constituency/${id}`);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Admin Area Approval Queue</h1>

                <div className="space-y-4">
                    {constituencyResults.length > 0 ? (
                        constituencyResults.map((constituency) => (
                            <div key={constituency.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 className="text-2xl font-bold text-white">{constituency.name}</h3>
                                        <p className="text-gray-400 text-sm">Constituency certified at {constituency.certified_at}</p>
                                    </div>
                                    <span className="px-4 py-2 bg-blue-500/20 text-blue-300 rounded-lg">
                                        Constituency Certified
                                    </span>
                                </div>

                                <div className="grid grid-cols-4 gap-4 mb-6">
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Wards</div>
                                        <div className="text-white font-bold text-2xl">{constituency.wards}</div>
                                    </div>
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Stations</div>
                                        <div className="text-white font-bold text-2xl">{constituency.stations}</div>
                                    </div>
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Total Votes</div>
                                        <div className="text-white font-bold text-2xl">{constituency.total_votes?.toLocaleString()}</div>
                                    </div>
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Progress</div>
                                        <div className="text-white font-bold text-2xl">{constituency.progress}%</div>
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <button onClick={() => handleCertify(constituency.id)} className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                                        ✓ Certify at Admin Area Level
                                    </button>
                                    <button onClick={() => handleReject(constituency.id)} className="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg">
                                        ✗ Reject & Return
                                    </button>
                                    <button onClick={() => handleView(constituency.id)} className="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg">
                                        View Full Details
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-300">No constituency-certified results awaiting approval</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
