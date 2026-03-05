import AppLayout from '@/Layouts/AppLayout';

export default function Observations({ auth, observations = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">My Observations</h1>

                <div className="space-y-4">
                    {observations.length > 0 ? (
                        observations.map((obs) => (
                            <div key={obs.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <p className="text-white">{obs.observation}</p>
                                <p className="text-gray-400 text-sm mt-2">{obs.created_at}</p>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400">No observations submitted yet</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
