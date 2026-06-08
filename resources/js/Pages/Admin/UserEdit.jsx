import { Button, Field, PageHeader, Panel, inputClass, roleLabel } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

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
