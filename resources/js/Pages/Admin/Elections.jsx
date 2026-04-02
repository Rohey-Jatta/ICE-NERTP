import AppLayout from '@/Layouts/AppLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Elections({ auth, elections = [], flash }) {
    const [editingElection, setEditingElection] = useState(null);
    const [showDeactivateConfirm, setShowDeactivateConfirm] = useState(null);

    // ── Edit form ──────────────────────────────────────────────────────
    const { data, setData, put, processing, errors, reset } = useForm({
        name:   '',
        type:   'presidential',
        date:   '',
        status: 'active',
    });

    const openEdit = (election) => {
        setData({
            name:   election.name,
            type:   election.type?.replace('_', '-') || 'presidential',
            date:   election.date || '',
            status: election.status || 'active',
        });
        setEditingElection(election);
    };

    const closeEdit = () => {
        reset();
        setEditingElection(null);
    };

    const handleUpdate = (e) => {
        e.preventDefault();
        put(`/admin/elections/${editingElection.id}`, {
            onSuccess: () => closeEdit(),
        });
    };

    // ── Deactivate ─────────────────────────────────────────────────────
    const handleDeactivate = (election) => {
        router.patch(`/admin/elections/${election.id}/toggle-status`, {}, {
            onSuccess: () => setShowDeactivateConfirm(null),
        });
    };

    const statusColors = {
        active:          'bg-teal-500/20 text-teal-300 border-teal-500/30',
        draft:           'bg-gray-500/20 text-gray-300 border-gray-500/30',
        certified:       'bg-green-500/20 text-green-300 border-green-500/30',
        results_pending: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        certifying:      'bg-blue-500/20 text-blue-300 border-blue-500/30',
        archived:        'bg-slate-500/20 text-slate-400 border-slate-500/30',
    };

    const typeOptions = [
        { value: 'presidential',  label: 'Presidential' },
        { value: 'parliamentary', label: 'Parliamentary' },
        { value: 'local',         label: 'Local Council' },
        { value: 'referendum',    label: 'Referendum' },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-white">Election Management</h1>
                    </div>
                    <Link href="/admin/elections/create" className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        + Create New Election
                    </Link>
                </div>

                {/* Flash messages */}
                {flash?.success && (
                    <div className="mb-6 p-4 bg-green-500/20 border border-green-500/50 rounded-lg text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300">
                        {flash.error}
                    </div>
                )}

                {/* Elections list */}
                <div className="space-y-4">
                    {elections.length > 0 ? (
                        elections.map((election) => (
                            <div key={election.id}
                                 className={`bg-slate-800/40 rounded-xl p-6 border transition-colors ${
                                     election.status === 'archived'
                                         ? 'border-slate-700/30 opacity-60'
                                         : 'border-slate-700/50'
                                 }`}>
                                <div className="flex justify-between items-start">
                                    {/* Info */}
                                    <div className="flex-1">
                                        <h3 className="text-xl font-bold text-white mb-1">{election.name}</h3>
                                        <p className="text-gray-400 capitalize text-sm">
                                            {election.type?.replace(/_/g, ' ')} &bull; {election.date}
                                        </p>
                                    </div>

                                    {/* Status badge */}
                                    <span className={`px-3 py-1 rounded-full text-xs font-semibold border mr-4 ${
                                        statusColors[election.status] || statusColors.draft
                                    }`}>
                                        {election.status?.replace(/_/g, ' ')}
                                    </span>

                                    {/* Actions */}
                                    <div className="flex gap-2 flex-shrink-0">
                                        {/* Edit */}
                                        <button
                                            onClick={() => openEdit(election)}
                                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-colors"
                                        >
                                            Edit
                                        </button>

                                        {/* Deactivate / Activate toggle */}
                                        {election.status !== 'certified' && (
                                            <button
                                                onClick={() => setShowDeactivateConfirm(election)}
                                                className={`px-4 py-2 rounded-lg text-sm font-semibold transition-colors ${
                                                    election.status === 'archived'
                                                        ? 'bg-teal-600 hover:bg-teal-700 text-white'
                                                        : 'bg-red-600/80 hover:bg-red-700 text-white'
                                                }`}
                                            >
                                                {election.status === 'archived' ? '✅ Activate' : '🚫 Deactivate'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400 mb-4">No elections configured yet.</p>
                            <Link href="/admin/elections/create"
                                  className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg inline-block">
                                Create First Election
                            </Link>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Edit Modal ───────────────────────────────────────────────── */}
            {editingElection && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div className="bg-slate-800 rounded-2xl border border-slate-700 w-full max-w-lg shadow-2xl">
                        <div className="flex items-center justify-between p-6 border-b border-slate-700">
                            <h2 className="text-xl font-bold text-white">Edit Election</h2>
                            <button onClick={closeEdit} className="text-gray-400 hover:text-white text-2xl leading-none">×</button>
                        </div>

                        <form onSubmit={handleUpdate} className="p-6 space-y-4">
                            {/* Name */}
                            <div>
                                <label className="block text-gray-300 mb-1 text-sm font-semibold">Election Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/60 border border-slate-600 rounded-lg text-white text-sm"
                                    placeholder="e.g., National Presidential Election 2026"
                                    required
                                />
                                {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
                            </div>

                            {/* Type */}
                            <div>
                                <label className="block text-gray-300 mb-1 text-sm font-semibold">Election Type</label>
                                <select
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/60 border border-slate-600 rounded-lg text-white text-sm"
                                >
                                    {typeOptions.map(o => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </select>
                                {errors.type && <p className="text-red-400 text-xs mt-1">{errors.type}</p>}
                            </div>

                            {/* Date */}
                            <div>
                                <label className="block text-gray-300 mb-1 text-sm font-semibold">Election Date</label>
                                <input
                                    type="date"
                                    value={data.date}
                                    onChange={(e) => setData('date', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/60 border border-slate-600 rounded-lg text-white text-sm"
                                    required
                                />
                                {errors.date && <p className="text-red-400 text-xs mt-1">{errors.date}</p>}
                            </div>

                            {/* Status */}
                            <div>
                                <label className="block text-gray-300 mb-1 text-sm font-semibold">Status</label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/60 border border-slate-600 rounded-lg text-white text-sm"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="configured">Configured</option>
                                    <option value="active">Active</option>
                                    <option value="results_pending">Results Pending</option>
                                    <option value="certifying">Certifying</option>
                                    <option value="certified">Certified</option>
                                    <option value="archived">Archived</option>
                                </select>
                                {errors.status && <p className="text-red-400 text-xs mt-1">{errors.status}</p>}
                            </div>

                            {/* Buttons */}
                            <div className="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg text-sm"
                                >
                                    {processing ? 'Saving…' : 'Save Changes'}
                                </button>
                                <button
                                    type="button"
                                    onClick={closeEdit}
                                    className="flex-1 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Deactivate Confirm Modal ─────────────────────────────────── */}
            {showDeactivateConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div className="bg-slate-800 rounded-2xl border border-slate-700 w-full max-w-md shadow-2xl p-6">
                        <h2 className="text-xl font-bold text-white mb-3">
                            {showDeactivateConfirm.status === 'archived' ? 'Activate Election' : 'Deactivate Election'}
                        </h2>
                        <p className="text-gray-300 mb-6 text-sm leading-relaxed">
                            {showDeactivateConfirm.status === 'archived'
                                ? <>Are you sure you want to <strong className="text-teal-300">activate</strong> <em>{showDeactivateConfirm.name}</em>? It will be set to <strong>active</strong> status.</>
                                : <>Are you sure you want to <strong className="text-red-300">deactivate</strong> <em>{showDeactivateConfirm.name}</em>? It will be archived and hidden from active workflows.</>
                            }
                        </p>
                        <div className="flex gap-3">
                            <button
                                onClick={() => handleDeactivate(showDeactivateConfirm)}
                                className={`flex-1 py-3 font-bold rounded-lg text-white text-sm ${
                                    showDeactivateConfirm.status === 'archived'
                                        ? 'bg-teal-600 hover:bg-teal-700'
                                        : 'bg-red-600 hover:bg-red-700'
                                }`}
                            >
                                {showDeactivateConfirm.status === 'archived' ? 'Yes, Activate' : 'Yes, Deactivate'}
                            </button>
                            <button
                                onClick={() => setShowDeactivateConfirm(null)}
                                className="flex-1 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
