import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

export default function ConstituencyReports({ auth, constituency, reportData }) {
    const [generating, setGenerating] = useState(null);

    const handleDownload = (reportType) => {
        setGenerating(reportType);
        // In production this would call a real export endpoint
        setTimeout(() => {
            alert(`${reportType} report generation would download here. Connect a PDF/Excel export endpoint.`);
            setGenerating(null);
        }, 800);
    };

    const reports = [
        {
            id: 'full',
            name: 'Full Constituency Results',
            description: 'Complete results with all wards and polling stations, candidate votes, and certification status.',
            format: 'PDF',
            color: 'border-blue-500/30 bg-blue-500/10',
            btnColor: 'bg-blue-600 hover:bg-blue-700',
            icon: '📋',
        },
        {
            id: 'ward-summary',
            name: 'Ward Summary Report',
            description: 'Summary of each ward with aggregated vote totals, turnout statistics, and certification progress.',
            format: 'PDF',
            color: 'border-teal-500/30 bg-teal-500/10',
            btnColor: 'bg-teal-600 hover:bg-teal-700',
            icon: '📊',
        },
        {
            id: 'turnout',
            name: 'Turnout Analysis',
            description: 'Detailed turnout statistics by ward and polling station with comparisons.',
            format: 'PDF',
            color: 'border-purple-500/30 bg-purple-500/10',
            btnColor: 'bg-purple-600 hover:bg-purple-700',
            icon: '📈',
        },
        {
            id: 'certification',
            name: 'Certification Status Report',
            description: 'Audit trail of certification decisions including approvals, reservations, and rejections.',
            format: 'PDF',
            color: 'border-amber-500/30 bg-amber-500/10',
            btnColor: 'bg-amber-600 hover:bg-amber-700',
            icon: '✅',
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header with back link */}
                <div className="mb-6">
                    <Link href="/constituency/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Back to Constituency Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Constituency Reports</h1>
                    {constituency?.name && <p className="text-teal-300 mt-1">{constituency.name}</p>}
                </div>

                {/* Summary stats */}
                {reportData && (
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-8">
                        <h2 className="text-lg font-bold text-white mb-4">Constituency Summary</h2>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {[
                                { label: 'Registered Voters', value: reportData.total_registered?.toLocaleString() || '0', color: 'text-white' },
                                { label: 'Total Votes Cast',  value: reportData.total_cast?.toLocaleString() || '0', color: 'text-white' },
                                { label: 'Valid Votes',       value: reportData.total_valid?.toLocaleString() || '0', color: 'text-teal-300' },
                                { label: 'Turnout',           value: `${reportData.turnout || 0}%`, color: 'text-blue-300' },
                            ].map(stat => (
                                <div key={stat.label} className="bg-slate-900/50 p-4 rounded-lg">
                                    <div className="text-gray-400 text-xs mb-1">{stat.label}</div>
                                    <div className={`text-2xl font-bold ${stat.color}`}>{stat.value}</div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 pt-4 border-t border-slate-700/50 flex gap-6 text-sm text-gray-400">
                            <span>Stations Reporting: <strong className="text-white">{reportData.total_stations}</strong></span>
                            <span>Certified: <strong className="text-teal-300">{reportData.certified_count}</strong></span>
                            <span>Rejected: <strong className="text-amber-300">{reportData.total_rejected?.toLocaleString()}</strong> votes rejected</span>
                        </div>
                    </div>
                )}

                {/* Report cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {reports.map((report) => (
                        <div key={report.id} className={`rounded-xl p-6 border ${report.color}`}>
                            <div className="flex items-start justify-between mb-4">
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl">{report.icon}</span>
                                    <div>
                                        <h3 className="text-lg font-bold text-white">{report.name}</h3>
                                        <p className="text-gray-400 text-sm mt-1">{report.description}</p>
                                    </div>
                                </div>
                                <span className="px-2 py-1 bg-slate-700 text-gray-300 rounded text-xs font-mono flex-shrink-0 ml-2">
                                    {report.format}
                                </span>
                            </div>
                            <button
                                onClick={() => handleDownload(report.name)}
                                disabled={generating === report.name}
                                className={`w-full px-4 py-3 ${report.btnColor} disabled:opacity-50 text-white font-bold rounded-lg transition-colors flex items-center justify-center gap-2`}
                            >
                                {generating === report.name ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        Generating…
                                    </>
                                ) : (
                                    <>⬇ Download Report</>
                                )}
                            </button>
                        </div>
                    ))}
                </div>

                {/* Navigation */}
                <div className="mt-6 flex gap-4">
                    <Link href="/constituency/approval-queue" className="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                        Back to Approval Queue
                    </Link>
                    <Link href="/constituency/ward-breakdowns" className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg">
                        View Ward Breakdowns
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
