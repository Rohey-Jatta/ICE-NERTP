import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

export default function WardCreate({ auth, constituencies = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        parent_id: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/hierarchy/wards');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="mb-6">
                    <Link href="/admin/hierarchy/wards" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-block">
                        ← Back to Wards
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Register New Ward</h1>
                    <p className="text-slate-500 text-sm mt-1">
                        Wards belong to a Constituency. Each ward can contain multiple polling stations.
                    </p>
                </div>

                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Parent Constituency */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Constituency <span className="text-red-400">*</span>
                            </label>
                            {constituencies.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">
                                        No constituencies found.{' '}
                                        <Link href="/admin/hierarchy/constituencies/create" className="underline text-iec-pink-600">
                                            Create one first
                                        </Link>.
                                    </p>
                                </div>
                            ) : (
                                <SearchableSelect
                                    value={String(data.parent_id)}
                                    onChange={(val) => setData('parent_id', val)}
                                    options={[{ value: '', label: '— Select Constituency —' }, ...constituencies.map((c) => ({ value: String(c.id), label: `${c.name}${c.parent_name ? ` (${c.parent_name})` : ''}` }))]}
                                    placeholder="Select constituency"
                                    className="w-full"
                                    required
                                />
                            )}
                            {errors.parent_id && <p className="text-red-400 text-sm mt-1">{errors.parent_id}</p>}
                        </div>

                        {/* Code */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Ward Code <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono"
                                placeholder="e.g., BJL-N-W01, CAMP-01"
                                maxLength={20}
                                required
                            />
                            <p className="text-slate-500 text-xs mt-1">A short unique code for this ward.</p>
                            {errors.code && <p className="text-red-400 text-sm mt-1">{errors.code}</p>}
                        </div>

                        {/* Name */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Ward Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                placeholder="e.g., Campama Ward"
                                required
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing || constituencies.length === 0}
                                className="flex-1 px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Registering…' : 'Register Ward'}
                            </button>
                            <Link
                                href="/admin/hierarchy/wards"
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
