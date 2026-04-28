import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import LeafletMap from '@/Components/Map/LeafletMap';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';

function ElectionSelector({ elections = [], selectedElectionId, basePath = '/results/map' }) {
    if (elections.length <= 1) return null;
    return (
        <div className="flex flex-wrap justify-center gap-2 mb-4">
            <span className="text-gray-500 text-xs self-center mr-1">Election:</span>
            {elections.map(e => (
                <button
                    key={e.id}
                    onClick={() => router.get(basePath, { election: e.id }, { preserveScroll: false })}
                    className={`px-4 py-1.5 rounded-lg text-sm font-semibold transition-all ${
                        selectedElectionId === e.id
                            ? 'bg-pink-600 text-white shadow-lg'
                            : 'bg-slate-800/40 text-gray-300 hover:bg-slate-700 border border-slate-700/50'
                    }`}
                >
                    {e.name}
                </button>
            ))}
        </div>
    );
}

export default function ResultsMap({ election, elections = [], selectedElectionId, stations }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';
    useInertiaPrefetch([`/results${param}`, `/results/stations${param}`]);

    if (!election) {
        return (
            <AppLayout>
                <div className="container mx-auto px-4 py-12">
                    <ElectionSelector elections={elections} selectedElectionId={selectedElectionId} basePath="/results/map" />
                    <div className="text-center p-12 bg-slate-800/40 rounded-xl border border-slate-700/50">
                        <h1 className="text-3xl font-bold text-white">No Results Available</h1>
                        <Link href="/" className="mt-4 inline-block px-6 py-3 bg-pink-600 text-white rounded-lg">Back Home</Link>
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
                        <h1 className="text-4xl font-bold text-white mb-4">{election.name}</h1>
                        <ElectionSelector elections={elections} selectedElectionId={selectedElectionId} basePath="/results/map" />
                        <div className="flex justify-center gap-4 mb-4 flex-wrap">
                            <Link href={`/results${param}`} prefetch
                                  className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all">
                                Summary
                            </Link>
                            <Link href={`/results/map${param}`} prefetch
                                  className="px-6 py-3 bg-slate-700 text-white rounded-lg font-semibold shadow-lg">
                                Map
                            </Link>
                            <Link href={`/results/stations${param}`} prefetch
                                  className="px-6 py-3 bg-slate-800/30 text-gray-300 rounded-lg font-semibold hover:bg-slate-700 transition-all">
                                Stations
                            </Link>
                        </div>
                    </div>
                    <LeafletMap stations={stations} />
                </div>
            </div>
        </AppLayout>
    );
}