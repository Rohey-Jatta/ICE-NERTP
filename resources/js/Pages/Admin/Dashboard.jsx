import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function AdminDashboard({ auth, statistics, systemStatus }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">IEC Administrator Dashboard</h1>
                <p className="text-gray-400 mb-8">System Configuration & User Management</p>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2"></div>
                        <div className="text-2xl font-bold text-white">{statistics?.totalUsers || 0}</div>
                        <div className="text-gray-400 text-sm">Total Users</div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2"></div>
                        <div className="text-2xl font-bold text-white">{statistics?.totalStations || 0}</div>
                        <div className="text-gray-400 text-sm">Polling Stations</div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="text-4xl mb-2"></div>
                        <div className="text-2xl font-bold text-white">{statistics?.activeElections || 0}</div>
                        <div className="text-gray-400 text-sm">Active Elections</div>
                    </div>

                    <div className="bg-teal-900/40 rounded-xl p-6 border border-teal-700/50">
                        <div className="text-4xl mb-2">✓</div>
                        <div className="text-2xl font-bold text-teal-300">{systemStatus?.status || 'Running'}</div>
                        <div className="text-gray-400 text-sm">System Status</div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">User Management</h2>
                        <div className="space-y-3">
                            <Link
                                href="/admin/users"
                                className="block p-4 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Manage Users</div>
                                        <div className="text-teal-200 text-sm">Create, edit, deactivate users</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/admin/roles"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Roles & Permissions</div>
                                        <div className="text-gray-300 text-sm">Manage role assignments</div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Election Management</h2>
                        <div className="space-y-3">
                            <Link
                                href="/admin/elections"
                                className="block p-4 bg-teal-700 hover:bg-teal-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Manage Elections</div>
                                        <div className="text-teal-200 text-sm">Create and configure elections</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/admin/polling-stations"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Polling Stations</div>
                                        <div className="text-gray-300 text-sm">Register and assign stations</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/admin/parties"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Political Parties</div>
                                        <div className="text-gray-300 text-sm">Register parties & candidates</div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">System Settings</h2>
                        <div className="space-y-3">
                            <Link
                                href="/admin/audit-logs"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Audit Logs</div>
                                        <div className="text-gray-300 text-sm">View system activity</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/admin/settings"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl">⚙️</div>
                                    <div>
                                        <div className="font-bold text-white">System Configuration</div>
                                        <div className="text-gray-300 text-sm">Configure system settings</div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Monitoring</h2>
                        <div className="space-y-3">
                            <Link
                                href="/admin/system-health"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">System Health</div>
                                        <div className="text-gray-300 text-sm">Monitor performance</div>
                                    </div>
                                </div>
                            </Link>

                            <Link
                                href="/admin/backups"
                                className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="text-2xl"></div>
                                    <div>
                                        <div className="font-bold text-white">Backup & Recovery</div>
                                        <div className="text-gray-300 text-sm">Database backups</div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
