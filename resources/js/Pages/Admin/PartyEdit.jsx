import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';

export default function PartyEdit({ auth, party }) {
    const { data, setData, put, processing, errors } = useForm({
        name:         party.name         || '',
        abbreviation: party.abbreviation || '',
        color:        party.color        || '#1e40af',
        leader_name:  party.leader_name  || '',
        motto:        party.motto        || '',
        headquarters: party.headquarters || '',
        website:      party.website      || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/admin/parties/${party.id}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-3xl">
                <div className="mb-6">
                    <Link href="/admin/parties" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Parties
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Edit Party: {party.name}</h1>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Name & Abbreviation */}
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
                                    maxLength={10}
                                    required
                                />
                                {errors.abbreviation && <p className="text-red-400 text-sm mt-1">{errors.abbreviation}</p>}
                            </div>
                        </div>

                        {/* Color */}
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
                                    maxLength={7}
                                />
                                <div
                                    className="w-12 h-12 rounded-lg border border-slate-600"
                                    style={{ backgroundColor: data.color }}
                                />
                            </div>
                        </div>

                        {/* Leader */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Leader's Name</label>
                            <input
                                type="text"
                                value={data.leader_name}
                                onChange={(e) => setData('leader_name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., Ousainou Darboe"
                            />
                        </div>

                        {/* Motto */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Motto</label>
                            <input
                                type="text"
                                value={data.motto}
                                onChange={(e) => setData('motto', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="Party motto"
                            />
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
                            </div>
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Website</label>
                                <input
                                    type="url"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder="https://..."
                                />
                                {errors.website && <p className="text-red-400 text-sm mt-1">{errors.website}</p>}
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                            <Link
                                href="/admin/parties"
                                className="flex-1 px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-center"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
