import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

// Generate a cryptographically-random password
function generatePassword(length = 14) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*';
    return Array.from(crypto.getRandomValues(new Uint8Array(length)))
        .map(b => chars[b % chars.length])
        .join('');
}

export default function UserCreate({ auth, pollingStations = [], wards = [], constituencies = [], adminAreas = [] }) {
    const [generatedPassword, setGeneratedPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [copied, setCopied] = useState(false);

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
        must_change_password: true,
    });

    const [selectedRole, setSelectedRole] = useState('polling-officer');

    const handleRoleChange = (role) => {
        setSelectedRole(role);
        setData('role', role);
    };

    const handleGeneratePassword = () => {
        const pwd = generatePassword();
        setGeneratedPassword(pwd);
        setData('password', pwd);
        setShowPassword(true);
    };

    const handleCopyPassword = () => {
        navigator.clipboard.writeText(data.password || generatedPassword).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
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
                                Used for 2FA verification code delivery. Include country code.
                            </p>
                            {errors.phone && <p className="text-red-400 text-sm mt-1">{errors.phone}</p>}
                        </div>

                        {/* Password Section */}
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <label className="text-slate-600 font-semibold">
                                    Password <span className="text-red-400">*</span>
                                </label>
                                <button
                                    type="button"
                                    onClick={handleGeneratePassword}
                                    className="px-3 py-1.5 bg-iec-pink-600 hover:bg-iec-pink-700 text-white text-xs font-semibold rounded-lg transition-colors"
                                >
                                    🎲 Generate Secure Password
                                </button>
                            </div>

                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="new-password"
                                    className="w-full px-4 py-3 pr-24 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono"
                                    placeholder="Minimum 8 characters"
                                    required
                                />
                                <div className="absolute right-2 top-1/2 -translate-y-1/2 flex gap-1">
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(v => !v)}
                                        className="px-2 py-1 text-slate-500 hover:text-slate-700 text-xs"
                                    >
                                        {showPassword ? '🙈' : '👁'}
                                    </button>
                                    {data.password && (
                                        <button
                                            type="button"
                                            onClick={handleCopyPassword}
                                            className="px-2 py-1 text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 rounded"
                                        >
                                            {copied ? '✓ Copied' : '📋'}
                                        </button>
                                    )}
                                </div>
                            </div>

                            {data.password && (
                                <div className="mt-2 flex items-center gap-3">
                                    <div className="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                        <div
                                            className={`h-full rounded-full transition-all ${
                                                data.password.length < 8 ? 'w-1/5 bg-red-400' :
                                                data.password.length < 12 ? 'w-2/5 bg-amber-400' :
                                                'w-4/5 bg-green-400'
                                            }`}
                                        />
                                    </div>
                                    <span className="text-xs text-slate-500">{data.password.length} chars</span>
                                </div>
                            )}

                            {errors.password && <p className="text-red-400 text-sm mt-1">{errors.password}</p>}

                            <p className="text-slate-500 text-xs mt-1">
                                💡 Use "Generate" for a secure random password, then copy it to share with the user.
                            </p>
                        </div>

                        {/* Force password change toggle */}
                        <label className="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-lg cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.must_change_password}
                                onChange={(e) => setData('must_change_password', e.target.checked)}
                                className="mt-0.5 h-4 w-4 text-iec-pink-600 bg-white border-slate-200 rounded"
                            />
                            <div>
                                <div className="text-amber-800 font-semibold text-sm">
                                    Require password change on first login
                                </div>
                                <div className="text-amber-600 text-xs mt-0.5">
                                    The user will be prompted to set a new password immediately after logging in. Recommended when using a generated or temporary password.
                                </div>
                            </div>
                        </label>

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