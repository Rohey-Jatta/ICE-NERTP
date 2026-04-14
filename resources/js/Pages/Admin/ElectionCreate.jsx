import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

// ── Re-use the same PartySelector from Elections.jsx ─────────────────────────
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

export default function ElectionCreate({ auth, allParties = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name:      '',
        type:      'parliamentary',
        date:      '',
        party_ids: [],
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/elections');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-3xl">
                <div className="mb-6">
                    <Link href="/admin/elections"
                        className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Elections
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Create Election</h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Details */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-white font-bold text-lg mb-5 pb-3 border-b border-slate-700">
                            Election Details
                        </h2>
                        <div className="space-y-5">
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Election Name <span className="text-red-400">*</span>
                                </label>
                                <input type="text" value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white focus:outline-none focus:border-teal-500"
                                    placeholder="e.g., 2025 National Assembly Elections" required />
                                {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-gray-300 mb-2 font-semibold">
                                        Election Type <span className="text-red-400">*</span>
                                    </label>
                                    <select value={data.type}
                                        onChange={e => setData('type', e.target.value)}
                                        className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white focus:outline-none focus:border-teal-500">
                                        <option value="presidential">Presidential</option>
                                        <option value="parliamentary">Parliamentary</option>
                                        <option value="local">Local Government</option>
                                        <option value="referendum">By-Election / Referendum</option>
                                    </select>
                                    {errors.type && <p className="text-red-400 text-sm mt-1">{errors.type}</p>}
                                </div>
                                <div>
                                    <label className="block text-gray-300 mb-2 font-semibold">
                                        Election Date <span className="text-red-400">*</span>
                                    </label>
                                    <input type="date" value={data.date}
                                        onChange={e => setData('date', e.target.value)}
                                        className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white focus:outline-none focus:border-teal-500"
                                        required />
                                    {errors.date && <p className="text-red-400 text-sm mt-1">{errors.date}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Participating Parties */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <div className="flex items-center justify-between mb-1">
                            <h2 className="text-white font-bold text-lg">Participating Parties</h2>
                            <Link href="/admin/parties/create"
                                className="text-xs text-teal-400 hover:text-teal-300 underline transition-colors">
                                + Register new party
                            </Link>
                        </div>
                        <p className="text-gray-500 text-sm mb-4">
                            Select the parties contesting this election. Click the candidate count badge to preview a party's candidates.
                        </p>

                        {data.party_ids.length > 0 && (
                            <div className="mb-3 text-sm text-teal-300 font-medium">
                                ✓ {data.party_ids.length} part{data.party_ids.length !== 1 ? 'ies' : 'y'} selected
                            </div>
                        )}

                        <PartySelector
                            allParties={allParties}
                            selectedIds={data.party_ids}
                            onChange={(ids) => setData('party_ids', ids)}
                        />

                        {errors.party_ids && (
                            <p className="text-red-400 text-sm mt-2">{errors.party_ids}</p>
                        )}
                    </div>

                    {/* Submit */}
                    <div className="flex gap-4">
                        <button type="submit" disabled={processing}
                            className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg transition-colors">
                            {processing ? 'Creating…' : '✓ Create Election'}
                        </button>
                        <Link href="/admin/elections"
                            className="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-center transition-colors">
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
