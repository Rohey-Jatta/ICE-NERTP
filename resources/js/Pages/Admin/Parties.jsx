import AppLayout from '@/Layouts/AppLayout';

export default function Parties({ auth, parties = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">Political Party Management</h1>
                    <button className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        + Register Party
                    </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {parties.length > 0 ? (
                        parties.map((party) => (
                            <div key={party.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <h3 className="text-xl font-bold text-white mb-2">{party.name}</h3>
                                <p className="text-gray-400 mb-4">{party.abbreviation}</p>
                                <div className="flex gap-3">
                                    <button className="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                                        Edit Details
                                    </button>
                                    <button className="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg">
                                        Manage Candidates
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-2 bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400">
                                Political parties will be loaded from database</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
