import { Button, Field, PageHeader, Panel, inputClass, roleLabel } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

export default function UserEdit({ auth, user, roles, pollingStations, wards, constituencies, adminAreas, devices = [], requiresDeviceBinding = false }) {
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
    const [resettingDevice, setResettingDevice] = useState(false);
    const [revokingDeviceId, setRevokingDeviceId] = useState(null);

    const handleRoleChange = (role) => {
        setSelectedRole(role);
        setData('role', role);
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        put(`/admin/users/${user.id}`);
    };

    const handleResetAllDevices = () => {
        if (!window.confirm(`Reset device binding for "${user.name}"? They will be able to register a new device on next login.`)) return;
        setResettingDevice(true);
        router.post(`/admin/users/${user.id}/devices/reset`, {}, {
            preserveScroll: true,
            onFinish: () => setResettingDevice(false),
        });
    };

    const handleRevokeDevice = (deviceId) => {
        if (!window.confirm('Revoke this device? The user will not be able to use it anymore.')) return;
        setRevokingDeviceId(deviceId);
        router.delete(`/admin/devices/${deviceId}`, {
            preserveScroll: true,
            onFinish: () => setRevokingDeviceId(null),
        });
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

    const activeDevices = devices.filter(d => !d.is_revoked);
    const revokedDevices = devices.filter(d => d.is_revoked);

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-3xl">
                <PageHeader
                    title={`Edit User: ${user.name}`}
                    description="Update account details, role assignment, and operational status."
                    backHref="/admin/users"
                    backLabel="Back to Users"
                />

                <Panel className="p-5 mb-5">
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

                        <div className="flex flex-wrap gap-3 pt-2">
                            <Button type="submit" disabled={processing}>{processing ? 'Updating...' : 'Update User'}</Button>
                            <Button href="/admin/users" variant="secondary">Cancel</Button>
                        </div>
                    </form>
                </Panel>

                {/* ── Device Management Panel ──────────────────────────────── */}
                {requiresDeviceBinding && (
                    <Panel className="p-5">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h2 className="ws-section-title">Device Binding</h2>
                                <p className="ws-section-desc mt-1">
                                    This role requires single-device binding. The user can only log in from their registered device.
                                </p>
                            </div>
                            {activeDevices.length > 0 && (
                                <Button
                                    variant="danger"
                                    onClick={handleResetAllDevices}
                                    disabled={resettingDevice}
                                >
                                    {resettingDevice ? 'Resetting…' : 'Reset Device Binding'}
                                </Button>
                            )}
                        </div>

                        {activeDevices.length === 0 && revokedDevices.length === 0 && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                No device registered yet. The device will be automatically bound on first login after OTP verification.
                            </div>
                        )}

                        {activeDevices.length > 0 && (
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Active Device</p>
                                <div className="space-y-2">
                                    {activeDevices.map((device) => (
                                        <div key={device.id} className="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-800">{device.device_name}</p>
                                                <p className="text-xs text-slate-500 mt-0.5">
                                                    {device.os} · {device.browser} · {device.device_type}
                                                </p>
                                                <p className="text-xs text-slate-400 mt-0.5">
                                                    Registered: {device.verified_at} · Last used: {device.last_used_at || 'Never'} · IP: {device.last_used_ip || '—'}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2 ml-4">
                                                <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                    ✓ Active
                                                </span>
                                                <Button
                                                    variant="danger"
                                                    onClick={() => handleRevokeDevice(device.id)}
                                                    disabled={revokingDeviceId === device.id}
                                                >
                                                    {revokingDeviceId === device.id ? 'Revoking…' : 'Revoke'}
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {revokedDevices.length > 0 && (
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Revoked Devices</p>
                                <div className="space-y-2">
                                    {revokedDevices.map((device) => (
                                        <div key={device.id} className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3 opacity-60">
                                            <div>
                                                <p className="text-sm font-medium text-slate-600">{device.device_name}</p>
                                                <p className="text-xs text-slate-400">{device.os} · {device.browser}</p>
                                            </div>
                                            <span className="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                                Revoked
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="mt-4 rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs text-blue-600">
                            <strong>How it works:</strong> On first login after OTP verification, the device is automatically bound to this account. 
                            If the user tries to log in from a different device, they will be blocked and asked to contact the administrator. 
                            Use "Reset Device Binding" to allow the user to register a new device.
                        </div>
                    </Panel>
                )}
            </div>
        </AppLayout>
    );
}