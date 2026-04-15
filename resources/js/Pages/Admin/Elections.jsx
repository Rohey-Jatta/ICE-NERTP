import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

function PartySelector({ allParties = [], selectedIds = [], onChange }) {
    const [expanded, setExpanded] = useState(null);

    const toggle = (partyId) => {
        if (selectedIds.includes(partyId)) {
            onChange(selectedIds.filter(id => id !== partyId));
        } else {
            onChange([...selectedIds, partyId]);
        }
    };

    if (allParties.length === 0) {
        return (
            <div className="text-center py-6 border-2 border-dashed border-slate-700 rounded-xl">
                <p className="text-gray-500 text-sm">No parties registered yet.</p>
                <Link href="/admin/parties/create"
                    className="text-teal-400 hover:text-teal-300 text-sm underline mt-1 inline-block">
                    Register a party first →
                </Link>
            </div>
        );
    }

    return (
        <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
            {allParties.map(party => {
                const isSelected = selectedIds.includes(party.id);
                const isExpanded = expanded === party.id;
                const colorStyle = party.colors_array?.length > 1
                    ? { background: `linear-gradient(135deg, ${party.colors_array.join(', ')})` }
                    : { background: party.colors_array?.[0] || party.color || '#3b82f6' };

                return (
                    <div key={party.id}
                        className={`rounded-xl border transition-colors ${
                            isSelected
                                ? 'border-teal-500/60 bg-teal-900/10'
                                : 'border-slate-700/50 bg-slate-900/30 hover:border-slate-600'
                        }`}>
                        <div className="flex items-center gap-3 p-3">
                            <div className="w-8 h-8 rounded-lg flex-shrink-0 border border-white/10" style={colorStyle} />
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="text-white font-semibold text-sm truncate">{party.name}</span>
                                    <span className="text-xs text-gray-500 font-mono flex-shrink-0">{party.abbreviation}</span>
                                </div>
                                {party.leader_name && (
                                    <div className="text-gray-500 text-xs truncate">Leader: {party.leader_name}</div>
                                )}
                            </div>
                            {party.candidate_count > 0 && (
                                <button type="button"
                                    onClick={() => setExpanded(isExpanded ? null : party.id)}
                                    className="text-xs text-gray-400 hover:text-teal-300 flex items-center gap-1 flex-shrink-0 px-2 py-1 rounded-lg hover:bg-slate-700/40 transition-colors">
                                    <span>👤 {party.candidate_count}</span>
                                    <span>{isExpanded ? '▲' : '▼'}</span>
                                </button>
                            )}
                            <button type="button" onClick={() => toggle(party.id)}
                                className={`flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors ${
                                    isSelected
                                        ? 'bg-teal-600 hover:bg-red-600/80 text-white'
                                        : 'bg-slate-700 hover:bg-teal-600/70 text-gray-300 hover:text-white'
                                }`}>
                                {isSelected ? '✓ Added' : '+ Add'}
                            </button>
                        </div>
                        {isExpanded && party.candidates?.length > 0 && (
                            <div className="border-t border-slate-700/50 px-3 pb-3 pt-2">
                                <div className="text-xs text-gray-500 mb-2 uppercase tracking-wide">Candidates</div>
                                <div className="space-y-1">
                                    {party.candidates.map(c => (
                                        <div key={c.id} className="flex items-center gap-2 text-sm text-gray-300 py-1">
                                            <span className="text-gray-600 font-mono text-xs w-6 text-right flex-shrink-0">
                                                {c.ballot_number || '—'}
                                            </span>
                                            <span className="w-px h-4 bg-slate-700 flex-shrink-0" />
                                            <span className="truncate">{c.name}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function ElectionCard({ election, allParties, onToggleStatus, onDelete }) {
    const [editingParties, setEditingParties] = useState(false);
    const [selectedIds, setSelectedIds] = useState(
        election.participating_parties?.map(p => p.id) ?? []
    );
    const [saving, setSaving] = useState(false);

    const STATUS_COLORS = {
        active:          'bg-green-500/20 text-green-300 border-green-500/40',
        draft:           'bg-slate-500/20 text-slate-300 border-slate-500/40',
        configured:      'bg-blue-500/20 text-blue-300 border-blue-500/40',
        results_pending: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40',
        certifying:      'bg-orange-500/20 text-orange-300 border-orange-500/40',
        certified:       'bg-teal-500/20 text-teal-300 border-teal-500/40',
        archived:        'bg-gray-500/20 text-gray-400 border-gray-500/40',
    };

    const saveParties = async () => {
        setSaving(true);
        await new Promise(resolve => {
            router.put(`/admin/elections/${election.id}`, {
                name:      election.name,
                type:      election.type,
                date:      election.date,
                status:    election.status,
                party_ids: selectedIds,
            }, {
                preserveScroll: true,
                onFinish: resolve,
            });
        });
        setSaving(false);
        setEditingParties(false);
    };

    return (
        <div className="bg-slate-800/40 rounded-xl border border-slate-700/50 overflow-hidden">
            <div className="p-5 flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-3 flex-wrap">
                        <h3 className="text-white font-bold text-lg truncate">{election.name}</h3>
                        <span className={`text-xs px-2 py-0.5 rounded-full border font-semibold capitalize ${
                            STATUS_COLORS[election.status] ?? STATUS_COLORS.draft
                        }`}>
                            {election.status?.replace('_', ' ')}
                        </span>
                    </div>
                    <div className="flex items-center gap-4 mt-1 text-sm text-gray-400">
                        <span className="capitalize">{election.type?.replace('_', ' ')}</span>
                        {election.date && <span>📅 {election.date}</span>}
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                    <button
                        onClick={() => onToggleStatus(election.id)}
                        className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors ${
                            election.status === 'archived'
                                ? 'border-green-600/40 text-green-400 hover:bg-green-600/20'
                                : 'border-slate-600/40 text-gray-400 hover:bg-slate-700/40'
                        }`}>
                        {election.status === 'archived' ? 'Activate' : 'Archive'}
                    </button>
                    <button
                        onClick={() => onDelete(election.id, election.name)}
                        className="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-600/40 text-red-400 hover:bg-red-600/20 transition-colors">
                        Delete All
                    </button>
                </div>
            </div>

            <div className="border-t border-slate-700/50 p-5">
                <div className="flex items-center justify-between mb-3">
                    <div className="text-sm font-semibold text-gray-300">
                        Participating Parties
                        <span className="text-gray-500 font-normal ml-2">
                            ({election.participating_parties?.length ?? 0})
                        </span>
                    </div>
                    <button type="button"
                        onClick={() => setEditingParties(e => !e)}
                        className="text-xs text-teal-400 hover:text-teal-300 underline transition-colors">
                        {editingParties ? 'Cancel' : 'Edit Parties'}
                    </button>
                </div>

                {editingParties ? (
                    <>
                        <PartySelector
                            allParties={allParties}
                            selectedIds={selectedIds}
                            onChange={setSelectedIds}
                        />
                        <div className="flex gap-3 mt-3">
                            <button type="button" onClick={saveParties} disabled={saving}
                                className="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm rounded-lg font-semibold transition-colors">
                                {saving ? 'Saving…' : 'Save Parties'}
                            </button>
                            <button type="button"
                                onClick={() => {
                                    setSelectedIds(election.participating_parties?.map(p => p.id) ?? []);
                                    setEditingParties(false);
                                }}
                                className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-gray-300 text-sm rounded-lg transition-colors">
                                Discard
                            </button>
                        </div>
                    </>
                ) : (
                    <>
                        {election.participating_parties?.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {election.participating_parties.map(p => {
                                    const full = allParties.find(ap => ap.id === p.id);
                                    const colorStyle = full?.colors_array?.length > 1
                                        ? { background: `linear-gradient(135deg, ${full.colors_array.join(', ')})` }
                                        : { background: full?.colors_array?.[0] || p.color || '#3b82f6' };
                                    return (
                                        <div key={p.id}
                                            className="flex items-center gap-2 px-3 py-1.5 bg-slate-900/50 rounded-lg border border-slate-700/50">
                                            <div className="w-4 h-4 rounded-sm flex-shrink-0" style={colorStyle} />
                                            <span className="text-white text-sm font-medium">{p.name}</span>
                                            <span className="text-gray-500 text-xs font-mono">{p.abbreviation}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-gray-500 text-sm italic">
                                No parties assigned yet.{' '}
                                <button type="button" onClick={() => setEditingParties(true)}
                                    className="text-teal-400 hover:text-teal-300 underline">
                                    Add parties →
                                </button>
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

export default function Elections({ auth, elections = [], allParties = [], flash }) {
    const handleToggleStatus = (electionId) => {
        if (!confirm('Toggle the status of this election?')) return;
        router.patch(`/admin/elections/${electionId}/toggle-status`, {}, { preserveScroll: true });
    };

    const handleDelete = (electionId, name) => {
        if (!window.confirm(
            `DELETE election "${name}" and ALL related data?\n\n` +
            `This will permanently delete:\n` +
            `• All polling stations\n` +
            `• All parties & candidates\n` +
            `• All submitted results & certifications\n` +
            `• Administrative hierarchy\n` +
            `• All audit logs for this election\n\n` +
            `This CANNOT be undone.`
        )) return;
        router.delete(`/admin/elections/${electionId}`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="mb-6">
                    <Link href="/admin/dashboard"
                        className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Dashboard
                    </Link>
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-white">Election Management</h1>
                            <p className="text-gray-400 text-sm mt-1">
                                {elections.length} election{elections.length !== 1 ? 's' : ''} registered
                            </p>
                        </div>
                        <Link href="/admin/elections/create"
                            className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg transition-colors">
                            + Create Election
                        </Link>
                    </div>
                </div>

                {flash?.success && (
                    <div className="mb-6 p-4 bg-green-500/20 border border-green-500/50 rounded-xl text-green-300">
                        ✓ {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-xl text-red-300">
                        ⚠ {flash.error}
                    </div>
                )}

                {elections.length === 0 ? (
                    <div className="text-center py-20 bg-slate-800/40 rounded-xl border border-slate-700/50">
                        <div className="text-5xl mb-4">🗳️</div>
                        <p className="text-gray-400 mb-4">No elections created yet.</p>
                        <Link href="/admin/elections/create"
                            className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg inline-block">
                            Create First Election
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {elections.map(election => (
                            <ElectionCard
                                key={election.id}
                                election={election}
                                allParties={allParties}
                                onToggleStatus={handleToggleStatus}
                                onDelete={handleDelete}
                            />
                        ))}
                    </div>
                )}

                {allParties.length > 0 && (
                    <div className="mt-8 bg-slate-800/40 rounded-xl border border-slate-700/50 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-white font-bold text-lg">
                                Registered Parties
                                <span className="text-gray-500 font-normal text-sm ml-2">
                                    ({allParties.length})
                                </span>
                            </h2>
                            <Link href="/admin/parties"
                                className="text-teal-400 hover:text-teal-300 text-sm underline transition-colors">
                                Manage all parties →
                            </Link>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {allParties.map(party => {
                                const colorStyle = party.colors_array?.length > 1
                                    ? { background: `linear-gradient(135deg, ${party.colors_array.join(', ')})` }
                                    : { background: party.colors_array?.[0] || party.color || '#3b82f6' };
                                return (
                                    <div key={party.id}
                                        className="flex items-center gap-3 p-3 bg-slate-900/40 rounded-xl border border-slate-700/30">
                                        <div className="w-10 h-10 rounded-lg flex-shrink-0 border border-white/10"
                                            style={colorStyle} />
                                        <div className="flex-1 min-w-0">
                                            <div className="text-white font-semibold text-sm truncate">{party.name}</div>
                                            <div className="text-gray-500 text-xs">
                                                {party.abbreviation}
                                                {party.candidate_count > 0 &&
                                                    <span className="ml-2">· {party.candidate_count} candidate{party.candidate_count !== 1 ? 's' : ''}</span>}
                                            </div>
                                        </div>
                                        <Link href={`/admin/parties/${party.id}/edit`}
                                            className="text-xs text-gray-400 hover:text-teal-300 transition-colors flex-shrink-0">
                                            Edit
                                        </Link>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
