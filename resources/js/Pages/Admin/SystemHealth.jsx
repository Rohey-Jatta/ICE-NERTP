import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';

const StatusBadge = ({ status }) => {
    const cfg = {
        online:  { bg: 'bg-green-500/20',  border: 'border-green-500/50',  text: 'text-green-300',  dot: 'bg-green-400'  },
        offline: { bg: 'bg-red-500/20',    border: 'border-red-500/50',    text: 'text-red-300',    dot: 'bg-red-400'    },
        running: { bg: 'bg-green-500/20',  border: 'border-green-500/50',  text: 'text-green-300',  dot: 'bg-green-400'  },
        unknown: { bg: 'bg-gray-500/20',   border: 'border-gray-500/50',   text: 'text-gray-300',   dot: 'bg-gray-400'   },
        warning: { bg: 'bg-amber-500/20',  border: 'border-amber-500/50',  text: 'text-amber-300',  dot: 'bg-amber-400'  },
    }[status] || { bg: 'bg-gray-500/20', border: 'border-gray-500/50', text: 'text-gray-300', dot: 'bg-gray-400' };

    return (
        <span className={`inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold border ${cfg.bg} ${cfg.border} ${cfg.text}`}>
            <span className={`w-2 h-2 rounded-full ${cfg.dot} animate-pulse`} />
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function SystemHealth({ auth }) {
    const [health, setHealth] = useState(null);
    const [loading, setLoading] = useState(true);
    const [lastUpdated, setLastUpdated] = useState(null);
    const [error, setError] = useState(null);

    const fetchHealth = useCallback(async () => {
        try {
            const res = await fetch('/admin/system-health/data', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            setHealth(data);
            setLastUpdated(new Date());
            setError(null);
        } catch (err) {
            setError('Failed to fetch system data: ' + err.message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchHealth();
        const interval = setInterval(fetchHealth, 30000); // refresh every 30s
        return () => clearInterval(interval);
    }, [fetchHealth]);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-white">System Health</h1>
                        {lastUpdated && (
                            <p className="text-gray-500 text-sm mt-1">
                                Last updated: {lastUpdated.toLocaleTimeString()} — refreshes every 30s
                            </p>
                        )}
                    </div>
                    <button
                        onClick={() => { setLoading(true); fetchHealth(); }}
                        disabled={loading}
                        className="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white rounded-lg text-sm"
                    >
                        {loading ? 'Refreshing…' : '↻ Refresh Now'}
                    </button>
                </div>

                {error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300">
                        {error}
                    </div>
                )}

                {loading && !health ? (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {[1, 2, 3, 4, 5, 6].map(i => (
                            <div key={i} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 animate-pulse">
                                <div className="h-4 bg-slate-700 rounded w-24 mb-3" />
                                <div className="h-8 bg-slate-700 rounded w-16" />
                            </div>
                        ))}
                    </div>
                ) : health ? (
                    <>
                        {/* Core Services */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-gray-400 text-sm font-semibold">DATABASE</span>
                                    <StatusBadge status={health.database?.status || 'unknown'} />
                                </div>
                                <p className="text-white font-bold text-xl capitalize">{health.database?.driver || '—'}</p>
                                {health.database?.error && <p className="text-red-300 text-xs mt-1">{health.database.error}</p>}
                            </div>

                            <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-gray-400 text-sm font-semibold">CACHE</span>
                                    <StatusBadge status={health.cache?.status || 'unknown'} />
                                </div>
                                <p className="text-white font-bold text-xl capitalize">{health.cache?.driver || '—'}</p>
                                {health.cache?.error && <p className="text-red-300 text-xs mt-1">{health.cache.error}</p>}
                            </div>

                            <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-gray-400 text-sm font-semibold">QUEUE</span>
                                    <StatusBadge status={health.queue?.status || 'unknown'} />
                                </div>
                                <div className="flex gap-4">
                                    <div>
                                        <div className="text-amber-300 text-sm">Pending</div>
                                        <div className="text-white font-bold text-xl">{health.queue?.pending ?? '—'}</div>
                                    </div>
                                    <div>
                                        <div className="text-red-300 text-sm">Failed</div>
                                        <div className="text-white font-bold text-xl">{health.queue?.failed ?? '—'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Resources */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            {/* Disk */}
                            <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <h3 className="text-gray-400 text-sm font-semibold mb-4">DISK USAGE</h3>
                                {health.disk ? (
                                    <>
                                        <div className="flex justify-between text-sm mb-2">
                                            <span className="text-gray-300">Used: {health.disk.used}</span>
                                            <span className="text-gray-300">Free: {health.disk.free}</span>
                                        </div>
                                        <div className="w-full bg-slate-700 rounded-full h-3 mb-2">
                                            <div
                                                className={`h-3 rounded-full ${parseFloat(health.disk.used_percentage) > 80 ? 'bg-red-500' : parseFloat(health.disk.used_percentage) > 60 ? 'bg-amber-500' : 'bg-teal-500'}`}
                                                style={{ width: health.disk.used_percentage }}
                                            />
                                        </div>
                                        <p className="text-white font-bold">{health.disk.used_percentage} used of {health.disk.total}</p>
                                    </>
                                ) : <p className="text-gray-500">Unavailable</p>}
                            </div>

                            {/* Memory */}
                            <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <h3 className="text-gray-400 text-sm font-semibold mb-4">PHP MEMORY</h3>
                                {health.memory ? (
                                    <div className="space-y-3">
                                        <div className="flex justify-between">
                                            <span className="text-gray-300">Current Usage</span>
                                            <span className="text-white font-bold">{health.memory.php_memory_used}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-gray-300">Peak Usage</span>
                                            <span className="text-white font-bold">{health.memory.php_memory_peak}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-gray-300">Limit</span>
                                            <span className="text-amber-300 font-bold">{health.memory.php_memory_limit}</span>
                                        </div>
                                    </div>
                                ) : <p className="text-gray-500">Unavailable</p>}
                            </div>
                        </div>

                        {/* App Info */}
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                            <h3 className="text-gray-400 text-sm font-semibold mb-4">APPLICATION INFO</h3>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                {health.app && Object.entries({
                                    'Environment':    health.app.environment,
                                    'Debug Mode':     health.app.debug ? '⚠️ ON' : '✓ OFF',
                                    'PHP Version':    health.app.php_version,
                                    'Laravel Version':health.app.laravel_version,
                                }).map(([label, value]) => (
                                    <div key={label} className="bg-slate-900/50 p-4 rounded-lg">
                                        <div className="text-gray-400 text-xs">{label}</div>
                                        <div className={`font-bold mt-1 ${label === 'Debug Mode' && health.app.debug ? 'text-amber-300' : 'text-white'}`}>{value}</div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Log Info */}
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <h3 className="text-gray-400 text-sm font-semibold mb-4">LOG FILE</h3>
                            {health.logs ? (
                                <div className="flex gap-6">
                                    <div>
                                        <div className="text-gray-300 text-sm">Status</div>
                                        <div className={`font-bold ${health.logs.exists ? 'text-green-300' : 'text-red-300'}`}>
                                            {health.logs.exists ? '✓ Exists' : '✗ Not found'}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-gray-300 text-sm">File Size</div>
                                        <div className="text-white font-bold">{health.logs.size}</div>
                                    </div>
                                    <div>
                                        <div className="text-gray-300 text-sm">Recent Errors</div>
                                        <div className={`font-bold ${health.logs.recent_errors > 0 ? 'text-amber-300' : 'text-green-300'}`}>
                                            {health.logs.recent_errors} in last 24h
                                        </div>
                                    </div>
                                </div>
                            ) : <p className="text-gray-500">Unavailable</p>}
                        </div>
                    </>
                ) : null}
            </div>
        </AppLayout>
    );
}