import { Badge, Button, DataTable, PageHeader, Panel } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { useCallback, useEffect, useState } from 'react';

export default function Backups({ auth }) {
    const [backups, setBackups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [creating, setCreating] = useState(false);
    const [message, setMessage] = useState(null);
    const [error, setError] = useState(null);

    const fetchBackups = useCallback(async () => {
        try {
            const res = await fetch('/admin/backups/list', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            setBackups(data);
            setError(null);
        } catch (err) {
            setError(`Could not load backup list: ${err.message}`);
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
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            setMessage({ type: data.success ? 'success' : 'error', text: data.message });

            if (data.success) fetchBackups();
        } catch (err) {
            setMessage({ type: 'error', text: `Backup creation failed: ${err.message}` });
        } finally {
            setCreating(false);
        }
    };

    const columns = [
        {
            key: 'name',
            header: 'Backup',
            render: (backup) => (
                <div>
                    <div className="font-semibold text-iec-navy">{backup.name}</div>
                    <div className="text-xs text-slate-500">{backup.date}</div>
                </div>
            ),
        },
        {
            key: 'size',
            header: 'Size',
            render: (backup) => <Badge tone="blue">{backup.size}</Badge>,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (backup) => (
                <a
                    href={`/admin/backups/download?file=${encodeURIComponent(backup.path)}`}
                    className="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-200 transition-colors hover:border-slate-500 hover:bg-white"
                    download
                >
                    Download
                </a>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Backup Management"
                    description="Database backups are stored securely and listed for administrator download."
                    actions={(
                        <>
                            <Button
                                onClick={() => {
                                    setLoading(true);
                                    fetchBackups();
                                }}
                                variant="secondary"
                                disabled={loading}
                            >
                                Refresh
                            </Button>
                            <Button onClick={handleCreateBackup} disabled={creating}>
                                {creating ? 'Creating Backup' : 'Create Backup'}
                            </Button>
                        </>
                    )}
                />

                {message && (
                    <div className={`mb-5 rounded-lg border p-4 text-sm ${message.type === 'success' ? 'border-teal-500/40 bg-iec-pink-500/10 text-iec-pink-600' : 'border-rose-500/40 bg-rose-500/10 text-rose-300'}`}>
                        {message.text}
                    </div>
                )}
                {error && (
                    <div className="mb-5 rounded-lg border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-300">
                        {error}
                    </div>
                )}

                <Panel className="mb-5 p-4">
                    <p className="text-sm text-slate-400">
                        Backups use <code className="rounded bg-slate-50 px-1.5 py-0.5 text-slate-200">spatie/laravel-backup</code>. Confirm <code className="rounded bg-slate-50 px-1.5 py-0.5 text-slate-200">config/backup.php</code> and the <code className="rounded bg-slate-50 px-1.5 py-0.5 text-slate-200">backup:run</code> artisan command are configured for the environment.
                    </p>
                </Panel>

                {loading ? (
                    <Panel className="p-12 text-center text-sm text-slate-500">Loading backups...</Panel>
                ) : (
                    <DataTable
                        columns={columns}
                        rows={backups}
                        empty="No backups found"
                        rowKey={(backup) => backup.path || backup.name}
                    />
                )}
            </div>
        </AppLayout>
    );
}
