import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';

export default function AuditLogs({ auth, logs, filters }) {
    const [selectedLog, setSelectedLog] = useState(null);

    const { data, setData, get, processing } = useForm({
        user: filters.user || '',
        action: filters.action || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });

    const applyFilters = (e) => {
        e.preventDefault();
        get('/admin/audit-logs', {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setData({
            user: '',
            action: '',
            date_from: '',
            date_to: '',
        });
        router.get('/admin/audit-logs', {}, {
            preserveState: false,
            replace: true,
        });
    };

    return (
        <AppLayout user={auth.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">Audit Logs</h1>
                    <a href="/admin/dashboard" className="px-4 py-2 bg-blue-500 text-white rounded-lg">
                        Back to Admin
                    </a>
                </div>

                {/* Filters */}
                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                    <h2 className="text-xl font-bold text-white mb-4">Filters</h2>
                    <form onSubmit={applyFilters} className="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <input
                                type="text"
                                value={data.user}
                                onChange={(e) => setData('user', e.target.value)}
                                placeholder="Search by user name/email..."
                                className="w-full px-4 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            />
                        </div>
                        <div>
                            <input
                                type="text"
                                value={data.action}
                                onChange={(e) => setData('action', e.target.value)}
                                placeholder="Filter by action..."
                                className="w-full px-4 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            />
                        </div>
                        <div>
                            <input
                                type="date"
                                value={data.date_from}
                                onChange={(e) => setData('date_from', e.target.value)}
                                className="w-full px-4 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            />
                        </div>
                        <div>
                            <input
                                type="date"
                                value={data.date_to}
                                onChange={(e) => setData('date_to', e.target.value)}
                                className="w-full px-4 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            />
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600 text-white rounded-lg flex-1"
                            >
                                {processing ? 'Filtering...' : 'Apply'}
                            </button>
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg"
                            >
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                {/* Audit Logs Table */}
                <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-900/50">
                                <tr>
                                    <th className="px-6 py-4 text-left text-white font-semibold">Timestamp</th>
                                    <th className="px-6 py-4 text-left text-white font-semibold">User</th>
                                    <th className="px-6 py-4 text-left text-white font-semibold">Action</th>
                                    <th className="px-6 py-4 text-left text-white font-semibold">Model</th>
                                    <th className="px-6 py-4 text-left text-white font-semibold">IP Address</th>
                                    <th className="px-6 py-4 text-left text-white font-semibold">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-700/30">
                                {logs?.data?.length > 0 ? (
                                    logs.data.map((log) => (
                                        <tr key={log.id} className="hover:bg-slate-900/30">
                                            <td className="px-6 py-4 text-gray-300 text-sm">
                                                {new Date(log.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 text-white">{log.user?.name || 'System'}</td>
                                            <td className="px-6 py-4">
                                                <span className="px-3 py-1 bg-teal-500/20 text-teal-300 rounded-full text-xs">
                                                    {log.action}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-gray-300">{log.model}</td>
                                            <td className="px-6 py-4 text-gray-400 text-sm">{log.ip_address}</td>
                                            <td className="px-6 py-4">
                                                <button
                                                    onClick={() => setSelectedLog(log)}
                                                    className="text-teal-400 hover:text-teal-300 text-sm"
                                                >
                                                    View Details →
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-gray-400">
                                            No audit logs found
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Detail Modal */}
                {selectedLog && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
                        <div className="bg-slate-800 rounded-xl p-8 max-w-2xl w-full border border-slate-700">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-2xl font-bold text-white">Audit Log Details</h3>
                                <button
                                    onClick={() => setSelectedLog(null)}
                                    className="text-gray-400 hover:text-white text-2xl"
                                >
                                    ×
                                </button>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <div className="text-sm text-gray-400">Action</div>
                                    <div className="text-white font-semibold">{selectedLog.action}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-400">User</div>
                                    <div className="text-white">{selectedLog.user?.name}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-400">IP Address</div>
                                    <div className="text-white">{selectedLog.ip_address}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-400">User Agent</div>
                                    <div className="text-white text-sm">{selectedLog.user_agent}</div>
                                </div>
                                {selectedLog.old_values && (
                                    <div>
                                        <div className="text-sm text-gray-400 mb-2">Old Values</div>
                                        <pre className="bg-slate-900/50 p-4 rounded-lg text-xs text-gray-300 overflow-x-auto">
                                            {JSON.stringify(selectedLog.old_values, null, 2)}
                                        </pre>
                                    </div>
                                )}
                                {selectedLog.new_values && (
                                    <div>
                                        <div className="text-sm text-gray-400 mb-2">New Values</div>
                                        <pre className="bg-slate-900/50 p-4 rounded-lg text-xs text-gray-300 overflow-x-auto">
                                            {JSON.stringify(selectedLog.new_values, null, 2)}
                                        </pre>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
