import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import { router } from '@inertiajs/react';

export default function AllResults({ auth, results = [] }) {
    const [filter, setFilter] = useState('all');

    const handleViewDetails = (resultId) => {
        router.visit(`/results/${resultId}`);
    };

    const filteredResults = filter === 'all' ? results : results.filter(r => {
        if (filter === 'Nationally') return r.status?.includes('Nationally');
        if (filter === 'Admin') return r.status?.includes('Admin');
        if (filter === 'Constituency') return r.status?.includes('Constituency');
        return true;
    });

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">All Results - National Overview</h1>

                {/* Filter Buttons */}
                <div className="flex gap-3 mb-6 overflow-x-auto">
                    <button
                        onClick={() => setFilter('all')}
                        className={`px-6 py-3 rounded-lg font-semibold whitespace-nowrap ${
                            filter === 'all'
                                ? 'bg-slate-500 text-white'
                                : 'bg-slate-700 text-gray-300 hover:bg-slate-600'
                        }`}
                    >
                        All Results
                    </button>
                    <button
                        onClick={() => setFilter('Nationally')}
                        className={`px-6 py-3 rounded-lg font-semibold whitespace-nowrap ${
                            filter === 'Nationally'
                                ? 'bg-slate-500 text-white'
                                : 'bg-slate-700 text-gray-300 hover:bg-slate-600'
                        }`}
                    >
                        Nationally Certified
                    </button>
                    <button
                        onClick={() => setFilter('Admin')}
                        className={`px-6 py-3 rounded-lg font-semibold whitespace-nowrap ${
                            filter === 'Admin'
                                ? 'bg-slate-500 text-white'
                                : 'bg-slate-700 text-gray-300 hover:bg-slate-600'
                        }`}
                    >
                        Admin Area Level
                    </button>
                    <button
                        onClick={() => setFilter('Constituency')}
                        className={`px-6 py-3 rounded-lg font-semibold whitespace-nowrap ${
                            filter === 'Constituency'
                                ? 'bg-slate-500 text-white'
                                : 'bg-slate-700 text-gray-300 hover:bg-slate-600'
                        }`}
                    >
                        Constituency Level
                    </button>
                </div>

                {/* Results Table */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-500/50">
                    {filteredResults.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-700">
                                        <th className="text-left text-gray-400 py-3">Administrative Area</th>
                                        <th className="text-right text-gray-400 py-3">Total Votes</th>
                                        <th className="text-right text-gray-400 py-3">Progress</th>
                                        <th className="text-center text-gray-400 py-3">Status</th>
                                        <th className="text-center text-gray-400 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredResults.map((result, i) => (
                                        <tr key={i} className="border-b border-slate-700/50">
                                            <td className="py-4 text-white font-semibold">{result.area}</td>
                                            <td className="py-4 text-right text-white text-lg">{result.votes?.toLocaleString()}</td>
                                            <td className="py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <div className="w-24 bg-slate-700 rounded-full h-2">
                                                        <div
                                                            className="bg-slate-500 h-2 rounded-full"
                                                            style={{ width: `${result.progress}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-white font-semibold">{result.progress}%</span>
                                                </div>
                                            </td>
                                            <td className="py-4 text-center">
                                                <span className={`px-3 py-1 rounded-full text-sm ${
                                                    result.status?.includes('Nationally')
                                                        ? 'bg-slate-500/20 text-slate-300 border border-slate-500/50'
                                                        : result.status?.includes('Admin')
                                                        ? 'bg-slate-500/20 text-slate-300'
                                                        : 'bg-slate-800/20 text-slate-300'
                                                }`}>
                                                    {result.status}
                                                </span>
                                            </td>
                                            <td className="py-4 text-center">
                                                <button
                                                    onClick={() => handleViewDetails(result.id)}
                                                    className="px-4 py-2 bg-slate-700 hover:bg-slate-500 text-white rounded-lg text-sm"
                                                >
                                                    View Full Details
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-gray-400 text-center py-8">No results match this filter</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
