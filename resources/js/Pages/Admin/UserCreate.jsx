import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';

export default function UserCreate({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'polling-officer',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/users');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-3xl font-bold text-white">Add New User</h1>
                    <button
                        onClick={() => router.visit('/admin/users')}
                        className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg"
                    >
                        ← Back to Users
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="Full Name"
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="user@iec.gm"
                            />
                            {errors.email && <p className="text-red-400 text-sm mt-1">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Password</label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="••••••••"
                            />
                            {errors.password && <p className="text-red-400 text-sm mt-1">{errors.password}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Role</label>
                            <select
                                value={data.role}
                                onChange={(e) => setData('role', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            >
                                <option value="polling-officer">Polling Station Officer</option>
                                <option value="ward-approver">Ward Approver</option>
                                <option value="constituency-approver">Constituency Approver</option>
                                <option value="admin-area-approver">Admin Area Approver</option>
                                <option value="iec-chairman">IEC Chairman</option>
                                <option value="party-representative">Party Representative</option>
                                <option value="election-monitor">Election Monitor</option>
                            </select>
                            {errors.role && <p className="text-red-400 text-sm mt-1">{errors.role}</p>}
                        </div>

                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Creating...' : 'Create User'}
                            </button>
                            <button
                                type="button"
                                onClick={() => router.visit('/admin/users')}
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
