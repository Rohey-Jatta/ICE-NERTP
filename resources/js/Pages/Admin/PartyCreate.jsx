import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';

export default function PartyCreate({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        abbreviation: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/parties');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-3xl font-bold text-white">Register New Political Party</h1>
                    <button
                        onClick={() => router.visit('/admin/parties')}
                        className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg"
                    >
                        Back to Parties
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Party Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., Democratic Party"
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Party Abbreviation</label>
                            <input
                                type="text"
                                value={data.abbreviation}
                                onChange={(e) => setData('abbreviation', e.target.value.toUpperCase().slice(0, 10))}
                                maxLength={10}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., DP (max 10 characters)"
                            />
                            {errors.abbreviation && <p className="text-red-400 text-sm mt-1">{errors.abbreviation}</p>}
                        </div>

                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Registering...' : 'Register Party'}
                            </button>
                            <button
                                type="button"
                                onClick={() => router.visit('/admin/parties')}
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
