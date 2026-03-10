import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';

export default function ConstituencyReports({ auth, reports = [] }) {
    const defaultReports = [
        { name: 'Full Constituency Results', description: 'Complete results with all wards', format: 'PDF' },
        { name: 'Ward Summary Report', description: 'Summary of each ward', format: 'Excel' },
        { name: 'Turnout Analysis', description: 'Detailed turnout statistics', format: 'PDF' },
        { name: 'Party Performance', description: 'Performance by political party', format: 'PDF' },
    ];

    const availableReports = reports.length > 0 ? reports : defaultReports;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Constituency Reports</h1>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {availableReports.map((report, i) => (
                        <div key={i} className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                            <div className="flex items-start justify-between mb-4">
                                <div>
                                    <h3 className="text-xl font-bold text-white mb-2">{report.name}</h3>
                                    <p className="text-gray-400 text-sm">{report.description}</p>
                                </div>
                                <span className="px-3 py-1 bg-blue-500/20 text-blue-300 rounded-lg text-sm">
                                    {report.format}
                                </span>
                            </div>
                            <button 
                                onClick={() => window.open(`/constituency/reports/download/${i + 1}`, '_blank')}
                                className="w-full px-6 py-3 bg-pink-500 hover:bg-pink-600 text-white font-bold rounded-lg"
                            >
                                Download Report
                            </button>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
