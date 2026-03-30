import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';

export default function Backups({ auth }) {
    const [backups, setBackups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [creating, setCreating] = useState(false);
    const [message, setMessage] = useState(null);
    const [error, setError] = useState(null);

    const fetchBackups = useCallback(async () => {
        try {
            const res = await fetch('/admin/backups/list', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            setBackups(data);
            setError(null);
        } catch (err) {
            setError('Could not load backup list: ' + err.message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchBackups();
    }, [fetchBackups]);

    const handleCreateBackup = async () => {
        if (!window.confirm('Create a new database backup now?')) return;
        setCreating(true);
        setMessage(null);
        try {
            const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
            const res = await fetch('/admin/backups/create', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (data.success) {
                setMessage({ type: 'success', text: data.message });
                fetchBackups(); // Refresh list
            } else {
                setMessage({ type: 'error', text: data.message });
            }
        } catch (err) {
            setMessage({ type: 'error', text: 'Backup creation failed: ' + err.message });
        } finally {
            setCreating(false);
        }
    };

    const formatBytes = (bytes) => {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-white">Backup Management</h1>
                        <p className="text-gray-400 mt-1">Database backups are stored securely.</p>
                    </div>
                    <button
                        onClick={handleCreateBackup}
                        disabled={creating}
                        className="px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg flex items-center gap-2"
                    >
                        {creating ? (
                            <>
                                <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Creating Backup…
                            </>
                        ) : '+ Create Backup Now'}
                    </button>
                </div>

                {/* Messages */}
                {message && (
                    <div className={`mb-6 p-4 rounded-lg border ${
                        message.type === 'success'
                            ? 'bg-green-500/20 border-green-500/50 text-green-300'
                            : 'bg-red-500/20 border-red-500/50 text-red-300'
                    }`}>
                        {message.text}
                    </div>
                )}
                {error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300">
                        {error}
                    </div>
                )}

                {/* Info Banner */}
                <div className="mb-6 p-4 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                    <p className="text-blue-300 text-sm">
                        <strong>ℹ️ Note:</strong> Backups use{' '}
                        <code className="bg-blue-900/30 px-1 rounded">spatie/laravel-backup</code>.
                        Ensure it is configured in <code className="bg-blue-900/30 px-1 rounded">config/backup.php</code>{' '}
                        and the <code className="bg-blue-900/30 px-1 rounded">backup:run</code> artisan command works.
                    </p>
                </div>

                {/* Backup List */}
                <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
                    <div className="p-6 border-b border-slate-700/50 flex items-center justify-between">
                        <h2 className="text-xl font-bold text-white">Available Backups ({backups.length})</h2>
                        <button
                            onClick={() => { setLoading(true); fetchBackups(); }}
                            disabled={loading}
                            className="text-gray-400 hover:text-white text-sm"
                        >
                            ↻ Refresh
                        </button>
                    </div>

                    {loading ? (
                        <div className="p-12 text-center">
                            <div className="animate-spin h-8 w-8 border-2 border-teal-500 border-t-transparent rounded-full mx-auto mb-4" />
                            <p className="text-gray-400">Loading backups…</p>
                        </div>
                    ) : backups.length === 0 ? (
                        <div className="p-12 text-center">
                            <p className="text-gray-400 mb-2">No backups found.</p>
                            <p className="text-gray-500 text-sm">Click "Create Backup Now" to create your first backup.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-700/30">
                            {backups.map((backup, i) => (
                                <div key={i} className="p-6 flex items-center justify-between hover:bg-slate-700/20">
                                    <div>
                                        <div className="text-white font-medium">{backup.name}</div>
                                        <div className="text-gray-400 text-sm mt-1">
                                            {backup.date} &bull; {backup.size}
                                        </div>
                                    </div>
                                    
                                     <a href={`/admin/backups/download?file=${encodeURIComponent(backup.path)}`}
                                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm"
                                        download
                                    >
                                        ⬇ Download
                                    </a>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}