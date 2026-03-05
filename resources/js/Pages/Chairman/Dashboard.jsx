import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function ChairmanDashboard({ auth, pendingNational, statistics, recentActivity }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">IEC Chairman Dashboard</h1>
                <p className="text-gray-400 mb-8">National Election Oversight & Final Certification</p>

                <div className="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                    <div className="bg-amber-900/40 rounded-xl p-6 border border-amber-700/50">
                        <div className="text-4xl mb-2"></div>
                        <div className="text-2xl font-bold text-amber-300">{pendingNational || 0}</div>
                        <div className="text-gray-400 text-sm">Awaiting National Certification</div>
                    </div>

                    <div className="bg-teal-900/40 rounded-xl p-6 border border-teal-700/50">
                        <div className="text-4xl mb-2">✓</div>
                        <div className="text-2xl font-bold text-teal-300">{statistics?.nationallyCertified || 0}</div>
                        <div className="text-gray-400 text-sm">Nationally Certified</div>
                    </div>

                    <div className="bg-slate-700/40 rounded-xl p-6 border border-slate-600/50">
                        <div className="text-4xl mb-2"></div>
                        <div className="text-2xl font-bold text-white">{statistics?.totalStations || 0}</div>
                        <div className="text-gray-400 text-sm">Total Stations</div>
                    </div>

                    <div className="bg-slate-700/40 rounded-xl p-6 border border-slate-600/50">
                        <div className="text-4xl mb-2"></div>
                        <div className="text-2xl font-bold text-white">{statistics?.totalVoters?.toLocaleString() || 0}</div>
                        <div className="text-gray-400 text-sm">Registered Voters</div>
                    </div>

                    <div className="bg-slate-700/40 rounded-xl p-6 border border-slate-600/50">
                        <div className="text-4xl mb-2"></div>
                        <div className="text-2xl font-bold text-white">{statistics?.nationalProgress || 0}%</div>
                        <div className="text-gray-400 text-sm">National Progress</div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Quick Actions</h2>
                        <div className="space-y-3">
                            <Link
                                href="/chairman/national-queue"
                                className="block p-4 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">National Certification Queue</div>
                                        <div className="text-teal-200 text-sm">{pendingNational} results awaiting final approval</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/chairman/all-results"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">View All Results</div>
                                        <div className="text-gray-300 text-sm">Complete national overview</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/chairman/analytics"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Full Analytics</div>
                                        <div className="text-gray-300 text-sm">Comprehensive reports</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/chairman/publish"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Publish Results</div>
                                        <div className="text-gray-300 text-sm">Make results public</div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Recent Activity</h2>
                        <div className="space-y-3 max-h-80 overflow-y-auto">
                            {recentActivity?.length > 0 ? (
                                recentActivity.map((activity, i) => (
                                    <div key={i} className="p-3 bg-slate-900/50 rounded-lg border border-slate-700/30">
                                        <div className="text-sm text-white">{activity.action}</div>
                                        <div className="text-xs text-gray-400 mt-1">{activity.time}</div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-gray-400 text-center py-8">No recent activity</div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
