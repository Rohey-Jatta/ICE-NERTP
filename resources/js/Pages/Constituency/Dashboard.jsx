import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function ConstituencyDashboard({ auth, constituency, statistics }) {
    const progress = statistics?.progress || 0;

    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-iec-navy">Constituency Approver Dashboard</h1>
                    {constituency?.name && (
                        <p className="text-iec-pink-600 mt-1 text-lg">{constituency.name}</p>
                    )}
                    <p className="text-slate-500 text-sm mt-1">
                        Review and certify ward-certified results at constituency level
                    </p>
                </div>

                {/* Alert if pending */}
                {statistics?.pending > 0 && (
                    <div className="mb-6 p-4 bg-pink-500/10 border border-pink-500/40 rounded-xl flex items-center gap-3">
                        <div className="w-3 h-3 bg-pink-400 rounded-full animate-pulse flex-shrink-0" />
                        <p className="text-pink-500">
                            <strong>{statistics.pending} result{statistics.pending !== 1 ? 's' : ''}</strong> awaiting your constituency certification
                        </p>
                        <Link
                            href="/constituency/approval-queue"
                            className="ml-auto px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white text-sm font-bold rounded-lg"
                        >
                            Review Now →
                        </Link>
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-amber-600">{statistics?.pending || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Pending Certification</div>
                    </div>
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-pink-600">{statistics?.certified || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Constituency Certified</div>
                    </div>
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-red-500">{statistics?.rejected || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Returned to Ward</div>
                    </div>
                    <div className="bg-white rounded-xl p-5 border border-slate-200">
                        <div className="text-3xl font-bold text-iec-navy">{statistics?.totalWards || 0}</div>
                        <div className="text-slate-500 text-sm mt-1">Wards</div>
                    </div>
                </div>

                {/* Progress bar */}
                {statistics?.certified > 0 && (
                    <div className="bg-white rounded-xl p-6 border border-slate-200 mb-8">
                        <div className="flex justify-between items-center mb-3">
                            <span className="text-slate-600 font-semibold">Certification Progress</span>
                            <span className="text-iec-navy font-bold">{progress}%</span>
                        </div>
                        <div className="w-full bg-white rounded-full h-4">
                            <div
                                className="bg-gradient-to-r from-teal-500 to-teal-400 h-4 rounded-full transition-all duration-700"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <h2 className="text-xl font-bold text-iec-navy mb-5">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/constituency/approval-queue?filter=pending"
                            className="group p-5 bg-pink-500/10 hover:bg-pink-500/20 border border-pink-500/30 rounded-xl transition-all block"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-pink-500/20 rounded-lg flex items-center justify-center text-pink-400">⏳</div>
                                <div className="text-lg font-bold text-iec-navy">Approval Queue</div>
                            </div>
                            <div className="text-pink-300 text-sm">
                                {statistics?.pending > 0
                                    ? `${statistics.pending} ward-certified result${statistics.pending !== 1 ? 's' : ''} pending`
                                    : 'No results pending'}
                            </div>
                        </Link>

                        <Link
                            href="/constituency/ward-breakdowns"
                            className="group p-5 bg-iec-pink-500/10 hover:bg-iec-pink-500/20 border border-blue-500/30 rounded-xl transition-all block"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-iec-pink-500/20 rounded-lg flex items-center justify-center text-iec-pink-600">📊</div>
                                <div className="text-lg font-bold text-iec-navy">Ward Breakdowns</div>
                            </div>
                            <div className="text-iec-pink-600 text-sm">
                                {statistics?.totalWards || 0} wards in your constituency
                            </div>
                        </Link>

                        <Link
                            href="/constituency/reports"
                            className="group p-5 bg-iec-pink-50 hover:bg-iec-pink-50 border border-iec-pink-100 rounded-xl transition-all block"
                        >
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-8 bg-iec-pink-50 rounded-lg flex items-center justify-center text-iec-pink-600">📄</div>
                                <div className="text-lg font-bold text-iec-navy">Reports</div>
                            </div>
                            <div className="text-iec-pink-600 text-sm">Generate constituency reports</div>
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
