import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';

export default function UserCreate({ auth, pollingStations = [], wards = [], constituencies = [], adminAreas = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name:                '',
        email:               '',
        phone:               '',
        password:            '',
        role:                'polling-officer',
        polling_station_id:  '',
        ward_id:             '',
        constituency_id:     '',
        admin_area_id:       '',
    });

    const [selectedRole, setSelectedRole] = useState('polling-officer');

    const handleRoleChange = (role) => {
        setSelectedRole(role);
        setData('role', role);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/users');
    };

    const renderRoleSpecificFields = () => {
        switch (selectedRole) {
            case 'polling-officer':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">
                            Assign to Polling Station
                            <span className="text-gray-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {pollingStations.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">
                                    No polling stations found. <a href="/admin/polling-stations/create" className="underline text-teal-400">Create one first</a>.
                                </p>
                            </div>
                        ) : (
                            <select value={data.polling_station_id}
                                onChange={(e) => setData('polling_station_id', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                                <option value="">— No station assigned yet —</option>
                                {pollingStations.map((station) => (
                                    <option key={station.id} value={station.id}>
                                        {station.code} — {station.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        {errors.polling_station_id && <p className="text-red-400 text-sm mt-1">{errors.polling_station_id}</p>}
                    </div>
                );
            case 'ward-approver':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">
                            Assign to Ward
                            <span className="text-gray-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {wards.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">No wards found. Configure the administrative hierarchy first.</p>
                            </div>
                        ) : (
                            <select value={data.ward_id}
                                onChange={(e) => setData('ward_id', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                                <option value="">— No ward assigned yet —</option>
                                {wards.map((ward) => (
                                    <option key={ward.id} value={ward.id}>{ward.name}</option>
                                ))}
                            </select>
                        )}
                        {errors.ward_id && <p className="text-red-400 text-sm mt-1">{errors.ward_id}</p>}
                    </div>
                );
            case 'constituency-approver':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">
                            Assign to Constituency
                            <span className="text-gray-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {constituencies.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">No constituencies found. Configure the administrative hierarchy first.</p>
                            </div>
                        ) : (
                            <select value={data.constituency_id}
                                onChange={(e) => setData('constituency_id', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                                <option value="">— No constituency assigned yet —</option>
                                {constituencies.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        )}
                        {errors.constituency_id && <p className="text-red-400 text-sm mt-1">{errors.constituency_id}</p>}
                    </div>
                );
            case 'admin-area-approver':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">
                            Assign to Admin Area
                            <span className="text-gray-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {adminAreas.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">No admin areas found. Configure the administrative hierarchy first.</p>
                            </div>
                        ) : (
                            <select value={data.admin_area_id}
                                onChange={(e) => setData('admin_area_id', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                                <option value="">— No admin area assigned yet —</option>
                                {adminAreas.map((area) => (
                                    <option key={area.id} value={area.id}>{area.name}</option>
                                ))}
                            </select>
                        )}
                        {errors.admin_area_id && <p className="text-red-400 text-sm mt-1">{errors.admin_area_id}</p>}
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-3xl font-bold text-white">Add New User</h1>
                    <button onClick={() => router.visit('/admin/users')}
                        className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg">
                        ← Back to Users
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    {/* autocomplete="off" on form prevents browser from autofilling fields */}
                    <form onSubmit={handleSubmit} className="space-y-6" autoComplete="off">

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Name <span className="text-red-400">*</span></label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                autoComplete="off"
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="Full Name"
                                required
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Email <span className="text-red-400">*</span></label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                autoComplete="off"
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="user@iec.gm"
                                required
                            />
                            {errors.email && <p className="text-red-400 text-sm mt-1">{errors.email}</p>}
                        </div>

                        {/* Phone — required for 2FA SMS delivery */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Phone Number <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="tel"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                autoComplete="off"
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="+220XXXXXXX"
                                required
                            />
                            <p className="text-gray-500 text-xs mt-1">
                                Used for 2FA verification code delivery. Include country code (e.g., +220 for Gambia).
                            </p>
                            {errors.phone && <p className="text-red-400 text-sm mt-1">{errors.phone}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Password <span className="text-red-400">*</span></label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="••••••••"
                                required
                            />
                            {errors.password && <p className="text-red-400 text-sm mt-1">{errors.password}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Role <span className="text-red-400">*</span></label>
                            <select
                                value={data.role}
                                onChange={(e) => handleRoleChange(e.target.value)}
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

                        {renderRoleSpecificFields()}

                        <div className="flex gap-4">
                            <button type="submit" disabled={processing}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold rounded-lg">
                                {processing ? 'Creating...' : 'Create User'}
                            </button>
                            <button type="button" onClick={() => router.visit('/admin/users')}
                                className="flex-1 px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
