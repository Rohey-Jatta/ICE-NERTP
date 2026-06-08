import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { Badge, Button, DataTable, Field, PageHeader, Pagination, Panel, Toolbar, inputClass } from '@/Components/AdminUI';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

const moduleOptions = ['Authentication', 'ElectionManagement', 'PartyManagement', 'PollingStation', 'UserManagement', 'System'];
const outcomeTone = (outcome) => {
    if (outcome === 'success') return 'teal';
    if (outcome === 'blocked') return 'amber';
    if (outcome === 'failure') return 'rose';
    return 'slate';
};

export default function AuditLogs({ auth, logs = {}, filters = {} }) {
    const [selectedLog, setSelectedLog] = useState(null);

    const { data, setData, get, processing } = useForm({
        user: filters.user || '',
        action: filters.action || '',
        module: filters.module || '',
        outcome: filters.outcome || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
    });

    const applyFilters = (event) => {
        event.preventDefault();
        get('/admin/audit-logs', { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        router.get('/admin/audit-logs', {}, { preserveState: false, replace: true });
    };

    const rows = logs.data ?? [];
    const columns = [
        {
            key: 'created_at',
            header: 'Timestamp',
            render: (log) => <span className="whitespace-nowrap ws-row-mono">{new Date(log.created_at).toLocaleString()}</span>,
        },
        {
            key: 'user',
            header: 'User',
            render: (log) => (
                <div>
                    <div className="ws-row-strong">{log.user?.name || 'System'}</div>
                    {log.user_role && <div className="ws-row-muted mt-0.5">{log.user_role.replace(/-/g, ' ')}</div>}
                </div>
            ),
        },
        { key: 'module', header: 'Module', render: (log) => <Badge tone="blue">{log.module}</Badge> },
        { key: 'action', header: 'Action', render: (log) => <span className="ws-row-mono">{log.action}</span> },
        { key: 'outcome', header: 'Outcome', render: (log) => <Badge tone={outcomeTone(log.outcome)}>{log.outcome || 'success'}</Badge> },
        { key: 'ip_address', header: 'IP Address', render: (log) => <span className="ws-row-mono">{log.ip_address || '—'}</span> },
        {
            key: 'details',
            header: 'Details',
            align: 'right',
            render: (log) => <Button variant="secondary" onClick={() => setSelectedLog(log)}>View</Button>,
        },
    ];

    return (
        <AppLayout user={auth.user}>
            <div className="ws-container">
                <PageHeader
                    title="Audit Logs"
                    description="Authentication, configuration, and administrative activity with user, IP, and timestamp evidence."
                />

                <form onSubmit={applyFilters}>
                    <Toolbar>
                        <Field label="User">
                            <input value={data.user} onChange={(event) => setData('user', event.target.value)} placeholder="Name or email" className={inputClass} />
                        </Field>
                        <Field label="Action">
                            <input value={data.action} onChange={(event) => setData('action', event.target.value)} placeholder="auth.login.success" className={inputClass} />
                        </Field>
                        <Field label="Module">
                            <SearchableSelect
                                value={data.module}
                                onChange={(val) => setData('module', val)}
                                options={[{ value: '', label: 'All modules' }, ...moduleOptions.map((module) => ({ value: module, label: module }))]}
                                placeholder="Select module"
                                className="w-full"
                            />
                        </Field>
                        <Field label="Outcome">
                            <SearchableSelect
                                value={data.outcome}
                                onChange={(val) => setData('outcome', val)}
                                options={[
                                    { value: '', label: 'All outcomes' },
                                    { value: 'success', label: 'Success' },
                                    { value: 'failure', label: 'Failure' },
                                    { value: 'blocked', label: 'Blocked' }
                                ]}
                                placeholder="Select outcome"
                                className="w-full"
                            />
                        </Field>
                        <div className="flex items-end gap-2">
                            <Button type="submit" disabled={processing} className="flex-1">{processing ? 'Applying...' : 'Apply Filters'}</Button>
                            <Button variant="secondary" onClick={clearFilters}>Clear</Button>
                        </div>
                    </Toolbar>
                </form>

                <DataTable columns={columns} rows={rows} empty="No audit logs match the current filters." />
                <Pagination links={logs.links} />

                {selectedLog && (
                    <div className="ws-modal-backdrop" onClick={() => setSelectedLog(null)}>
                        <div className="ws-modal" style={{ maxWidth: '52rem' }} onClick={(e) => e.stopPropagation()}>
                            <div className="ws-modal-strip" />
                            <div className="ws-modal-header">
                                <div>
                                    <h3 className="ws-modal-title">Audit Log Details</h3>
                                    <p className="ws-modal-subtitle">{selectedLog.action}</p>
                                </div>
                                <button className="ws-modal-close" onClick={() => setSelectedLog(null)} aria-label="Close">×</button>
                            </div>
                            <div className="ws-modal-body space-y-4">
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    {[
                                        ['Timestamp', new Date(selectedLog.created_at).toLocaleString()],
                                        ['User', selectedLog.user?.name || 'System'],
                                        ['Module', selectedLog.module],
                                        ['Outcome', selectedLog.outcome || 'success'],
                                        ['IP Address', selectedLog.ip_address || '—'],
                                        ['Role', selectedLog.user_role || '—'],
                                    ].map(([label, value]) => (
                                        <div key={label} className="rounded-md border border-gray-200 bg-gray-50 p-3">
                                            <div className="ws-label">{label}</div>
                                            <div className="text-sm text-slate-800">{value}</div>
                                        </div>
                                    ))}
                                </div>
                                {selectedLog.user_agent && (
                                    <div>
                                        <div className="ws-label">User Agent</div>
                                        <p className="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-slate-700 break-words">{selectedLog.user_agent}</p>
                                    </div>
                                )}
                                {(selectedLog.old_values || selectedLog.new_values) && (
                                    <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                        {selectedLog.old_values && (
                                            <div>
                                                <div className="ws-label">Old values</div>
                                                <pre className="overflow-x-auto rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-slate-700">{JSON.stringify(selectedLog.old_values, null, 2)}</pre>
                                            </div>
                                        )}
                                        {selectedLog.new_values && (
                                            <div>
                                                <div className="ws-label">New values</div>
                                                <pre className="overflow-x-auto rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-slate-700">{JSON.stringify(selectedLog.new_values, null, 2)}</pre>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                            <div className="ws-modal-footer">
                                <Button variant="secondary" onClick={() => setSelectedLog(null)}>Close</Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
