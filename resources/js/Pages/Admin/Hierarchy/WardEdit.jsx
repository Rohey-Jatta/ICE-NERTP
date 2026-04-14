import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';

export default function WardEdit({ auth, ward, constituencies = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        code:      ward.code      || '',
        name:      ward.name      || '',
        parent_id: ward.parent_id?.toString() || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/admin/hierarchy/wards/${ward.id}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="mb-6">
                    <Link href="/admin/hierarchy/wards" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Wards
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Edit Ward</h1>
                    <p className="text-gray-400 text-sm mt-1">
                        Update ward details. Wards belong to a Constituency.
                    </p>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Parent Constituency */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Constituency <span className="text-red-400">*</span>
                            </label>
                            {constituencies.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">
                                        No constituencies found.{' '}
                                        <Link href="/admin/hierarchy/constituencies/create" className="underline text-teal-400">
                                            Create one first
                                        </Link>.
                                    </p>
                                </div>
                            ) : (
                                <select
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    required
                                >
                                    <option value="">— Select Constituency —</option>
                                    {constituencies.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}{c.parent_name ? ` (${c.parent_name})` : ''}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {errors.parent_id && <p className="text-red-400 text-sm mt-1">{errors.parent_id}</p>}
                        </div>

                        {/* Code */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Ward Code <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white font-mono"
                                placeholder="e.g., BJL-N-W01"
                                maxLength={20}
                                required
                            />
                            <p className="text-gray-500 text-xs mt-1">A short unique code for this ward.</p>
                            {errors.code && <p className="text-red-400 text-sm mt-1">{errors.code}</p>}
                        </div>

                        {/* Name */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Ward Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., Campama Ward"
                                required
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing || constituencies.length === 0}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                            <Link
                                href="/admin/hierarchy/wards"
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
