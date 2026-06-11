import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

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
    const [showPassword, setShowPassword] = useState(false);

    // Generate a random, readable default password (e.g. "Xk7mPq2rTn4w").
    const generatePassword = () => {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        const bytes = new Uint32Array(12);
        window.crypto.getRandomValues(bytes);
        const generated = Array.from(bytes, (b) => chars[b % chars.length]).join('');
        setData('password', generated);
        setShowPassword(true);
    };

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
                        <label className="block text-slate-600 mb-2 font-semibold">
                            Assign to Polling Station
                            <span className="text-slate-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {pollingStations.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">
                                    No polling stations found. <a href="/admin/polling-stations/create" className="underline text-iec-pink-600">Create one first</a>.
                                </p>
                            </div>
                        ) : (
                            <SearchableSelect
                                value={String(data.polling_station_id)}
                                onChange={(val) => setData('polling_station_id', val)}
                                options={[{ value: '', label: '— No station assigned yet —' }, ...pollingStations.map((station) => ({ value: String(station.id), label: `${station.code} — ${station.name}` }))]}
                                placeholder="Select polling station"
                                className="w-full"
                            />
                        )}
                        {errors.polling_station_id && <p className="text-red-400 text-sm mt-1">{errors.polling_station_id}</p>}
                    </div>
                );
            case 'ward-approver':
                return (
                    <div>
                        <label className="block text-slate-600 mb-2 font-semibold">
                            Assign to Ward
                            <span className="text-slate-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {wards.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">No wards found. Configure the administrative hierarchy first.</p>
                            </div>
                        ) : (
                            <SearchableSelect
                                value={String(data.ward_id)}
                                onChange={(val) => setData('ward_id', val)}
                                options={[{ value: '', label: '— No ward assigned yet —' }, ...wards.map((ward) => ({ value: String(ward.id), label: ward.name }))]}
                                placeholder="Select ward"
                                className="w-full"
                            />
                        )}
                        {errors.ward_id && <p className="text-red-400 text-sm mt-1">{errors.ward_id}</p>}
                    </div>
                );
            case 'constituency-approver':
                return (
                    <div>
                        <label className="block text-slate-600 mb-2 font-semibold">
                            Assign to Constituency
                            <span className="text-slate-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {constituencies.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">No constituencies found. Configure the administrative hierarchy first.</p>
                            </div>
                        ) : (
                            <SearchableSelect
                                value={String(data.constituency_id)}
                                onChange={(val) => setData('constituency_id', val)}
                                options={[{ value: '', label: '— No constituency assigned yet —' }, ...constituencies.map((c) => ({ value: String(c.id), label: c.name }))]}
                                placeholder="Select constituency"
                                className="w-full"
                            />
                        )}
                        {errors.constituency_id && <p className="text-red-400 text-sm mt-1">{errors.constituency_id}</p>}
                    </div>
                );
            case 'admin-area-approver':
                return (
                    <div>
                        <label className="block text-slate-600 mb-2 font-semibold">
                            Assign to Admin Area
                            <span className="text-slate-500 font-normal text-xs ml-2">(Optional)</span>
                        </label>
                        {adminAreas.length === 0 ? (
                            <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                <p className="text-amber-300 text-sm">No admin areas found. Configure the administrative hierarchy first.</p>
                            </div>
                        ) : (
                            <SearchableSelect
                                value={String(data.admin_area_id)}
                                onChange={(val) => setData('admin_area_id', val)}
                                options={[{ value: '', label: '— No admin area assigned yet —' }, ...adminAreas.map((area) => ({ value: String(area.id), label: area.name }))]}
                                placeholder="Select admin area"
                                className="w-full"
                            />
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
            <div className="ws-container max-w-3xl">
                <div className="mb-6">
                    <button onClick={() => router.visit('/admin/users')} className="ws-page-back">
                        Back to Users
                    </button>
                    <h1 className="ws-page-title">Add New User</h1>
                    <p className="ws-page-desc">Create a workspace account and assign its operational role.</p>
                </div>

                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    {/* autocomplete="off" on form prevents browser from autofilling fields */}
                    <form onSubmit={handleSubmit} className="space-y-6" autoComplete="off">

                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">Name <span className="text-red-400">*</span></label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                autoComplete="off"
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                placeholder="Full Name"
                                required
                            />
                            {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">Email <span className="text-red-400">*</span></label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                autoComplete="off"
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                placeholder="user@iec.gm"
                                required
                            />
                            {errors.email && <p className="text-red-400 text-sm mt-1">{errors.email}</p>}
                        </div>

                        {/* Phone — required for 2FA SMS delivery */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Phone Number <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="tel"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                autoComplete="off"
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                placeholder="+220XXXXXXX"
                                required
                            />
                            <p className="text-slate-500 text-xs mt-1">
                                Used for 2FA verification code delivery. Include country code (e.g., +220 for Gambia).
                            </p>
                            {errors.phone && <p className="text-red-400 text-sm mt-1">{errors.phone}</p>}
                        </div>

                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">Default Password <span className="text-red-400">*</span></label>
                            <div className="flex gap-2">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="new-password"
                                    className="flex-1 px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                    placeholder="Set a default password (min 8 characters)"
                                    minLength={8}
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={generatePassword}
                                    className="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-iec-navy font-semibold rounded-lg whitespace-nowrap"
                                    title="Generate a random default password"
                                >
                                    ⚡ Generate
                                </button>
                            </div>
                            <div className="flex items-center justify-between mt-1">
                                <p className="text-slate-500 text-xs">
                                    Share this default password with the user — they will be required to change it at first login.
                                </p>
                                <label className="flex items-center gap-1.5 text-xs text-slate-500 flex-shrink-0 ml-3">
                                    <input type="checkbox" checked={showPassword} onChange={(e) => setShowPassword(e.target.checked)} />
                                    Show
                                </label>
                            </div>
                            {errors.password && <p className="text-red-400 text-sm mt-1">{errors.password}</p>}
                        </div>

                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">Role <span className="text-red-400">*</span></label>
                            <SearchableSelect
                                value={data.role}
                                onChange={(val) => handleRoleChange(val)}
                                options={[
                                    { value: 'polling-officer', label: 'Polling Station Officer' },
                                    { value: 'ward-approver', label: 'Ward Approver' },
                                    { value: 'constituency-approver', label: 'Constituency Approver' },
                                    { value: 'admin-area-approver', label: 'Admin Area Approver' },
                                    { value: 'iec-chairman', label: 'IEC Chairman' },
                                    { value: 'party-representative', label: 'Party Representative' },
                                    { value: 'election-monitor', label: 'Election Monitor' }
                                ]}
                                placeholder="Select role"
                                className="w-full"
                            />
                            {errors.role && <p className="text-red-400 text-sm mt-1">{errors.role}</p>}
                        </div>

                        {renderRoleSpecificFields()}

                        <div className="flex gap-4">
                            <button type="submit" disabled={processing}
                                className="flex-1 px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white font-bold rounded-lg">
                                {processing ? 'Creating...' : 'Create User'}
                            </button>
                            <button type="button" onClick={() => router.visit('/admin/users')}
                                className="flex-1 px-6 py-3 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
