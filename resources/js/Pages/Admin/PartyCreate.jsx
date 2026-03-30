import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function PartyCreate({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        abbreviation: '',
        color: '#1e40af',
        leader_name: '',
        motto: '',
        headquarters: '',
        website: '',
        leader_photo: null,
        symbol: null,
    });

    const [leaderPreview, setLeaderPreview] = useState(null);
    const [symbolPreview, setSymbolPreview] = useState(null);

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
        post('/admin/parties', {
            forceFormData: true, // Required for file uploads
        });
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
                                <label className="block text-gray-300 mb-2 font-semibold">Party Name <span className="text-red-400">*</span></label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder="e.g., United Democratic Party"
                                    required
                                />
                                {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Abbreviation <span className="text-red-400">*</span></label>
                                <input
                                    type="text"
                                    value={data.abbreviation}
                                    onChange={(e) => setData('abbreviation', e.target.value.toUpperCase().slice(0, 10))}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white font-mono"
                                    placeholder="UDP"
                                    maxLength={10}
                                    required
                                />
                                {errors.abbreviation && <p className="text-red-400 text-sm mt-1">{errors.abbreviation}</p>}
                            </div>
                        </div>

                        {/* Party Color */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Party Color</label>
                            <div className="flex items-center gap-4">
                                <input
                                    type="color"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    className="h-12 w-20 rounded-lg cursor-pointer border-0 bg-transparent"
                                />
                                <input
                                    type="text"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    className="w-32 px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white font-mono text-sm"
                                    placeholder="#1e40af"
                                    maxLength={7}
                                />
                                <div
                                    className="w-12 h-12 rounded-lg border border-slate-600"
                                    style={{ backgroundColor: data.color }}
                                />
                            </div>
                        </div>

                        {/* Motto */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Party Motto</label>
                            <input
                                type="text"
                                value={data.motto}
                                onChange={(e) => setData('motto', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., Unity, Freedom, Progress"
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
                                    placeholder="e.g., Banjul, The Gambia"
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
                                        placeholder="e.g., Ousainou Darboe"
                                    />
                                    {errors.leader_name && <p className="text-red-400 text-sm mt-1">{errors.leader_name}</p>}
                                </div>

                                <div>
                                    <label className="block text-gray-300 mb-2 font-semibold">Leader's Photo</label>
                                    <div className="flex items-center gap-4">
                                        {leaderPreview ? (
                                            <img src={leaderPreview} alt="Leader" className="w-16 h-16 rounded-full object-cover border-2 border-teal-500" />
                                        ) : (
                                            <div className="w-16 h-16 rounded-full bg-slate-700 flex items-center justify-center border-2 border-slate-600">
                                                <span className="text-gray-400 text-xs">No photo</span>
                                            </div>
                                        )}
                                        <label className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg cursor-pointer text-sm">
                                            Upload Photo
                                            <input
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={handleLeaderPhoto}
                                            />
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
                                    <img src={symbolPreview} alt="Symbol" className="w-24 h-24 object-contain border border-slate-600 rounded-lg bg-white p-1" />
                                ) : (
                                    <div className="w-24 h-24 bg-slate-700 border border-slate-600 rounded-lg flex items-center justify-center">
                                        <span className="text-gray-400 text-xs text-center">No symbol</span>
                                    </div>
                                )}
                                <div>
                                    <label className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg cursor-pointer text-sm inline-block">
                                        Upload Symbol / Logo
                                        <input
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={handleSymbol}
                                        />
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
                            <Link href="/admin/parties" className="flex-1 px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-center">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}