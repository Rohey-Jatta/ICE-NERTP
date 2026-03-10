import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';

export default function ElectionCreate({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'presidential',
        date: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/elections');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-3xl font-bold text-white">Create New Election</h1>
                    <button
                        onClick={() => router.visit('/admin/elections')}
                        className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg"
                    >
                        ← Back to Elections
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Election Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., National Presidential Election 2026"
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

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

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Election Date</label>
                            <input
                                type="date"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            />
                            {errors.date && <p className="text-red-400 text-sm mt-1">{errors.date}</p>}
                        </div>

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
