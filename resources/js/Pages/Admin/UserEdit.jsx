import { Button, Field, PageHeader, Panel, inputClass, roleLabel } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

function generatePassword(length = 14) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*';
    return Array.from(crypto.getRandomValues(new Uint8Array(length)))
        .map(b => chars[b % chars.length])
        .join('');
}

export default function UserEdit({ auth, user, roles, pollingStations, wards, constituencies, adminAreas }) {
    const [showPasswordSection, setShowPasswordSection] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [copied, setCopied] = useState(false);

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
        // Password reset fields (optional)
        new_password:        '',
        must_change_password: user.must_change_password ?? false,
    });

    const [selectedRole, setSelectedRole] = useState(data.role);

    const handleRoleChange = (role) => {
        setSelectedRole(role);
        setData('role', role);
    };

    const handleGeneratePassword = () => {
        const pwd = generatePassword();
        setData('new_password', pwd);
        setShowPassword(true);
    };

    const handleCopyPassword = () => {
        navigator.clipboard.writeText(data.new_password).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        put(`/admin/users/${user.id}`);
    };

    const renderRoleSpecificFields = () => {
        if (selectedRole === 'polling-officer') {
            return (
                <Field label="Assign to Polling Station">
                    <SearchableSelect
                        value={String(data.polling_station_id)}
                        onChange={(val) => setData('polling_station_id', val)}
                        options={[{ value: '', label: 'Select polling station' }, ...pollingStations.map((station) => ({ value: String(station.id), label: `${station.code} - ${station.name}` }))]}
                        placeholder="Select polling station"
                        className="w-full"
                    />
                </Field>
            );
        }

        if (selectedRole === 'ward-approver') {
            return (
                <Field label="Assign to Ward">
                    <SearchableSelect
                        value={String(data.ward_id)}
                        onChange={(val) => setData('ward_id', val)}
                        options={[{ value: '', label: 'Select ward' }, ...wards.map((ward) => ({ value: String(ward.id), label: ward.name }))]}
                        placeholder="Select ward"
                        className="w-full"
                    />
                </Field>
            );
        }

        if (selectedRole === 'constituency-approver') {
            return (
                <Field label="Assign to Constituency">
                    <SearchableSelect
                        value={String(data.constituency_id)}
                        onChange={(val) => setData('constituency_id', val)}
                        options={[{ value: '', label: 'Select constituency' }, ...constituencies.map((constituency) => ({ value: String(constituency.id), label: constituency.name }))]}
                        placeholder="Select constituency"
                        className="w-full"
                    />
                </Field>
            );
        }

        if (selectedRole === 'admin-area-approver') {
            return (
                <Field label="Assign to Administrative Area">
                    <SearchableSelect
                        value={String(data.admin_area_id)}
                        onChange={(val) => setData('admin_area_id', val)}
                        options={[{ value: '', label: 'Select administrative area' }, ...adminAreas.map((area) => ({ value: String(area.id), label: area.name }))]}
                        placeholder="Select administrative area"
                        className="w-full"
                    />
                </Field>
            );
        }

        return null;
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-3xl">
                <PageHeader
                    title={`Edit User: ${user.name}`}
                    description="Update account details, role assignment, and operational status."
                    backHref="/admin/users"
                    backLabel="Back to Users"
                />

                <Panel className="p-5">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <Field label="Name">
                            <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={inputClass} placeholder="Full name" required />
                            {errors.name && <p className="mt-1 text-sm text-rose-600">{errors.name}</p>}
                        </Field>

                        <Field label="Email">
                            <input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className={inputClass} placeholder="user@iec.gm" required />
                            {errors.email && <p className="mt-1 text-sm text-rose-600">{errors.email}</p>}
                        </Field>

                        <Field label="Phone Number">
                            <input type="tel" value={data.phone} onChange={(event) => setData('phone', event.target.value)} className={inputClass} placeholder="+220XXXXXXX" required />
                            <p className="mt-1 text-xs text-slate-500">Used for 2FA verification code delivery. Include country code.</p>
                            {errors.phone && <p className="mt-1 text-sm text-rose-600">{errors.phone}</p>}
                        </Field>

                        <div className="ws-form-grid">
                            <Field label="Status">
                                <SearchableSelect
                                    value={data.status}
                                    onChange={(val) => setData('status', val)}
                                    options={[
                                        { value: 'active', label: 'Active' },
                                        { value: 'inactive', label: 'Inactive' },
                                        { value: 'suspended', label: 'Suspended' }
                                    ]}
                                    placeholder="Status"
                                    className="w-full"
                                />
                                {errors.status && <p className="mt-1 text-sm text-rose-600">{errors.status}</p>}
                            </Field>

                            <Field label="Role">
                                <SearchableSelect
                                    value={data.role}
                                    onChange={(val) => handleRoleChange(val)}
                                    options={roles.map((role) => ({ value: role, label: roleLabel(role) }))}
                                    placeholder="Select role"
                                    className="w-full"
                                />
                                {errors.role && <p className="mt-1 text-sm text-rose-600">{errors.role}</p>}
                            </Field>
                        </div>

                        {renderRoleSpecificFields()}

                        {/* ── Password Reset Section ──────────────────────────── */}
                        <div className="border border-slate-200 rounded-xl overflow-hidden">
                            <button
                                type="button"
                                onClick={() => setShowPasswordSection(v => !v)}
                                className="w-full flex items-center justify-between p-4 bg-slate-50 hover:bg-slate-100 text-left transition-colors"
                            >
                                <div>
                                    <span className="font-semibold text-slate-700 text-sm">🔑 Reset Password</span>
                                    <span className="text-slate-500 text-xs ml-2">
                                        {user.must_change_password ? '(User must change on next login)' : '(Optional — leave blank to keep current)'}
                                    </span>
                                </div>
                                <span className="text-slate-400">{showPasswordSection ? '▲' : '▼'}</span>
                            </button>

                            {showPasswordSection && (
                                <div className="p-4 space-y-4">
                                    {user.must_change_password && (
                                        <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-700 text-xs">
                                            ⚠ This user is currently required to change their password on next login.
                                        </div>
                                    )}

                                    <div>
                                        <div className="flex items-center justify-between mb-1.5">
                                            <label className="text-sm font-semibold text-slate-600">New Password</label>
                                            <button
                                                type="button"
                                                onClick={handleGeneratePassword}
                                                className="px-3 py-1 bg-iec-pink-600 hover:bg-iec-pink-700 text-white text-xs rounded-lg"
                                            >
                                                🎲 Generate
                                            </button>
                                        </div>
                                        <div className="relative">
                                            <input
                                                type={showPassword ? 'text' : 'password'}
                                                value={data.new_password}
                                                onChange={(e) => setData('new_password', e.target.value)}
                                                className={`${inputClass} font-mono pr-20`}
                                                placeholder="Leave blank to keep current password"
                                                autoComplete="new-password"
                                            />
                                            <div className="absolute right-2 top-1/2 -translate-y-1/2 flex gap-1">
                                                <button type="button" onClick={() => setShowPassword(v => !v)}
                                                    className="px-2 py-1 text-slate-400 hover:text-slate-600 text-xs">
                                                    {showPassword ? '🙈' : '👁'}
                                                </button>
                                                {data.new_password && (
                                                    <button type="button" onClick={handleCopyPassword}
                                                        className="px-2 py-1 text-xs bg-slate-100 hover:bg-slate-200 rounded">
                                                        {copied ? '✓' : '📋'}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        {errors.new_password && <p className="text-rose-600 text-xs mt-1">{errors.new_password}</p>}
                                        <p className="text-slate-400 text-xs mt-1">Minimum 8 characters. Leave blank to keep current password unchanged.</p>
                                    </div>

                                    {/* Force change toggle */}
                                    <label className="flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.must_change_password}
                                            onChange={(e) => setData('must_change_password', e.target.checked)}
                                            className="mt-0.5 h-4 w-4 text-iec-pink-600 bg-white border-slate-200 rounded"
                                        />
                                        <div>
                                            <div className="text-amber-800 font-semibold text-xs">Force password change on next login</div>
                                            <div className="text-amber-600 text-xs mt-0.5">User will be redirected to change their password immediately after the next successful login.</div>
                                        </div>
                                    </label>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-3 pt-2">
                            <Button type="submit" disabled={processing}>{processing ? 'Updating...' : 'Update User'}</Button>
                            <Button href="/admin/users" variant="secondary">Cancel</Button>
                        </div>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}