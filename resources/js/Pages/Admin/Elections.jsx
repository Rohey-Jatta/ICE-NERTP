import AppLayout from '@/Layouts/AppLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

/* ── Multi-select party picker ─────────────────────────────────────────── */
function PartySelector({ allParties, selectedIds, onChange }) {
    const toggle = (id) => {
        onChange(selectedIds.includes(id)
            ? selectedIds.filter(p => p !== id)
            : [...selectedIds, id]);
    };

    const parseColors = (c) => c
        ? c.split(',').map(x => x.trim()).filter(x => /^#[0-9a-fA-F]{6}$/.test(x))
        : [];

    if (!allParties || allParties.length === 0) {
        return (
            <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                <p className="text-amber-300 text-sm">
                    No parties registered yet.{' '}
                    <Link href="/admin/parties/create" className="underline text-teal-400">Register a party first</Link>.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-2 max-h-56 overflow-y-auto pr-1">
            {allParties.map((party) => {
                const selected = selectedIds.includes(party.id);
                const colors   = parseColors(party.color);
                return (
                    <label
                        key={party.id}
                        className={`flex items-center gap-3 p-3 rounded-lg cursor-pointer border transition-colors ${
                            selected
                                ? 'bg-teal-900/30 border-teal-500/50'
                                : 'bg-slate-900/30 border-slate-700/30 hover:bg-slate-900/50'
                        }`}
                    >
                        <input
                            type="checkbox"
                            checked={selected}
                            onChange={() => toggle(party.id)}
                            className="h-4 w-4 text-teal-600 bg-slate-900 border-slate-600 rounded"
                        />
                        <div className="flex gap-0.5 flex-shrink-0">
                            {colors.length > 0
                                ? colors.map((c, i) => (
                                    <span key={i} className="w-4 h-4 rounded-sm border border-white/20" style={{ backgroundColor: c }} />
                                ))
                                : <span className="w-4 h-4 rounded-sm bg-gray-500 border border-white/20" />
                            }
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="text-white text-sm font-medium truncate">{party.name}</div>
                            <div className="text-gray-400 text-xs">{party.abbreviation}</div>
                        </div>
                        {selected && <span className="text-teal-400 text-xs font-semibold flex-shrink-0">✓ Selected</span>}
                    </label>
                );
            })}
        </div>
    );
}

/* ── Party badge strip on card ─────────────────────────────────────────── */
function PartyBadges({ parties }) {
    if (!parties || parties.length === 0) {
        return <span className="text-gray-600 text-xs italic">No parties assigned</span>;
    }
    const parseColors = (c) => c
        ? c.split(',').map(x => x.trim()).filter(x => /^#[0-9a-fA-F]{6}$/.test(x))
        : [];

    return (
        <div className="flex flex-wrap gap-2 mt-2">
            {parties.map((party) => {
                const colors       = parseColors(party.color);
                const primaryColor = colors[0] || '#6b7280';
                return (
                    <span
                        key={party.id}
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border border-white/10"
                        style={{ backgroundColor: primaryColor + '28', color: primaryColor }}
                    >
                        <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: primaryColor }} />
                        {party.abbreviation}
                    </span>
                );
            })}
        </div>
    );
}

const STATUS_META = {
    active:          { label: 'Active',          stripe: 'from-teal-400 to-teal-600',   badge: 'bg-teal-500/20 text-teal-300 border-teal-500/30',   glow: 'shadow-teal-900/30' },
    draft:           { label: 'Draft',           stripe: 'from-slate-400 to-slate-600', badge: 'bg-gray-500/20 text-gray-300 border-gray-500/30',   glow: '' },
    certified:       { label: 'Certified',       stripe: 'from-green-400 to-green-600', badge: 'bg-green-500/20 text-green-300 border-green-500/30', glow: 'shadow-green-900/20' },
    results_pending: { label: 'Results Pending', stripe: 'from-amber-400 to-amber-600', badge: 'bg-amber-500/20 text-amber-300 border-amber-500/30', glow: 'shadow-amber-900/20' },
    certifying:      { label: 'Certifying',      stripe: 'from-blue-400 to-blue-600',   badge: 'bg-blue-500/20 text-blue-300 border-blue-500/30',   glow: 'shadow-blue-900/20' },
    configured:      { label: 'Configured',      stripe: 'from-purple-400 to-purple-600', badge: 'bg-purple-500/20 text-purple-300 border-purple-500/30', glow: '' },
    archived:        { label: 'Archived',        stripe: 'from-slate-600 to-slate-700', badge: 'bg-slate-500/20 text-slate-400 border-slate-500/30', glow: '' },
};

const TYPE_OPTIONS = [
    { value: 'presidential',  label: 'Presidential' },
    { value: 'parliamentary', label: 'Parliamentary' },
    { value: 'local',         label: 'Local Council' },
    { value: 'referendum',    label: 'By-Election / Referendum' },
];

const STATUS_OPTIONS = ['draft','configured','active','results_pending','certifying','certified','archived'];

export default function Elections({ auth, elections = [], allParties = [], flash }) {
    const [editingElection, setEditingElection]             = useState(null);
    const [showDeactivateConfirm, setShowDeactivateConfirm] = useState(null);
    const [deletingId, setDeletingId]                       = useState(null);

    const { data, setData, put, processing, errors, reset } = useForm({
        name:      '',
        type:      'presidential',
        date:      '',
        status:    'active',
        party_ids: [],
    });

    const openEdit = (election) => {
        setData({
            name:      election.name,
            type:      election.type?.replace('_', '-') || 'presidential',
            date:      election.date || '',
            status:    election.status || 'active',
            party_ids: election.participating_parties?.map(p => p.id) || [],
        });
        setEditingElection(election);
    };

    const closeEdit = () => { reset(); setEditingElection(null); };

    const handleUpdate = (e) => {
        e.preventDefault();
        put(`/admin/elections/${editingElection.id}`, { onSuccess: () => closeEdit() });
    };

    const handleElectionDelete = (id, name) => {
        if (!window.confirm(`Force-delete election "${name}"?\n\n⚠ This PERMANENTLY removes ALL related data.\n\nThis cannot be undone.`)) return;
        setDeletingId(id);
        router.delete(`/admin/elections/${id}/force`, {
            onSuccess: () => setDeletingId(null),
            onError: (errs) => { setDeletingId(null); alert(errs?.error || 'Failed to delete election.'); },
            onFinish: () => setDeletingId(null),
        });
    };

    const handleDeactivate = (election) => {
        router.patch(`/admin/elections/${election.id}/toggle-status`, {}, {
            onSuccess: () => setShowDeactivateConfirm(null),
        });
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="flex flex-wrap justify-between items-start gap-4 mb-6">
                    <div>
                        <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-white">Election Management</h1>
                        <p className="text-gray-400 text-sm mt-1">Create and configure elections, assign participating parties.</p>
                    </div>
                    <Link href="/admin/elections/create"
                          className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        + Create New Election
                    </Link>
                </div>

                {/* Flash messages */}
                {flash?.success && (
                    <div className="mb-6 p-4 bg-teal-500/20 border border-teal-500/50 rounded-lg text-teal-300">✓ {flash.success}</div>
                )}
                {flash?.error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300">⚠ {flash.error}</div>
                )}

                {/* Elections list */}
                <div className="space-y-4">
                    {elections.length > 0 ? elections.map((election) => {
                        const meta    = STATUS_META[election.status] || STATUS_META.draft;
                        const isActive = election.status === 'active';

                        return (
                            <div
                                key={election.id}
                                className={`relative rounded-xl overflow-hidden border transition-all ${
                                    isActive
                                        ? 'border-teal-500/40 shadow-lg shadow-teal-900/25'
                                        : election.status === 'archived'
                                        ? 'border-slate-700/30 opacity-65'
                                        : 'border-slate-700/50'
                                } bg-slate-800/40`}
                            >
                                {/* Status colour strip at top */}
                                <div className={`h-1.5 w-full bg-gradient-to-r ${meta.stripe}`} />

                                <div className="p-6">
                                    <div className="flex flex-wrap justify-between items-start gap-4">

                                        {/* Left: info */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex flex-wrap items-center gap-3 mb-1">
                                                <h3 className="text-xl font-bold text-white">{election.name}</h3>
                                                <span className={`px-3 py-0.5 rounded-full text-xs font-bold border ${meta.badge}`}>
                                                    {meta.label}
                                                </span>
                                                {isActive && (
                                                    <span className="flex items-center gap-1.5 text-xs text-teal-400">
                                                        <span className="w-2 h-2 rounded-full bg-teal-400 animate-pulse" />
                                                        Live
                                                    </span>
                                                )}
                                            </div>

                                            <p className="text-gray-400 capitalize text-sm mt-0.5">
                                                {election.type?.replace(/_/g, ' ')}
                                                {election.date && <> · 📅 {election.date}</>}
                                            </p>

                                            {/* Participating parties */}
                                            <div className="mt-3">
                                                <p className="text-gray-500 text-xs uppercase tracking-wide mb-1">
                                                    Participating Parties
                                                    {election.participating_parties?.length > 0 &&
                                                        <span className="ml-1 normal-case text-gray-600">
                                                            ({election.participating_parties.length})
                                                        </span>
                                                    }
                                                </p>
                                                <PartyBadges parties={election.participating_parties} />
                                            </div>
                                        </div>

                                        {/* Right: actions */}
                                        <div className="flex gap-2 flex-shrink-0 flex-wrap">
                                            <button
                                                onClick={() => openEdit(election)}
                                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold"
                                            >
                                                ✏ Edit
                                            </button>
                                            <button
                                                onClick={() => setShowDeactivateConfirm(election)}
                                                className={`px-4 py-2 rounded-lg text-sm font-semibold ${
                                                    election.status === 'archived'
                                                        ? 'bg-teal-600/80 hover:bg-teal-600 text-white'
                                                        : 'bg-amber-600/80 hover:bg-amber-600 text-white'
                                                }`}
                                            >
                                                {election.status === 'archived' ? '✅ Activate' : '🚫 Archive'}
                                            </button>
                                            <button
                                                onClick={() => handleElectionDelete(election.id, election.name)}
                                                disabled={deletingId === election.id}
                                                className="px-4 py-2 bg-red-700/80 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg text-sm font-semibold"
                                            >
                                                {deletingId === election.id ? 'Deleting…' : '🗑 Delete'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    }) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <div className="text-5xl mb-4">🗳️</div>
                            <p className="text-gray-400 mb-4">No elections configured yet.</p>
                            <Link href="/admin/elections/create"
                                  className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg inline-block">
                                Create First Election
                            </Link>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Edit Modal ─────────────────────────────────────────────────── */}
            {editingElection && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
                    <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col" style={{ maxHeight: '90vh' }}>

                        {/* Status strip inside modal header */}
                        <div className={`h-1 w-full rounded-t-2xl bg-gradient-to-r ${(STATUS_META[data.status] || STATUS_META.draft).stripe}`} />

                        {/* Modal Header */}
                        <div className="flex items-center justify-between px-6 py-5 border-b border-slate-700 flex-shrink-0">
                            <div>
                                <h2 className="text-xl font-bold text-white">Edit Election</h2>
                                <p className="text-gray-400 text-sm mt-0.5">{editingElection.name}</p>
                            </div>
                            <button onClick={closeEdit} className="text-gray-400 hover:text-white text-2xl w-8 h-8 flex items-center justify-center">×</button>
                        </div>

                        {/* Modal Body */}
                        <div className="overflow-y-auto flex-1 px-6 py-5 space-y-5">
                            {/* Name */}
                            <div>
                                <label className="block text-gray-300 mb-1.5 text-sm font-semibold">
                                    Election Name <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:border-teal-500"
                                    required
                                />
                                {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
                            </div>

                            {/* Type + Date */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-gray-300 mb-1.5 text-sm font-semibold">
                                        Election Type <span className="text-red-400">*</span>
                                    </label>
                                    <select
                                        value={data.type}
                                        onChange={(e) => setData('type', e.target.value)}
                                        className="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:border-teal-500"
                                    >
                                        {TYPE_OPTIONS.map(o => (
                                            <option key={o.value} value={o.value}>{o.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-gray-300 mb-1.5 text-sm font-semibold">
                                        Election Date <span className="text-red-400">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={data.date}
                                        onChange={(e) => setData('date', e.target.value)}
                                        className="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:border-teal-500"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Status */}
                            <div>
                                <label className="block text-gray-300 mb-1.5 text-sm font-semibold">Status</label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:border-teal-500"
                                >
                                    {STATUS_OPTIONS.map(s => (
                                        <option key={s} value={s}>
                                            {s.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Participating Parties */}
                            <div>
                                <label className="block text-gray-300 mb-1.5 text-sm font-semibold">
                                    Participating Parties
                                    <span className="ml-2 text-gray-500 font-normal text-xs">
                                        ({data.party_ids.length} selected)
                                    </span>
                                </label>
                                <PartySelector
                                    allParties={allParties}
                                    selectedIds={data.party_ids}
                                    onChange={(ids) => setData('party_ids', ids)}
                                />
                                {errors.party_ids && <p className="text-red-400 text-xs mt-1">{errors.party_ids}</p>}
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="flex gap-3 px-6 py-4 border-t border-slate-700 flex-shrink-0">
                            <button
                                type="button"
                                onClick={handleUpdate}
                                disabled={processing}
                                className="flex-1 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg text-sm"
                            >
                                {processing ? 'Saving…' : '✓ Save Changes'}
                            </button>
                            <button
                                type="button"
                                onClick={closeEdit}
                                className="flex-1 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ── Archive / Activate Confirm ─────────────────────────────────── */}
            {showDeactivateConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
                    <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-md shadow-2xl p-6">
                        <h2 className="text-xl font-bold text-white mb-3">
                            {showDeactivateConfirm.status === 'archived' ? '✅ Activate Election' : '🚫 Archive Election'}
                        </h2>
                        <p className="text-gray-300 mb-6 text-sm leading-relaxed">
                            {showDeactivateConfirm.status === 'archived'
                                ? <>Are you sure you want to <strong className="text-teal-300">activate</strong> <em>{showDeactivateConfirm.name}</em>?</>
                                : <>Are you sure you want to <strong className="text-amber-300">archive</strong> <em>{showDeactivateConfirm.name}</em>? Results will still be visible.</>
                            }
                        </p>
                        <div className="flex gap-3">
                            <button
                                onClick={() => handleDeactivate(showDeactivateConfirm)}
                                className={`flex-1 py-3 font-bold rounded-lg text-white text-sm ${
                                    showDeactivateConfirm.status === 'archived'
                                        ? 'bg-teal-600 hover:bg-teal-700'
                                        : 'bg-amber-600 hover:bg-amber-700'
                                }`}
                            >
                                {showDeactivateConfirm.status === 'archived' ? 'Yes, Activate' : 'Yes, Archive'}
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
