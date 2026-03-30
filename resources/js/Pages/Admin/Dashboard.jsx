import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function AdminDashboard({ auth, statistics, systemStatus }) {
    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">IEC Administrator Dashboard</h1>
                <p className="text-gray-400 mb-8">System Configuration & User Management</p>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-white">{statistics?.totalUsers || 0}</div>
                        <div className="text-gray-400 text-sm">Total Users</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-white">{statistics?.totalStations || 0}</div>
                        <div className="text-gray-400 text-sm">Polling Stations</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-white">{statistics?.activeElections || 0}</div>
                        <div className="text-gray-400 text-sm">Active Elections</div>
                    </div>
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-pink-300/50">
                        <div className="text-2xl font-bold text-white">{systemStatus?.status || 'Running'}</div>
                        <div className="text-gray-400 text-sm">System Status</div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* User Management */}
                    <div className="bg-slate-900/40 rounded-xl p-8 border border-slate-700/50">
                        <h2 className="text-2xl font-bold text-white mb-6">User Management</h2>
                        <div className="space-y-3">
                            {[
                                { href: '/admin/users', title: 'Manage Users', desc: 'Create, edit, deactivate users' },
                                { href: '/admin/party-representatives', title: 'Party Representatives', desc: 'Manage party reps & assignments' },
                                { href: '/admin/election-monitors', title: 'Election Monitors', desc: 'Manage monitors & accreditations' },
                                { href: '/admin/roles', title: 'Roles & Permissions', desc: 'Manage role assignments' },
                            ].map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                                >
                                    <div className="font-bold text-white">{item.title}</div>
                                    <div className="text-gray-300 text-sm">{item.desc}</div>
                                </Link>
                            ))}
                        </div>
                    </div>

                    {/* Election Management */}
                    <div className="bg-slate-900/40 rounded-xl p-8 border border-slate-800/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Election Management</h2>
                        <div className="space-y-3">
                            {[
                                { href: '/admin/elections', title: 'Manage Elections', desc: 'Create and configure elections' },
                                { href: '/admin/polling-stations', title: 'Polling Stations', desc: 'Register and assign stations' },
                                { href: '/admin/parties', title: 'Political Parties', desc: 'Register parties & candidates' },
                            ].map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="block p-4 bg-slate-600 hover:bg-slate-500 rounded-lg transition-colors"
                                >
                                    <div className="font-bold text-white">{item.title}</div>
                                    <div className="text-gray-300 text-sm">{item.desc}</div>
                                </Link>
                            ))}
                        </div>
                    </div>

                    {/* System Settings */}
                    <div className="bg-slate-900/40 rounded-xl p-8 border border-slate-800/50">
                        <h2 className="text-2xl font-bold text-white mb-6">System Settings</h2>
                        <div className="space-y-3">
                            {[
                                { href: '/admin/audit-logs', title: 'Audit Logs', desc: 'View system activity' },
                                { href: '/admin/settings', title: 'System Configuration', desc: 'Configure system settings' },
                            ].map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="block p-4 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                                >
                                    <div className="font-bold text-white">{item.title}</div>
                                    <div className="text-gray-300 text-sm">{item.desc}</div>
                                </Link>
                            ))}
                        </div>
                    </div>

                    {/* Monitoring */}
                    <div className="bg-slate-900/40 rounded-xl p-8 border border-slate-800/50">
                        <h2 className="text-2xl font-bold text-white mb-6">Monitoring</h2>
                        <div className="space-y-3">
                            {[
                                { href: '/admin/system-health', title: 'System Health', desc: 'Monitor real-time performance' },
                                { href: '/admin/backups', title: 'Backup & Recovery', desc: 'Database backups' },
                            ].map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="block p-4 bg-slate-600 hover:bg-slate-500 rounded-lg transition-colors"
                                >
                                    <div className="font-bold text-white">{item.title}</div>
                                    <div className="text-gray-300 text-sm">{item.desc}</div>
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}