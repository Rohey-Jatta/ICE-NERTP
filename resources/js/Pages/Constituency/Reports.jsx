import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

export default function ConstituencyReports({ auth, constituency, reportData }) {
    const [generating, setGenerating] = useState(null);

    // Navigating directly to the export endpoint triggers the file download.
    const handleDownload = (reportId, format) => {
        const key = `${reportId}-${format}`;
        setGenerating(key);
        window.location.href = `/constituency/reports/export/${reportId}/${format}`;
        // The browser stays on this page during a download; just clear the spinner.
        setTimeout(() => setGenerating(null), 2500);
    };

    const reports = [
        {
            id: 'full',
            name: 'Full Constituency Results',
            description: 'Complete results with all wards and polling stations, candidate votes, and certification status.',
            color: 'border-blue-500/30 bg-iec-pink-500/10',
            icon: '📋',
        },
        {
            id: 'ward-summary',
            name: 'Ward Summary Report',
            description: 'Summary of each ward with aggregated vote totals, turnout statistics, and certification progress.',
            color: 'border-teal-500/30 bg-iec-pink-500/10',
            icon: '📊',
        },
        {
            id: 'turnout',
            name: 'Turnout Analysis',
            description: 'Detailed turnout statistics by ward and polling station with comparisons.',
            color: 'border-iec-pink-100 bg-iec-pink-50',
            icon: '📈',
        },
        {
            id: 'certification',
            name: 'Certification Status Report',
            description: 'Audit trail of certification decisions including approvals, reservations, and rejections.',
            color: 'border-amber-500/30 bg-amber-500/10',
            icon: '✅',
        },
    ];

    const Spinner = () => (
        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    );

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header with back link */}
                <div className="mb-6">
                    <Link href="/constituency/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                        Back to Constituency Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Constituency Reports</h1>
                    {constituency?.name && <p className="text-iec-pink-600 mt-1">{constituency.name}</p>}
                </div>

                {/* Summary stats */}
                {reportData && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200 mb-8">
                        <h2 className="text-lg font-bold text-iec-navy mb-4">Constituency Summary</h2>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {[
                                { label: 'Registered Voters', value: reportData.total_registered?.toLocaleString() || '0', color: 'text-iec-navy' },
                                { label: 'Total Votes Cast',  value: reportData.total_cast?.toLocaleString() || '0', color: 'text-iec-navy' },
                                { label: 'Valid Votes',       value: reportData.total_valid?.toLocaleString() || '0', color: 'text-iec-pink-600' },
                                { label: 'Turnout',           value: `${reportData.turnout || 0}%`, color: 'text-iec-pink-600' },
                            ].map(stat => (
                                <div key={stat.label} className="bg-white p-4 rounded-lg">
                                    <div className="text-slate-500 text-xs mb-1">{stat.label}</div>
                                    <div className={`text-2xl font-bold ${stat.color}`}>{stat.value}</div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 pt-4 border-t border-slate-200 flex gap-6 text-sm text-slate-500">
                            <span>Stations Reporting: <strong className="text-iec-navy">{reportData.total_stations}</strong></span>
                            <span>Certified: <strong className="text-iec-pink-600">{reportData.certified_count}</strong></span>
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
                                        <h3 className="text-lg font-bold text-iec-navy">{report.name}</h3>
                                        <p className="text-slate-500 text-sm mt-1">{report.description}</p>
                                    </div>
                                </div>
                                <span className="px-2 py-1 bg-white text-slate-600 rounded text-xs font-mono flex-shrink-0 ml-2">
                                    PDF · XLSX
                                </span>
                            </div>
                            <div className="flex gap-3">
                                <button
                                    onClick={() => handleDownload(report.id, 'pdf')}
                                    disabled={generating === `${report.id}-pdf`}
                                    className="flex-1 px-4 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white font-bold rounded-lg transition-colors flex items-center justify-center gap-2"
                                >
                                    {generating === `${report.id}-pdf` ? <><Spinner /> Generating…</> : <>⬇ PDF</>}
                                </button>
                                <button
                                    onClick={() => handleDownload(report.id, 'excel')}
                                    disabled={generating === `${report.id}-excel`}
                                    className="flex-1 px-4 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold rounded-lg transition-colors flex items-center justify-center gap-2"
                                >
                                    {generating === `${report.id}-excel` ? <><Spinner /> Generating…</> : <>⬇ Excel</>}
                                </button>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Navigation */}
                <div className="mt-6 flex gap-4">
                    <Link href="/constituency/approval-queue" className="px-6 py-3 bg-pink-400 hover:bg-pink-500 text-white font-bold rounded-lg">
                        Back to Approval Queue
                    </Link>
                    <Link href="/constituency/ward-breakdowns" className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
                        View Ward Breakdowns
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
