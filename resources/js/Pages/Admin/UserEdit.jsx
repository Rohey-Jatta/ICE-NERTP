import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';

export default function UserEdit({ auth, user, roles, pollingStations, wards, constituencies, adminAreas, parties }) {
    const { data, setData, put, processing, errors } = useForm({
        name:                user.name   || '',
        email:               user.email  || '',
        phone:               user.phone  || '',
        status:              user.status || 'active',
        role:                user.roles && user.roles.length > 0 ? user.roles[0].name : 'polling-officer',
        polling_station_id:  '',
        ward_id:             '',
        constituency_id:     '',
        admin_area_id:       '',
        party_id:            '',
        polling_station_ids: [],
        designation:         '',
        organization:        '',
        monitor_type:        'domestic',
    });

    const [selectedRole, setSelectedRole] = useState(data.role);

    const handleRoleChange = (role) => {
        setSelectedRole(role);
        setData('role', role);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/admin/users/${user.id}`);
    };

    const renderRoleSpecificFields = () => {
        switch (selectedRole) {
            case 'polling-officer':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">Assign to Polling Station</label>
                        <select value={data.polling_station_id}
                            onChange={(e) => setData('polling_station_id', e.target.value)}
                            className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                            <option value="">Select Polling Station</option>
                            {pollingStations.map((station) => (
                                <option key={station.id} value={station.id}>
                                    {station.code} - {station.name}
                                </option>
                            ))}
                        </select>
                    </div>
                );
            case 'ward-approver':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">Assign to Ward</label>
                        <select value={data.ward_id}
                            onChange={(e) => setData('ward_id', e.target.value)}
                            className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                            <option value="">Select Ward</option>
                            {wards.map((ward) => (
                                <option key={ward.id} value={ward.id}>{ward.name}</option>
                            ))}
                        </select>
                    </div>
                );
            case 'constituency-approver':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">Assign to Constituency</label>
                        <select value={data.constituency_id}
                            onChange={(e) => setData('constituency_id', e.target.value)}
                            className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                            <option value="">Select Constituency</option>
                            {constituencies.map((constituency) => (
                                <option key={constituency.id} value={constituency.id}>{constituency.name}</option>
                            ))}
                        </select>
                    </div>
                );
            case 'admin-area-approver':
                return (
                    <div>
                        <label className="block text-gray-300 mb-2 font-semibold">Assign to Admin Area</label>
                        <select value={data.admin_area_id}
                            onChange={(e) => setData('admin_area_id', e.target.value)}
                            className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                            <option value="">Select Admin Area</option>
                            {adminAreas.map((area) => (
                                <option key={area.id} value={area.id}>{area.name}</option>
                            ))}
                        </select>
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
                    <h1 className="text-3xl font-bold text-white">Edit User: {user.name}</h1>
                    <button onClick={() => router.visit('/admin/users')}
                        className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg">
                        ← Back to Users
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Name <span className="text-red-400">*</span></label>
                            <input type="text" value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="Full Name" required />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Email <span className="text-red-400">*</span></label>
                            <input type="email" value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="user@iec.gm" required />
                            {errors.email && <p className="text-red-400 text-sm mt-1">{errors.email}</p>}
                        </div>

                        {/* Phone — critical for 2FA */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Phone Number <span className="text-red-400">*</span>
                            </label>
                            <input type="tel" value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="+220XXXXXXX" required />
                            <p className="text-gray-500 text-xs mt-1">
                                Used for 2FA verification code delivery. Include country code (e.g., +220 for Gambia).
                            </p>
                            {errors.phone && <p className="text-red-400 text-sm mt-1">{errors.phone}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Status</label>
                            <select value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            {errors.status && <p className="text-red-400 text-sm mt-1">{errors.status}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Role</label>
                            <select value={data.role}
                                onChange={(e) => handleRoleChange(e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white">
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {role.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                    </option>
                                ))}
                            </select>
                            {errors.role && <p className="text-red-400 text-sm mt-1">{errors.role}</p>}
                        </div>

                        {renderRoleSpecificFields()}

                        <div className="flex gap-4">
                            <button type="submit" disabled={processing}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600 text-white font-bold rounded-lg">
                                {processing ? 'Updating...' : 'Update User'}
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