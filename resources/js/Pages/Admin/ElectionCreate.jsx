import AppLayout from '@/Layouts/AppLayout';
import { useForm, router, Link } from '@inertiajs/react';

function PartySelector({ allParties, selectedIds, onChange }) {
    const toggle = (id) => {
        if (selectedIds.includes(id)) {
            onChange(selectedIds.filter(p => p !== id));
        } else {
            onChange([...selectedIds, id]);
        }
    };

    const parseColors = (colorStr) => {
        if (!colorStr) return [];
        return colorStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{6}$/.test(c));
    };

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
                const colors = parseColors(party.color);

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
                            {colors.length > 0 ? colors.map((c, i) => (
                                <span key={i} className="w-4 h-4 rounded-sm border border-white/20" style={{ backgroundColor: c }} />
                            )) : (
                                <span className="w-4 h-4 rounded-sm bg-gray-500 border border-white/20" />
                            )}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="text-white text-sm font-medium truncate">{party.name}</div>
                            <div className="text-gray-400 text-xs">{party.abbreviation}</div>
                        </div>
                        {selected && <span className="text-teal-400 text-xs font-semibold flex-shrink-0">✓</span>}
                    </label>
                );
            })}
        </div>
    );
}

export default function ElectionCreate({ auth, allParties = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name:      '',
        type:      'presidential',
        date:      '',
        party_ids: [],
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/elections');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <Link href="/admin/elections" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Elections
                        </Link>
                        <h1 className="text-3xl font-bold text-white">Create New Election</h1>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Name */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Election Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="Election Name/Type"
                                required
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        {/* Type */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Election Type</label>
                            <select
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            >
                                <option value="presidential">Presidential</option>
                                <option value="parliamentary">Parliamentary</option>
                                <option value="local">Local Council</option>
                                <option value="referendum">Referendum</option>
                            </select>
                            {errors.type && <p className="text-red-400 text-sm mt-1">{errors.type}</p>}
                        </div>

                        {/* Date */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Election Date</label>
                            <input
                                type="date"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                required
                            />
                            {errors.date && <p className="text-red-400 text-sm mt-1">{errors.date}</p>}
                        </div>

                        {/* Participating Parties */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Participating Parties
                                <span className="ml-2 text-gray-500 font-normal text-xs">
                                    ({data.party_ids.length} selected)
                                </span>
                            </label>
                            <p className="text-gray-500 text-xs mb-3">
                                Select all political parties taking part in this election.
                            </p>
                            <PartySelector
                                allParties={allParties}
                                selectedIds={data.party_ids}
                                onChange={(ids) => setData('party_ids', ids)}
                            />
                            {errors.party_ids && <p className="text-red-400 text-sm mt-1">{errors.party_ids}</p>}
                        </div>

                        {/* Actions */}
                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Creating...' : 'Create Election'}
                            </button>
                            <button
                                type="button"
                                onClick={() => router.visit('/admin/elections')}
                                className="flex-1 px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
