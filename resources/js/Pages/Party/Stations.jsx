import AppLayout from '@/Layouts/AppLayout';

export default function PartyStations({ auth, stations = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">My Assigned Stations</h1>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {stations.length > 0 ? (
                        <div className="space-y-4">
                            {stations.map((station) => (
                                <div key={station.id} className="bg-slate-900/50 p-4 rounded-lg">
                                    <h3 className="text-white font-bold">{station.name}</h3>
                                    <p className="text-gray-400">{station.ward}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-gray-400 text-center py-8">No stations assigned</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
