import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import SearchableSelect from '@/Components/SearchableSelect';

export default function AdminAreaEdit({ auth, adminArea, elections = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        code:        adminArea.code        || '',
        name:        adminArea.name        || '',
        election_id: adminArea.election_id?.toString() || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/admin/hierarchy/admin-areas/${adminArea.id}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="mb-6">
                    <Link href="/admin/hierarchy/admin-areas" className="text-slate-500 hover:text-iec-pink-500 text-sm mb-2 inline-block">
                        ← Back to Administrative Areas
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Edit Administrative Area</h1>
                    <p className="text-slate-500 text-sm mt-1">
                        Update this top-level administrative region.
                    </p>
                </div>


                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Election */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Election <span className="text-red-400">*</span>
                            </label>
                            {elections.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">
                                        No elections found.{' '}
                                        <Link href="/admin/elections/create" className="underline text-iec-pink-600">
                                            Create one first
                                        </Link>.
                                    </p>
                                </div>
                            ) : (
                                <SearchableSelect
                                    value={String(data.election_id)}
                                    onChange={(val) => setData('election_id', val)}
                                    options={[{ value: '', label: '— Select Election —' }, ...elections.map((el) => ({ value: String(el.id), label: el.name }))]}
                                    placeholder="Select election"
                                    className="w-full"
                                    required
                                />
                            )}
                            {errors.election_id && <p className="text-red-400 text-sm mt-1">{errors.election_id}</p>}
                        </div>

                        {/* Code */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Area Code <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono"
                                placeholder="e.g., BJL, WCR, NBR"
                                maxLength={20}
                                required
                            />
                            <p className="text-gray-500 text-xs mt-1">A short unique code for this administrative area.</p>
                            {errors.code && <p className="text-red-400 text-sm mt-1">{errors.code}</p>}
                        </div>

                        {/* Name */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Area Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                placeholder="e.g., Banjul Administrative Area"
                                required
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                            <Link
                                href="/admin/hierarchy/admin-areas"
                                className="flex-1 px-6 py-3 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg text-center"
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
