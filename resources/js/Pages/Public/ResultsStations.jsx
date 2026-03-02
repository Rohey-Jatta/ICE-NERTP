import AppLayout from '@/Layouts/AppLayout';

export default function ResultsStations({ election, stations }) {
    if (!election) {
        return (
            <AppLayout>
                <div className="container mx-auto px-4 py-12">
                    <div className="text-center p-12 bg-slate-800/40 rounded-xl border border-slate-700/50">
                        <h1 className="text-3xl font-bold text-white">No Results Available</h1>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="container mx-auto px-4 py-8">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center mb-8">
                        
                        <h1 className="text-4xl font-bold text-white mb-6">{election.name}</h1>
                        
                        <div className="flex justify-center gap-4 mb-8 flex-wrap">
                            <a 
                                href="/results"
                                className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all"
                            >
                                Summary
                            </a>
                            <a 
                                href="/results/map"
                                className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all"
                            >
                                Map
                            </a>
                            <a 
                                href="/results/stations"
                                className="px-6 py-3 bg-slate-700 text-white rounded-lg font-semibold shadow-lg"
                            >
                                Stations
                            </a>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">All Polling Stations ({stations.length})</h2>
                        <div className="space-y-4 max-h-[700px] overflow-y-auto pr-2">
                            {stations.map((station) => (
                                <div key={station.id} className="bg-slate-900/50 rounded-lg p-4 border border-slate-700/30">
                                    <div className="flex justify-between items-start">
                                        <div className="flex-1">
                                            <div className="font-bold text-white mb-2">{station.name}</div>
                                            <div className="text-sm text-gray-400 space-y-1">
                                                <div>Code: {station.code}</div>
                                                <div>Registered: {station.registered_voters?.toLocaleString()}</div>
                                            </div>
                                            {station.total_votes_cast && (
                                                <div className="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-700/30">
                                                    <div>
                                                        <span className="text-teal-300">Valid:</span> 
                                                        <span className="ml-2 font-semibold text-white">{parseInt(station.valid_votes).toLocaleString()}</span>
                                                    </div>
                                                    <div>
                                                        <span className="text-amber-300">Rejected:</span> 
                                                        <span className="ml-2 font-semibold text-white">{parseInt(station.rejected_votes).toLocaleString()}</span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                        <div className="ml-4">
                                            {station.status === 'nationally_certified' ? (
                                                <span className="inline-block px-3 py-1 bg-teal-900/20 border border-teal-900/50 text-teal-300 rounded-full text-xs font-semibold">
                                                    Certified
                                                </span>
                                            ) : station.status === 'submitted' ? (
                                                <span className="inline-block px-3 py-1 bg-amber-700/20 border border-amber-700/50 text-amber-300 rounded-full text-xs font-semibold">
                                                    Pending
                                                </span>
                                            ) : (
                                                <span className="inline-block px-3 py-1 bg-slate-300/20 border border-slate-300/50 text-slate-300 rounded-full text-xs font-semibold">
                                                    Not Reported
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
