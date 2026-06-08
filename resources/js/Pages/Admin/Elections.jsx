import AppLayout from '@/Layouts/AppLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button, PageHeader, Panel } from '@/Components/AdminUI';
import SearchableSelect from '@/Components/SearchableSelect';

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
                    <Link href="/admin/parties/create" className="underline text-iec-pink-600">Register a party first</Link>.
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
                                : 'bg-slate-50 border-slate-200 hover:bg-white'
                        }`}
                    >
                        <input
                            type="checkbox"
                            checked={selected}
                            onChange={() => toggle(party.id)}
                            className="h-4 w-4 text-iec-pink-600 bg-white border-slate-200 rounded"
                        />
                        <div className="flex gap-0.5 flex-shrink-0">
                            {colors.length > 0
                                ? colors.map((c, i) => (
                                    <span key={i} className="w-4 h-4 rounded-sm border border-slate-200" style={{ backgroundColor: c }} />
                                ))
                                : <span className="w-4 h-4 rounded-sm bg-gray-500 border border-slate-200" />
                            }
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="text-iec-navy text-sm font-medium truncate">{party.name}</div>
                            <div className="text-slate-500 text-xs">{party.abbreviation}</div>
                        </div>
                        {selected && <span className="text-iec-pink-600 text-xs font-semibold flex-shrink-0">✓ Selected</span>}
                    </label>
                );
            })}
        </div>
    );
}

/* ── Party badge strip on card ─────────────────────────────────────────── */
function PartyBadges({ parties }) {
    if (!parties || parties.length === 0) {
        return <span className="text-slate-600 text-xs italic">No parties assigned</span>;
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
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border border-slate-200"
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
    active:          { label: 'Active', tone: 'teal' },
    draft:           { label: 'Draft', tone: 'slate' },
    certified:       { label: 'Certified', tone: 'teal' },
    results_pending: { label: 'Results Pending', tone: 'amber' },
    certifying:      { label: 'Certifying', tone: 'blue' },
    configured:      { label: 'Configured', tone: 'blue' },
    archived:        { label: 'Archived', tone: 'slate' },
};

const TYPE_OPTIONS = [
    { value: 'presidential',  label: 'Presidential' },
    { value: 'parliamentary', label: 'National Assembly' },
    { value: 'local',         label: 'Regional / Local Government' },
    { value: 'referendum',    label: 'Constituency / By-Election' },
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
        allow_provisional_public_display: true,
        party_ids: [],
    });

    const openEdit = (election) => {
        setData({
            name:      election.name,
            type:      election.type?.replace('_', '-') || 'presidential',
            date:      election.date || '',
            status:    election.status || 'active',
            allow_provisional_public_display: !!election.allow_provisional_public_display,
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
            <div className="ws-container">
                <PageHeader
                    title="Election Management"
                    description="Create and configure elections, assign participating parties, and control public display."
                    actions={<Button href="/admin/elections/create">Create New Election</Button>}
                />

                {/* Flash messages */}
                {flash?.success && (
                    <div className="mb-6 p-4 bg-iec-pink-500/20 border border-teal-500/50 rounded-lg text-iec-pink-600">✓ {flash.success}</div>
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
                            <Panel
                                key={election.id}
                                className={`p-5 transition-colors ${election.status === 'archived' ? 'opacity-70' : ''}`}
                            >
                                    <div className="grid gap-5 xl:grid-cols-[1fr_auto] xl:items-start">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="text-lg font-bold text-slate-900">{election.name}</h3>
                                                <Badge tone={meta.tone}>{meta.label}</Badge>
                                                {isActive && (
                                                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700">
                                                        <span className="w-2 h-2 rounded-full bg-iec-pink-500 animate-pulse" />
                                                        Live
                                                    </span>
                                                )}
                                                {election.allow_provisional_public_display && (
                                                    <Badge tone="pink">Public homepage</Badge>
                                                )}
                                            </div>

                                            <p className="mt-2 text-sm capitalize text-slate-500">
                                                {election.type?.replace(/_/g, ' ')}
                                                {election.date && <> · {election.date}</>}
                                            </p>

                                            <div className="mt-4">
                                                <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Participating Parties
                                                    {election.participating_parties?.length > 0 &&
                                                        <span className="ml-1 normal-case text-slate-600">
                                                            ({election.participating_parties.length})
                                                        </span>
                                                    }
                                                </p>
                                                <PartyBadges parties={election.participating_parties} />
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap justify-start gap-2 xl:justify-end">
                                            <Button onClick={() => openEdit(election)} variant="secondary">Edit</Button>
                                            <Button
                                                onClick={() => setShowDeactivateConfirm(election)}
                                                variant={election.status === 'archived' ? 'primary' : 'warning'}
                                            >
                                                {election.status === 'archived' ? 'Activate' : 'Archive'}
                                            </Button>
                                            <Button
                                                onClick={() => handleElectionDelete(election.id, election.name)}
                                                disabled={deletingId === election.id}
                                                variant="danger"
                                            >
                                                {deletingId === election.id ? 'Deleting...' : 'Delete'}
                                            </Button>
                                        </div>
                                    </div>
                            </Panel>
                        );
                    }) : (
                        <Panel className="p-12 text-center">
                            <p className="mb-4 text-slate-400">No elections configured yet.</p>
                            <Button href="/admin/elections/create">Create First Election</Button>
                        </Panel>
                    )}
                </div>
            </div>

            {/* ── Edit Modal ─────────────────────────────────────────────────── */}
            {editingElection && (
                <div className="ws-modal-backdrop">
                    <div className="ws-modal" style={{ maxWidth: '42rem' }}>
                        <div className="ws-modal-strip" />
                        <div className="ws-modal-header">
                            <div>
                                <h2 className="ws-modal-title">Edit Election</h2>
                                <p className="ws-modal-subtitle">{editingElection.name}</p>
                            </div>
                            <button onClick={closeEdit} className="ws-modal-close" aria-label="Close">×</button>
                        </div>

                        <div className="ws-modal-body space-y-5">
                            <div>
                                <label className="block text-slate-600 mb-1.5 text-sm font-semibold">
                                    Election Name <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy text-sm focus:outline-none focus:border-iec-pink-500"
                                    required
                                />
                                {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
                            </div>

                            {/* Type + Date */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-600 mb-1.5 text-sm font-semibold">
                                        Election Type <span className="text-red-400">*</span>
                                    </label>
                                    <SearchableSelect
                                        value={data.type}
                                        onChange={(val) => setData('type', val)}
                                        options={TYPE_OPTIONS}
                                        placeholder="Select election type"
                                        className="w-full text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 mb-1.5 text-sm font-semibold">
                                        Election Date <span className="text-red-400">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={data.date}
                                        onChange={(e) => setData('date', e.target.value)}
                                        className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy text-sm focus:outline-none focus:border-iec-pink-500"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Status */}
                            <div>
                                <label className="block text-slate-600 mb-1.5 text-sm font-semibold">Status</label>
                                <SearchableSelect
                                    value={data.status}
                                    onChange={(val) => setData('status', val)}
                                    options={STATUS_OPTIONS.map(s => ({
                                        value: s,
                                        label: s.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
                                    }))}
                                    placeholder="Select status"
                                    className="w-full text-sm"
                                />
                            </div>

                            {/* Public Display */}
                            <div className="rounded-xl border border-slate-200 bg-white p-4">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        checked={data.allow_provisional_public_display}
                                        onChange={(e) => setData('allow_provisional_public_display', e.target.checked)}
                                        className="mt-1 h-5 w-5 rounded border-slate-200 bg-white text-iec-pink-600"
                                    />
                                    <span>
                                        <span className="block text-sm font-bold text-iec-navy">Show on public homepage</span>
                                        <span className="mt-1 block text-xs leading-5 text-slate-500">
                                            Controls whether this election is eligible for public results, map, and station pages.
                                        </span>
                                    </span>
                                </label>
                            </div>

                            {/* Participating Parties */}
                            <div>
                                <label className="block text-slate-600 mb-1.5 text-sm font-semibold">
                                    Participating Parties
                                    <span className="ml-2 text-slate-500 font-normal text-xs">
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

                        <div className="ws-modal-footer">
                            <button
                                type="button"
                                onClick={handleUpdate}
                                disabled={processing}
                                className="ws-btn ws-btn-primary flex-1"
                            >
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                            <button
                                type="button"
                                onClick={closeEdit}
                                className="ws-btn ws-btn-secondary flex-1"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ── Archive / Activate Confirm ─────────────────────────────────── */}
            {showDeactivateConfirm && (
                <div className="ws-modal-backdrop">
                    <div className="ws-modal p-6" style={{ maxWidth: '28rem' }}>
                        <h2 className="ws-modal-title mb-3">
                            {showDeactivateConfirm.status === 'archived' ? 'Activate Election' : 'Archive Election'}
                        </h2>
                        <p className="text-slate-600 mb-6 text-sm leading-relaxed">
                            {showDeactivateConfirm.status === 'archived'
                                ? <>Are you sure you want to <strong>activate</strong> <em>{showDeactivateConfirm.name}</em>?</>
                                : <>Are you sure you want to <strong>archive</strong> <em>{showDeactivateConfirm.name}</em>? Results will still be visible.</>
                            }
                        </p>
                        <div className="flex gap-3">
                            <button
                                onClick={() => handleDeactivate(showDeactivateConfirm)}
                                className="ws-btn ws-btn-primary flex-1"
                            >
                                {showDeactivateConfirm.status === 'archived' ? 'Yes, Activate' : 'Yes, Archive'}
                            </button>
                            <button
                                onClick={() => setShowDeactivateConfirm(null)}
                                className="ws-btn ws-btn-secondary flex-1"
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
