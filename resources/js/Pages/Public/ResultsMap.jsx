import AppLayout from '@/Layouts/AppLayout';
import LeafletMap from '@/Components/Map/LeafletMap';

export default function ResultsMap({ election, stations }) {
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
                                className="px-6 py-3 bg-slate-700 text-white rounded-lg font-semibold shadow-lg"
                            >
                                Map
                            </a>
                            <a 
                                href="/results/stations"
                                className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all"
                            >
                                Stations
                            </a>
                        </div>
                    </div>

                    <LeafletMap stations={stations} />
                </div>
            </div>
        </AppLayout>
    );
}
