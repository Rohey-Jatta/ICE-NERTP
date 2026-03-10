import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function ConstituencyApprovalQueue({ auth, wardResults = [] }) {
    const handleApprove = (wardId) => {
        router.post(`/constituency/approve/${wardId}`);
    };
    const handleReject = (wardId) => {
        router.post(`/constituency/reject/${wardId}`);
    };
    const handleView = (wardId) => {
        router.visit(`/wards/${wardId}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Constituency Approval Queue</h1>

                <div className="space-y-4">
                    {wardResults.length > 0 ? (
                        wardResults.map((ward) => (
                            <div key={ward.id} className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                                <div className="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 className="text-xl font-bold text-white">{ward.ward_name}</h3>
                                        <p className="text-gray-400 text-sm">
                                            Certified by {ward.certified_by} at {ward.certified_at}
                                        </p>
                                    </div>
                                    <span className="px-4 py-2 bg-slate-800/40 text-gray-400 rounded-lg">
                                        Ward Certified
                                    </span>
                                </div>

                                <div className="grid grid-cols-3 gap-4 mb-6">
                                    <div className="bg-slate-800/40 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Stations</div>
                                        <div className="text-white font-bold text-2xl">{ward.stations}</div>
                                    </div>
                                    <div className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">All Certified</div>
                                        <div className="text-white font-bold text-2xl">{ward.certified}</div>
                                    </div>
                                    <div className="bg-slate-800/40 p-4 rounded-lg">
                                        <div className="text-gray-400 text-sm">Total Votes</div>
                                        <div className="text-white font-bold text-2xl">{ward.total_votes?.toLocaleString()}</div>
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <button onClick={() => handleApprove(ward.id)} className="flex-1 px-6 py-3 bg-pink-600 hover:bg-pink-700 text-white font-bold rounded-lg">
                                        Approve at Constituency Level
                                    </button>
                                    <button onClick={() => handleReject(ward.id)} className="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg">
                                        Reject & Return to Ward
                                    </button>
                                    <button onClick={() => handleView(ward.id)} className="px-6 py-3 bg-pink-600 hover:bg-pink-700 text-white font-bold rounded-lg">
                                        View Ward Details
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-pink-300/50 text-center">
                            <p className="text-gray-300">No ward-certified results awaiting approval</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
