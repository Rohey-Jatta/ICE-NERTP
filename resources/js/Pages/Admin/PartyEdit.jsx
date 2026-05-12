import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

// ── Candidate Management Section ─────────────────────────────────────────────
function CandidatesSection({ partyId, activeElectionId, initialCandidates = [] }) {
    const [candidates, setCandidates]     = useState(initialCandidates);
    const [showForm, setShowForm]         = useState(false);
    const [form, setForm]                 = useState({ name: '', ballot_number: '' });
    const [photo, setPhoto]               = useState(null);
    const [photoPreview, setPhotoPreview] = useState(null);
    const [saving, setSaving]             = useState(false);
    const [deleting, setDeleting]         = useState(null);
    const [error, setError]               = useState('');

    const getCsrf = () =>
        document.head.querySelector('meta[name="csrf-token"]')?.content || '';

    const handleAddCandidate = async (e) => {
        e.preventDefault();
        if (!form.name.trim() || !activeElectionId) return;
        setSaving(true);
        setError('');

        const fd = new FormData();
        fd.append('name', form.name.trim());
        fd.append('election_id', activeElectionId);
        if (form.ballot_number.trim()) fd.append('ballot_number', form.ballot_number.trim());
        if (photo) fd.append('photo', photo);

        try {
            const res  = await fetch(`/admin/parties/${partyId}/candidates`, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': getCsrf() },
                body:    fd,
            });
            const data = await res.json();

            if (res.ok && data.candidate) {
                setCandidates(prev => [...prev, data.candidate]);
                setForm({ name: '', ballot_number: '' });
                setPhoto(null);
                setPhotoPreview(null);
                setShowForm(false);
            } else {
                setError(data.message || 'Failed to add candidate.');
            }
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (candidateId) => {
        if (!confirm('Remove this candidate from the election?')) return;
        setDeleting(candidateId);
        try {
            const res = await fetch(`/admin/candidates/${candidateId}`, {
                method:  'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json' },
            });
            if (res.ok) {
                setCandidates(prev => prev.filter(c => c.id !== candidateId));
            }
        } catch { /* ignore */ }
        finally { setDeleting(null); }
    };

    // Group candidates by election
    const byElection = candidates.reduce((acc, c) => {
        const key = c.election_name || 'Unknown Election';
        if (!acc[key]) acc[key] = [];
        acc[key].push(c);
        return acc;
    }, {});

    return (
        <div className="bg-white rounded-xl border border-slate-200 p-6">
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h3 className="text-iec-navy font-bold text-lg">
                        Candidates
                        <span className="text-slate-500 font-normal text-sm ml-2">({candidates.length} total)</span>
                    </h3>
                    <p className="text-slate-500 text-xs mt-0.5">
                        {activeElectionId
                            ? 'New candidates will be linked to the active election.'
                            : '⚠ No active election — activate an election to add candidates.'}
                    </p>
                </div>
                {activeElectionId && (
                    <button type="button"
                        onClick={() => { setShowForm(f => !f); setError(''); }}
                        className="px-4 py-2 bg-iec-pink-600 hover:bg-iec-pink-700 text-white rounded-lg text-sm font-semibold transition-colors">
                        {showForm ? '✕ Cancel' : '+ Add Candidate'}
                    </button>
                )}
            </div>

            {/* ── Add Candidate Form ──────────────────────────────────────── */}
            {showForm && activeElectionId && (
                <form onSubmit={handleAddCandidate}
                    className="mb-5 p-4 bg-white rounded-xl border border-teal-600/30">
                    <div className="text-xs text-iec-pink-600 mb-3 font-semibold">
                        ✦ Adding candidate for: Active Election
                    </div>
                    {error && (
                        <div className="mb-3 p-2 bg-red-500/20 text-red-300 text-sm rounded-lg">{error}</div>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label className="block text-slate-600 text-sm font-semibold mb-1">
                                Candidate Name <span className="text-red-400">*</span>
                            </label>
                            <input type="text" required value={form.name}
                                onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                                className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-iec-navy text-sm focus:outline-none focus:border-iec-pink-500"
                                placeholder="Full name" />
                        </div>
                        <div>
                            <label className="block text-slate-600 text-sm font-semibold mb-1">
                                Ballot Number <span className="text-slate-500 font-normal">(optional)</span>
                            </label>
                            <input type="text" value={form.ballot_number}
                                onChange={e => setForm(f => ({ ...f, ballot_number: e.target.value }))}
                                className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-iec-navy text-sm focus:outline-none focus:border-iec-pink-500"
                                placeholder="e.g., 1, 2, A" />
                        </div>
                    </div>
                    <div className="mb-4">
                        <label className="block text-slate-600 text-sm font-semibold mb-1">
                            Photo <span className="text-slate-500 font-normal">(optional)</span>
                        </label>
                        <div className="flex items-center gap-3">
                            {photoPreview && (
                                <img src={photoPreview} alt="Preview"
                                    className="w-12 h-12 rounded-full object-cover border-2 border-teal-500 flex-shrink-0" />
                            )}
                            <label className="px-3 py-2 bg-white hover:bg-slate-100 text-iec-navy rounded-lg cursor-pointer text-sm transition-colors">
                                Choose Photo
                                <input type="file" accept="image/*" className="hidden"
                                    onChange={e => {
                                        const file = e.target.files[0];
                                        if (!file) return;
                                        setPhoto(file);
                                        const reader = new FileReader();
                                        reader.onloadend = () => setPhotoPreview(reader.result);
                                        reader.readAsDataURL(file);
                                    }} />
                            </label>
                            {photoPreview && (
                                <button type="button" onClick={() => { setPhoto(null); setPhotoPreview(null); }}
                                    className="text-slate-500 hover:text-red-400 text-sm">Remove</button>
                            )}
                        </div>
                    </div>
                    <button type="submit" disabled={saving || !form.name.trim()}
                        className="px-5 py-2 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors">
                        {saving ? 'Adding...' : 'Add Candidate'}
                    </button>
                </form>
            )}

            {/* ── Candidates List grouped by election ─────────────────────── */}
            {candidates.length === 0 ? (
                <div className="text-center py-8 border-2 border-dashed border-slate-200 rounded-xl">
                    <div className="text-3xl mb-2">🗳️</div>
                    <p className="text-slate-500 text-sm">
                        {activeElectionId
                            ? 'No candidates yet. Click "+ Add Candidate" to add one.'
                            : 'No candidates registered for this party.'}
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    {Object.entries(byElection).map(([electionName, electionCandidates]) => (
                        <div key={electionName}>
                            <div className="text-xs text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-2">
                                <span className="w-4 h-px bg-slate-100 block" />
                                {electionName}
                                <span className="w-full h-px bg-slate-100 block" />
                            </div>
                            <div className="space-y-2">
                                {electionCandidates.map(c => (
                                    <div key={c.id}
                                        className="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                        {c.photo_url ? (
                                            <img src={c.photo_url} alt={c.name}
                                                className="w-10 h-10 rounded-full object-cover flex-shrink-0 border border-slate-200" />
                                        ) : (
                                            <div className="w-10 h-10 rounded-full bg-white flex items-center justify-center text-iec-navy font-bold text-sm flex-shrink-0 border border-slate-200">
                                                {c.name?.charAt(0)?.toUpperCase() ?? '?'}
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <div className="text-iec-navy font-medium text-sm">{c.name}</div>
                                            {c.ballot_number && (
                                                <div className="text-slate-500 text-xs">Ballot #{c.ballot_number}</div>
                                            )}
                                        </div>
                                        <button type="button" onClick={() => handleDelete(c.id)}
                                            disabled={deleting === c.id}
                                            className="px-3 py-1 text-xs font-semibold bg-red-600/20 hover:bg-red-600/40 text-red-300 rounded border border-red-600/30 disabled:opacity-50 transition-colors">
                                            {deleting === c.id ? '...' : 'Remove'}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Multi-color picker ────────────────────────────────────────────────────────
const PRESET_COLORS = [
    '#ef4444','#f97316','#eab308','#22c55e','#10b981',
    '#14b8a6','#06b6d4','#3b82f6','#6366f1','#8b5cf6',
    '#ec4899','#f43f5e','#ffffff','#000000','#6b7280',
    '#92400e','#064e3b','#1e3a8a','#4a044e','#7f1d1d',
];

function MultiColorPicker({ colors, onChange, max = 3 }) {
    const [showPicker, setShowPicker] = useState(false);
    const [activeSlot, setActiveSlot] = useState(0);
    const [customInput, setCustomInput] = useState('');

    const slots = Array.from({ length: max }, (_, i) => colors[i] || null);

    const setSlotColor = (slotIndex, hex) => {
        const next = [...colors];
        next[slotIndex] = hex;
        onChange(next.filter(Boolean));
    };

    const removeSlot = (slotIndex) => {
        onChange(colors.filter((_, i) => i !== slotIndex));
    };

    const openSlot = (i) => {
        setActiveSlot(i);
        setCustomInput(colors[i] || '');
        setShowPicker(true);
    };

    const applyCustom = () => {
        const hex = customInput.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
            setSlotColor(activeSlot, hex);
            setShowPicker(false);
        }
    };

    const gradientStyle = () => {
        if (colors.length === 0) return { background: '#334155' };
        if (colors.length === 1) return { background: colors[0] };
        return { background: `linear-gradient(135deg, ${colors.join(', ')})` };
    };

    return (
        <div>
            <div className="flex items-center gap-3 mb-3">
                <div className="w-16 h-10 rounded-lg border border-slate-200 flex-shrink-0" style={gradientStyle()} />
                <div className="text-slate-600 text-sm">
                    {colors.length === 0 ? 'No colors selected' : colors.join(' + ')}
                </div>
            </div>
            <div className="flex gap-3 flex-wrap mb-3">
                {slots.map((color, i) => (
                    <div key={i} className="relative">
                        {color ? (
                            <div className="flex items-center gap-1">
                                <button type="button" onClick={() => openSlot(i)}
                                    className="w-10 h-10 rounded-lg border-2 border-slate-300 hover:border-slate-300 transition-colors"
                                    style={{ background: color }} title={`Color ${i + 1}: ${color}`} />
                                <button type="button" onClick={() => removeSlot(i)}
                                    className="text-slate-500 hover:text-red-400 text-lg leading-none">×</button>
                            </div>
                        ) : (
                            colors.length <= i && (
                                <button type="button" onClick={() => openSlot(i)}
                                    className="w-10 h-10 rounded-lg border-2 border-dashed border-slate-500 hover:border-teal-400 text-slate-500 hover:text-iec-pink-600 transition-colors flex items-center justify-center text-xl">
                                    +
                                </button>
                            )
                        )}
                    </div>
                ))}
            </div>
            <p className="text-slate-500 text-xs mb-3">Select up to {max} colors.</p>
            {showPicker && (
                <div className="bg-white border border-slate-200 rounded-xl p-4 mt-2">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-iec-navy text-sm font-semibold">Picking Color {activeSlot + 1}</span>
                        <button type="button" onClick={() => setShowPicker(false)}
                            className="text-slate-500 hover:text-iec-navy text-lg">×</button>
                    </div>
                    <div className="flex items-center gap-3 mb-4">
                        <input type="color"
                            value={customInput.match(/^#[0-9a-fA-F]{6}$/) ? customInput : '#3b82f6'}
                            onChange={(e) => setCustomInput(e.target.value)}
                            className="w-12 h-10 rounded-lg cursor-pointer border-0 bg-transparent" />
                        <input type="text" value={customInput}
                            onChange={(e) => setCustomInput(e.target.value)}
                            placeholder="#rrggbb" maxLength={7}
                            className="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono text-sm" />
                        <button type="button" onClick={applyCustom}
                            className="px-4 py-2 bg-iec-pink-600 hover:bg-iec-pink-700 text-white text-sm rounded-lg font-semibold">Apply</button>
                    </div>
                    <div className="grid grid-cols-10 gap-1.5">
                        {PRESET_COLORS.map((c) => (
                            <button key={c} type="button"
                                onClick={() => { setSlotColor(activeSlot, c); setShowPicker(false); }}
                                className={`w-7 h-7 rounded-md border-2 transition-transform hover:scale-110 ${
                                    colors[activeSlot] === c ? 'border-iec-pink-500 scale-110' : 'border-slate-200 hover:border-slate-300'
                                }`}
                                style={{ background: c }} title={c} />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function parseColors(colorStr) {
    if (!colorStr) return [];
    return colorStr.split(',').map(c => c.trim()).filter(c => /^#[0-9a-fA-F]{6}$/.test(c));
}

export default function PartyEdit({ auth, party, candidates = [], activeElectionId = null, flash }) {
    const initialColors = party.colors_array || parseColors(party.color);

    const { data, setData, post, processing, errors } = useForm({
        name:         party.name         || '',
        abbreviation: party.abbreviation || '',
        colors:       initialColors,
        leader_name:  party.leader_name  || '',
        motto:        party.motto        || '',
        headquarters: party.headquarters || '',
        website:      party.website      || '',
        leader_photo: null,
        symbol:       null,
    });

    const [leaderPreview, setLeaderPreview] = useState(party.leader_photo_url || null);
    const [symbolPreview, setSymbolPreview] = useState(party.symbol_url || null);

    const handleLeaderPhoto = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setData('leader_photo', file);
        const reader = new FileReader();
        reader.onloadend = () => setLeaderPreview(reader.result);
        reader.readAsDataURL(file);
    };

    const handleSymbol = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setData('symbol', file);
        const reader = new FileReader();
        reader.onloadend = () => setSymbolPreview(reader.result);
        reader.readAsDataURL(file);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/admin/parties/${party.id}/update`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-4xl">
                <div className="mb-6">
                    <Link href="/admin/parties" className="ws-page-back">
                        Back to Parties
                    </Link>
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="ws-page-title">Edit Party</h1>
                            <p className="ws-page-desc">{party.name}</p>
                        </div>
                        {/* Party color swatch */}
                        <div className="flex-shrink-0 w-12 h-12 rounded-xl border border-slate-200"
                            style={initialColors.length > 1
                                ? { background: `linear-gradient(135deg, ${initialColors.join(', ')})` }
                                : { background: initialColors[0] || '#334155' }} />
                    </div>
                </div>

                {/* Flash messages */}
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

                {/* ── Party Details Form ──────────────────────────────────────── */}
                <div className="bg-white rounded-xl p-6 border border-slate-200 mb-6">
                    <h2 className="text-iec-navy font-bold text-lg mb-5 pb-3 border-b border-slate-200">
                        Party Details
                    </h2>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Name & Abbreviation */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Party Name <span className="text-red-400">*</span>
                                </label>
                                <input type="text" value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy focus:outline-none focus:border-iec-pink-500"
                                    placeholder="Party's full name" required />
                                {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Abbreviation <span className="text-red-400">*</span>
                                </label>
                                <input type="text" value={data.abbreviation}
                                    onChange={(e) => setData('abbreviation', e.target.value.toUpperCase().slice(0, 10))}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono focus:outline-none focus:border-iec-pink-500"
                                    placeholder="e.g., UDP" maxLength={10} required />
                                {errors.abbreviation && <p className="text-red-400 text-sm mt-1">{errors.abbreviation}</p>}
                            </div>
                        </div>

                        {/* Colors */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Party Colors
                                <span className="text-slate-500 font-normal text-xs ml-2">(up to 3)</span>
                            </label>
                            <MultiColorPicker
                                colors={data.colors}
                                onChange={(newColors) => setData('colors', newColors)}
                                max={3}
                            />
                            {data.colors.map((c, i) => (
                                <input key={i} type="hidden" name={`color_${i}`} value={c} />
                            ))}
                        </div>

                        {/* Motto */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">Party Motto</label>
                            <input type="text" value={data.motto}
                                onChange={(e) => setData('motto', e.target.value)}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy focus:outline-none focus:border-iec-pink-500"
                                placeholder="Party's motto or slogan" />
                        </div>

                        {/* Headquarters & Website */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">Headquarters</label>
                                <input type="text" value={data.headquarters}
                                    onChange={(e) => setData('headquarters', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy focus:outline-none focus:border-iec-pink-500"
                                    placeholder="e.g., Banjul" />
                            </div>
                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">Website</label>
                                <input type="url" value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy focus:outline-none focus:border-iec-pink-500"
                                    placeholder="https://..." />
                            </div>
                        </div>

                        {/* Leader */}
                        <div className="border border-slate-200 rounded-xl p-6">
                            <h3 className="text-iec-navy font-bold text-lg mb-4">Party Leader</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-slate-600 mb-2 font-semibold">Leader's Full Name</label>
                                    <input type="text" value={data.leader_name}
                                        onChange={(e) => setData('leader_name', e.target.value)}
                                        className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy focus:outline-none focus:border-iec-pink-500"
                                        placeholder="Full name" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 mb-2 font-semibold">Leader's Photo</label>
                                    <div className="flex items-center gap-4">
                                        {leaderPreview ? (
                                            <img src={leaderPreview} alt="Leader"
                                                className="w-16 h-16 rounded-full object-cover border-2 border-teal-500 flex-shrink-0" />
                                        ) : (
                                            <div className="w-16 h-16 rounded-full bg-white flex items-center justify-center border-2 border-slate-200 flex-shrink-0">
                                                <span className="text-slate-500 text-2xl font-bold">
                                                    {party.abbreviation?.charAt(0) ?? '?'}
                                                </span>
                                            </div>
                                        )}
                                        <label className="px-4 py-2 bg-white hover:bg-slate-100 text-iec-navy rounded-lg cursor-pointer text-sm transition-colors">
                                            {leaderPreview ? 'Change Photo' : 'Upload Photo'}
                                            <input type="file" accept="image/*" className="hidden" onChange={handleLeaderPhoto} />
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Symbol */}
                        <div className="border border-slate-200 rounded-xl p-6">
                            <h3 className="text-iec-navy font-bold text-lg mb-4">Party Symbol / Logo</h3>
                            <div className="flex items-center gap-6">
                                {symbolPreview ? (
                                    <img src={symbolPreview} alt="Symbol"
                                        className="w-24 h-24 object-contain border border-slate-200 rounded-lg bg-white p-1" />
                                ) : (
                                    <div className="w-24 h-24 bg-white border border-slate-200 rounded-lg flex items-center justify-center">
                                        <span className="text-slate-500 text-xs text-center px-2">No symbol uploaded</span>
                                    </div>
                                )}
                                <div>
                                    <label className="px-4 py-2 bg-white hover:bg-slate-100 text-iec-navy rounded-lg cursor-pointer text-sm inline-block transition-colors">
                                        {symbolPreview ? 'Replace Symbol' : 'Upload Symbol / Logo'}
                                        <input type="file" accept="image/*" className="hidden" onChange={handleSymbol} />
                                    </label>
                                    <p className="text-slate-500 text-xs mt-2">PNG or SVG recommended. Max 5MB.</p>
                                </div>
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="flex gap-4">
                            <button type="submit" disabled={processing}
                                className="flex-1 px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white font-bold rounded-lg transition-colors">
                                {processing ? 'Saving…' : '✓ Save Party Details'}
                            </button>
                            <Link href="/admin/parties"
                                className="px-6 py-3 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg text-center transition-colors">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>

                {/* ── Candidates Section ──────────────────────────────────────── */}
                <CandidatesSection
                    partyId={party.id}
                    activeElectionId={activeElectionId}
                    initialCandidates={candidates}
                />
            </div>
        </AppLayout>
    );
}
