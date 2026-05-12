import { Button, Field, PageHeader, Panel, inputClass } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function PollingStationCreate({ auth, wards = [], officers = [], election, hasActiveElection = true }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        address: '',
        ward_id: '',
        latitude: '',
        longitude: '',
        registered_voters: '',
        assigned_officer_id: '',
        is_active: true,
        is_test_station: false,
    });

    const [locating, setLocating] = useState(false);

    const handleGPS = () => {
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setData((current) => ({
                    ...current,
                    latitude: pos.coords.latitude.toFixed(8),
                    longitude: pos.coords.longitude.toFixed(8),
                }));
                setLocating(false);
            },
            () => {
                alert('Could not get GPS location. Please enter coordinates manually.');
                setLocating(false);
            },
            { enableHighAccuracy: true }
        );
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        if (!hasActiveElection) return;
        post('/admin/polling-stations');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-4xl">
                <PageHeader
                    title="Register New Polling Station"
                    description={election ? `Election: ${election.name}` : 'Create a polling station for the active election.'}
                    backHref="/admin/polling-stations"
                    backLabel="Back to Polling Stations"
                />

                {errors.error && (
                    <div className="ws-alert ws-alert-error">{errors.error}</div>
                )}

                {!hasActiveElection && (
                    <div className="ws-alert ws-alert-warning">
                        <span>
                            No active election found. Create and activate an election before registering polling stations.{' '}
                            <Link href="/admin/elections/create" className="font-semibold underline">Create an election</Link>
                        </span>
                    </div>
                )}

                <Panel className="p-5">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="ws-form-grid">
                            <Field label="Station Code">
                                <input
                                    type="text"
                                    value={data.code}
                                    onChange={(event) => setData('code', event.target.value.toUpperCase())}
                                    className={`${inputClass} font-mono`}
                                    placeholder="e.g., BNJ-001"
                                    required
                                />
                                {errors.code && <p className="mt-1 text-sm text-rose-600">{errors.code}</p>}
                            </Field>
                            <Field label="Station Name">
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    className={inputClass}
                                    placeholder="e.g., Central Primary School"
                                    required
                                />
                                {errors.name && <p className="mt-1 text-sm text-rose-600">{errors.name}</p>}
                            </Field>
                        </div>

                        <Field label="Address">
                            <input type="text" value={data.address} onChange={(event) => setData('address', event.target.value)} className={inputClass} placeholder="Full station address" />
                        </Field>

                        <Field label="Ward">
                            {wards.length === 0 ? (
                                <div className="ws-alert ws-alert-warning mb-0">No wards found. Configure the administrative hierarchy first.</div>
                            ) : (
                                <select value={data.ward_id} onChange={(event) => setData('ward_id', event.target.value)} className={inputClass} required>
                                    <option value="">Select a ward</option>
                                    {wards.map((ward) => <option key={ward.id} value={ward.id}>{ward.name}</option>)}
                                </select>
                            )}
                            {errors.ward_id && <p className="mt-1 text-sm text-rose-600">{errors.ward_id}</p>}
                        </Field>

                        <div>
                            <div className="mb-2 flex items-center justify-between gap-3">
                                <span className="ws-label mb-0">GPS Coordinates</span>
                                <Button type="button" onClick={handleGPS} disabled={locating} variant="secondary">
                                    {locating ? 'Getting Location' : 'Use My GPS'}
                                </Button>
                            </div>
                            <div className="ws-form-grid">
                                <Field label="Latitude">
                                    <input type="number" step="0.00000001" value={data.latitude} onChange={(event) => setData('latitude', event.target.value)} className={`${inputClass} font-mono`} placeholder="13.4549" required />
                                    {errors.latitude && <p className="mt-1 text-sm text-rose-600">{errors.latitude}</p>}
                                </Field>
                                <Field label="Longitude">
                                    <input type="number" step="0.00000001" value={data.longitude} onChange={(event) => setData('longitude', event.target.value)} className={`${inputClass} font-mono`} placeholder="-16.5790" required />
                                    {errors.longitude && <p className="mt-1 text-sm text-rose-600">{errors.longitude}</p>}
                                </Field>
                            </div>
                            <p className="mt-1 text-xs text-slate-500">Used for GPS validation when officers submit results.</p>
                        </div>

                        <div className="ws-form-grid">
                            <Field label="Registered Voters">
                                <input type="number" min="0" value={data.registered_voters} onChange={(event) => setData('registered_voters', event.target.value)} className={inputClass} placeholder="0" required />
                                {errors.registered_voters && <p className="mt-1 text-sm text-rose-600">{errors.registered_voters}</p>}
                            </Field>
                            <Field label="Assigned Polling Officer">
                                <select value={data.assigned_officer_id} onChange={(event) => setData('assigned_officer_id', event.target.value)} className={inputClass}>
                                    <option value="">No officer assigned yet</option>
                                    {officers.map((officer) => <option key={officer.id} value={officer.id}>{officer.name} ({officer.email})</option>)}
                                </select>
                                {officers.length === 0 && (
                                    <p className="mt-1 text-xs text-slate-500">
                                        No polling officers found. <Link href="/admin/users/create" className="text-iec-pink underline">Create a polling officer</Link> first.
                                    </p>
                                )}
                                {errors.assigned_officer_id && <p className="mt-1 text-sm text-rose-600">{errors.assigned_officer_id}</p>}
                            </Field>
                        </div>

                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label className="ws-toggle-card">
                                <span>
                                    <span className="block font-semibold text-slate-900">Active Station</span>
                                    <span className="mt-1 block text-sm text-slate-500">Station is open for voting.</span>
                                </span>
                                <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
                            </label>
                            <label className="ws-toggle-card">
                                <span>
                                    <span className="block font-semibold text-slate-900">Test Station</span>
                                    <span className="mt-1 block text-sm text-slate-500">Results excluded from aggregation.</span>
                                </span>
                                <input type="checkbox" checked={data.is_test_station} onChange={(event) => setData('is_test_station', event.target.checked)} />
                            </label>
                        </div>

                        <div className="flex flex-wrap gap-3 pt-2">
                            <Button type="submit" disabled={processing || !hasActiveElection}>
                                {processing ? 'Registering...' : !hasActiveElection ? 'No Active Election' : 'Register Polling Station'}
                            </Button>
                            <Button href="/admin/polling-stations" variant="secondary">Cancel</Button>
                        </div>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
