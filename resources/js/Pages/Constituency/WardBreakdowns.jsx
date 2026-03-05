import AppLayout from '@/Layouts/AppLayout';

export default function WardBreakdowns({ auth, wards = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Ward Breakdowns</h1>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {wards.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="text-left text-gray-400 py-3">Ward</th>
                                        <th className="text-right text-gray-400 py-3">Stations</th>
                                        <th className="text-right text-gray-400 py-3">Total Votes</th>
                                        <th className="text-right text-gray-400 py-3">Turnout</th>
                                        <th className="text-center text-gray-400 py-3">Status</th>
                                        <th className="text-center text-gray-400 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {wards.map((ward, i) => (
                                        <tr key={i} className="border-b border-slate-700/50">
                                            <td className="py-4 text-white font-semibold">{ward.name}</td>
                                            <td className="py-4 text-right text-white">{ward.stations}</td>
                                            <td className="py-4 text-right text-white">{ward.votes?.toLocaleString()}</td>
                                            <td className="py-4 text-right text-white">{ward.turnout}%</td>
                                            <td className="py-4 text-center">
                                                <span className={`px-3 py-1 rounded-full text-sm ${
                                                    ward.status === 'Certified'
                                                        ? 'bg-teal-500/20 text-teal-300'
                                                        : 'bg-amber-500/20 text-amber-300'
                                                }`}>
                                                    {ward.status}
                                                </span>
                                            </td>
                                            <td className="py-4 text-center">
                                                <button className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                                                    View Details
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-gray-400 text-center py-8">No ward data available</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
