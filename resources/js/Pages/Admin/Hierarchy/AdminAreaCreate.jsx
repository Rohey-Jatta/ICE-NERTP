import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';

export default function AdminAreaCreate({ auth, elections = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        election_id: elections[0]?.id || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/hierarchy/admin-areas');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="mb-6">
                    <Link href="/admin/hierarchy/admin-areas" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Register New Administrative Area</h1>
                    <p className="text-gray-400 text-sm mt-1">
                        This the top-level administrative regions. Each can contain multiple constituencies.
                    </p>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Election */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Election <span className="text-red-400">*</span>
                            </label>
                            {elections.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">
                                        No active elections found.{' '}
                                        <Link href="/admin/elections/create" className="underline text-teal-400">Create one first</Link>.
                                    </p>
                                </div>
                            ) : (
                                <select
                                    value={data.election_id}
                                    onChange={(e) => setData('election_id', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    required
                                >
                                    <option value="">— Select Election —</option>
                                    {elections.map((el) => (
                                        <option key={el.id} value={el.id}>{el.name}</option>
                                    ))}
                                </select>
                            )}
                            {errors.election_id && <p className="text-red-400 text-sm mt-1">{errors.election_id}</p>}
                        </div>

                        {/* Code */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Area Code <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white font-mono"
                                placeholder="e.g., BJL, WCR, NBR"
                                maxLength={20}
                                required
                            />
                            <p className="text-gray-500 text-xs mt-1">A short unique code for this administrative area.</p>
                            {errors.code && <p className="text-red-400 text-sm mt-1">{errors.code}</p>}
                        </div>

                        {/* Name */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Area Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., Banjul Administrative Area"
                                required
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-6 py-3 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Registering…' : 'Register Administrative Area'}
                            </button>
                            <Link
                                href="/admin/hierarchy/admin-areas"
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
