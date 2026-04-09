import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

// ── Multi-color picker: up to 3 swatches ──────────────────────────────────
const PRESET_COLORS = [
    '#ef4444','#f97316','#eab308','#22c55e','#10b981',
    '#14b8a6','#06b6d4','#3b82f6','#6366f1','#8b5cf6',
    '#ec4899','#f43f5e','#ffffff','#000000','#6b7280',
    '#92400e','#064e3b','#1e3a8a','#4a044e','#7f1d1d',
];

function ColorSwatch({ color, size = 'md' }) {
    const sz = size === 'sm' ? 'w-5 h-5' : 'w-7 h-7';
    return (
        <span
            className={`${sz} rounded-full border-2 border-white/30 inline-block flex-shrink-0`}
            style={{ background: color }}
            title={color}
        />
    );
}

function MultiColorPicker({ colors, onChange, max = 3 }) {
    const [showPicker, setShowPicker] = useState(false);
    const [activeSlot, setActiveSlot] = useState(0); // which slot is being edited
    const [customInput, setCustomInput] = useState('');

    // colors is an array of up to `max` hex strings
    const slots = Array.from({ length: max }, (_, i) => colors[i] || null);

    const setSlotColor = (slotIndex, hex) => {
        const next = [...colors];
        next[slotIndex] = hex;
        // Remove trailing nulls
        onChange(next.filter(Boolean));
    };

    const removeSlot = (slotIndex) => {
        const next = colors.filter((_, i) => i !== slotIndex);
        onChange(next);
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

    // CSS gradient string for the preview stripe
    const gradientStyle = () => {
        if (colors.length === 0) return { background: '#334155' };
        if (colors.length === 1) return { background: colors[0] };
        return { background: `linear-gradient(135deg, ${colors.join(', ')})` };
    };

    return (
        <div>
            {/* Color strip preview */}
            <div className="flex items-center gap-3 mb-3">
                <div
                    className="w-16 h-10 rounded-lg border border-slate-600 flex-shrink-0"
                    style={gradientStyle()}
                />
                <div className="text-gray-300 text-sm">
                    {colors.length === 0
                        ? 'No colors selected'
                        : colors.join(' + ')
                    }
                </div>
            </div>

            {/* Slots */}
            <div className="flex gap-3 flex-wrap mb-3">
                {slots.map((color, i) => (
                    <div key={i} className="relative">
                        {color ? (
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => openSlot(i)}
                                    className="w-10 h-10 rounded-lg border-2 border-white/30 hover:border-white/70 transition-colors"
                                    style={{ background: color }}
                                    title={`Color ${i + 1}: ${color} — click to change`}
                                />
                                <button
                                    type="button"
                                    onClick={() => removeSlot(i)}
                                    className="text-gray-400 hover:text-red-400 text-lg leading-none"
                                    title="Remove this color"
                                >
                                    ×
                                </button>
                            </div>
                        ) : (
                            colors.length <= i && (
                                <button
                                    type="button"
                                    onClick={() => openSlot(i)}
                                    className="w-10 h-10 rounded-lg border-2 border-dashed border-slate-500
                                               hover:border-teal-400 text-slate-500 hover:text-teal-400
                                               transition-colors flex items-center justify-center text-xl"
                                    title={`Add color ${i + 1}`}
                                >
                                    +
                                </button>
                            )
                        )}
                    </div>
                ))}
            </div>

            <p className="text-gray-500 text-xs mb-3">
                Select up to {max} colors. Parties with a single color pick one; parties with tri-color flags pick three.
            </p>

            {/* Color picker panel */}
            {showPicker && (
                <div className="bg-slate-900/80 border border-slate-600 rounded-xl p-4 mt-2">
                    <div className="flex items-center justify-between mb-3">
                        <span className="text-white text-sm font-semibold">
                            Picking Color {activeSlot + 1}
                        </span>
                        <button type="button" onClick={() => setShowPicker(false)}
                                className="text-gray-400 hover:text-white text-lg">×</button>
                    </div>

                    {/* Native color input */}
                    <div className="flex items-center gap-3 mb-4">
                        <input
                            type="color"
                            value={customInput.match(/^#[0-9a-fA-F]{6}$/) ? customInput : '#3b82f6'}
                            onChange={(e) => setCustomInput(e.target.value)}
                            className="w-12 h-10 rounded-lg cursor-pointer border-0 bg-transparent"
                        />
                        <input
                            type="text"
                            value={customInput}
                            onChange={(e) => setCustomInput(e.target.value)}
                            placeholder="#rrggbb"
                            maxLength={7}
                            className="flex-1 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg
                                       text-white font-mono text-sm"
                        />
                        <button
                            type="button"
                            onClick={applyCustom}
                            className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm rounded-lg font-semibold"
                        >
                            Apply
                        </button>
                    </div>

                    {/* Preset swatches */}
                    <div className="grid grid-cols-10 gap-1.5">
                        {PRESET_COLORS.map((c) => (
                            <button
                                key={c}
                                type="button"
                                onClick={() => {
                                    setSlotColor(activeSlot, c);
                                    setShowPicker(false);
                                }}
                                className={`w-7 h-7 rounded-md border-2 transition-transform hover:scale-110 ${
                                    colors[activeSlot] === c
                                        ? 'border-white scale-110'
                                        : 'border-slate-600 hover:border-white/60'
                                }`}
                                style={{ background: c }}
                                title={c}
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────

export default function PartyCreate({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        name:         '',
        abbreviation: '',
        colors:       [],          // array of 1-3 hex strings
        leader_name:  '',
        motto:        '',
        headquarters: '',
        website:      '',
        leader_photo: null,
        symbol:       null,
    });

    const [leaderPreview, setLeaderPreview] = useState(null);
    const [symbolPreview, setSymbolPreview]  = useState(null);

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
        // Serialize colors array as JSON string so it survives multipart FormData
        post('/admin/parties', { forceFormData: true });
    };

    // Color gradient preview
    const colorPreviewStyle = () => {
        const c = data.colors;
        if (!c || c.length === 0) return { background: '#334155' };
        if (c.length === 1) return { background: c[0] };
        return { background: `linear-gradient(135deg, ${c.join(', ')})` };
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-3xl">
                <div className="mb-6">
                    <Link href="/admin/parties" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Parties
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Register New Political Party</h1>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Party Name & Abbreviation */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Party Name <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder="party's full name"
                                    required
                                />
                                {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Abbreviation <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.abbreviation}
                                    onChange={(e) => setData('abbreviation', e.target.value.toUpperCase().slice(0, 10))}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white font-mono"
                                    placeholder="party abbreviation (e.g., DEM, GOP)"
                                    maxLength={10}
                                    required
                                />
                                {errors.abbreviation && <p className="text-red-400 text-sm mt-1">{errors.abbreviation}</p>}
                            </div>
                        </div>

                        {/* Party Colors (1-3) */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Party Colors
                                <span className="text-gray-500 font-normal text-xs ml-2">
                                    (up to 3 — e.g., red + white + blue for tri-color parties)
                                </span>
                            </label>

                            <MultiColorPicker
                                colors={data.colors}
                                onChange={(newColors) => setData('colors', newColors)}
                                max={3}
                            />

                            {/* Hidden inputs to serialize colors into FormData */}
                            {data.colors.map((c, i) => (
                                <input key={i} type="hidden" name={`color_${i}`} value={c} />
                            ))}
                            <input type="hidden" name="colors_count" value={data.colors.length} />

                            {errors.color && <p className="text-red-400 text-sm mt-1">{errors.color}</p>}
                        </div>

                        {/* Motto */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Party Motto</label>
                            <input
                                type="text"
                                value={data.motto}
                                onChange={(e) => setData('motto', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="party's motto or slogan"
                            />
                            {errors.motto && <p className="text-red-400 text-sm mt-1">{errors.motto}</p>}
                        </div>

                        {/* Headquarters & Website */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Headquarters</label>
                                <input
                                    type="text"
                                    value={data.headquarters}
                                    onChange={(e) => setData('headquarters', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder="party headquarters location"
                                />
                                {errors.headquarters && <p className="text-red-400 text-sm mt-1">{errors.headquarters}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Website</label>
                                <input
                                    type="url"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder="https://partywebsite.gm"
                                />
                                {errors.website && <p className="text-red-400 text-sm mt-1">{errors.website}</p>}
                            </div>
                        </div>

                        {/* Leader Section */}
                        <div className="border border-slate-700 rounded-xl p-6">
                            <h3 className="text-white font-bold text-lg mb-4">Party Leader</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-gray-300 mb-2 font-semibold">Leader's Full Name</label>
                                    <input
                                        type="text"
                                        value={data.leader_name}
                                        onChange={(e) => setData('leader_name', e.target.value)}
                                        className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                        placeholder="Candidate's full name"
                                    />
                                    {errors.leader_name && <p className="text-red-400 text-sm mt-1">{errors.leader_name}</p>}
                                </div>

                                <div>
                                    <label className="block text-gray-300 mb-2 font-semibold">Leader's Photo</label>
                                    <div className="flex items-center gap-4">
                                        {leaderPreview ? (
                                            <img src={leaderPreview} alt="Leader"
                                                 className="w-16 h-16 rounded-full object-cover border-2 border-teal-500" />
                                        ) : (
                                            <div className="w-16 h-16 rounded-full bg-slate-700 flex items-center justify-center border-2 border-slate-600">
                                                <span className="text-gray-400 text-xs text-center">No photo</span>
                                            </div>
                                        )}
                                        <label className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg cursor-pointer text-sm">
                                            Upload Photo
                                            <input type="file" accept="image/*" className="hidden" onChange={handleLeaderPhoto} />
                                        </label>
                                    </div>
                                    {errors.leader_photo && <p className="text-red-400 text-sm mt-1">{errors.leader_photo}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Party Symbol */}
                        <div className="border border-slate-700 rounded-xl p-6">
                            <h3 className="text-white font-bold text-lg mb-4">Party Symbol / Logo</h3>
                            <div className="flex items-center gap-6">
                                {symbolPreview ? (
                                    <img src={symbolPreview} alt="Symbol"
                                         className="w-24 h-24 object-contain border border-slate-600 rounded-lg bg-white p-1" />
                                ) : (
                                    <div className="w-24 h-24 bg-slate-700 border border-slate-600 rounded-lg flex items-center justify-center">
                                        <span className="text-gray-400 text-xs text-center">No symbol</span>
                                    </div>
                                )}
                                <div>
                                    <label className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg cursor-pointer text-sm inline-block">
                                        Upload Symbol / Logo
                                        <input type="file" accept="image/*" className="hidden" onChange={handleSymbol} />
                                    </label>
                                    <p className="text-gray-400 text-xs mt-2">PNG or SVG recommended for clear display</p>
                                </div>
                            </div>
                            {errors.symbol && <p className="text-red-400 text-sm mt-1">{errors.symbol}</p>}
                        </div>

                        {/* Submit */}
                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Registering…' : 'Register Political Party'}
                            </button>
                            <Link href="/admin/parties"
                                  className="flex-1 px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-center">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
