import { Button, Field, PageHeader, Panel, inputClass, roleLabel } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function UserEdit({ auth, user, roles, pollingStations, wards, constituencies, adminAreas }) {
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

    const handleSubmit = (event) => {
        event.preventDefault();
        put(`/admin/users/${user.id}`);
    };

    const renderRoleSpecificFields = () => {
        if (selectedRole === 'polling-officer') {
            return (
                <Field label="Assign to Polling Station">
                    <select value={data.polling_station_id} onChange={(event) => setData('polling_station_id', event.target.value)} className={inputClass}>
                        <option value="">Select polling station</option>
                        {pollingStations.map((station) => (
                            <option key={station.id} value={station.id}>{station.code} - {station.name}</option>
                        ))}
                    </select>
                </Field>
            );
        }

        if (selectedRole === 'ward-approver') {
            return (
                <Field label="Assign to Ward">
                    <select value={data.ward_id} onChange={(event) => setData('ward_id', event.target.value)} className={inputClass}>
                        <option value="">Select ward</option>
                        {wards.map((ward) => <option key={ward.id} value={ward.id}>{ward.name}</option>)}
                    </select>
                </Field>
            );
        }

        if (selectedRole === 'constituency-approver') {
            return (
                <Field label="Assign to Constituency">
                    <select value={data.constituency_id} onChange={(event) => setData('constituency_id', event.target.value)} className={inputClass}>
                        <option value="">Select constituency</option>
                        {constituencies.map((constituency) => <option key={constituency.id} value={constituency.id}>{constituency.name}</option>)}
                    </select>
                </Field>
            );
        }

        if (selectedRole === 'admin-area-approver') {
            return (
                <Field label="Assign to Administrative Area">
                    <select value={data.admin_area_id} onChange={(event) => setData('admin_area_id', event.target.value)} className={inputClass}>
                        <option value="">Select administrative area</option>
                        {adminAreas.map((area) => <option key={area.id} value={area.id}>{area.name}</option>)}
                    </select>
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
                                <select value={data.status} onChange={(event) => setData('status', event.target.value)} className={inputClass}>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                                {errors.status && <p className="mt-1 text-sm text-rose-600">{errors.status}</p>}
                            </Field>

                            <Field label="Role">
                                <select value={data.role} onChange={(event) => handleRoleChange(event.target.value)} className={inputClass}>
                                    {roles.map((role) => <option key={role} value={role}>{roleLabel(role)}</option>)}
                                </select>
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
            </div>
        </AppLayout>
    );
}
