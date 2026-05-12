import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function PollingStationEdit({ auth, station, wards = [], officers = [], election }) {
    const { data, setData, put, processing, errors } = useForm({
        code:                station.code          || '',
        name:                station.name          || '',
        address:             station.address       || '',
        ward_id:             station.ward_id?.toString() || '',
        latitude:            station.latitude?.toString() || '',
        longitude:           station.longitude?.toString() || '',
        registered_voters:   station.registered_voters?.toString() || '',
        assigned_officer_id: station.assigned_officer_id?.toString() || '',
        is_active:           station.is_active     ?? true,
        is_test_station:     station.is_test_station ?? false,
    });

    const [locating, setLocating] = useState(false);

    const handleGPS = () => {
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setData(prev => ({
                    ...prev,
                    latitude:  pos.coords.latitude.toFixed(8),
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

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/admin/polling-stations/${station.id}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-4xl">
                <div className="mb-6">
                    <Link href="/admin/polling-stations" className="ws-page-back">
                        Back to Polling Stations
                    </Link>
                    <h1 className="ws-page-title">Edit Polling Station</h1>
                    {election && (
                        <p className="ws-page-desc">Election: {election.name}</p>
                    )}
                </div>

                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Code & Name */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Station Code <span className="text-red-400">*</span>
                                </label>
                                <input type="text" value={data.code}
                                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono"
                                    placeholder="e.g., BNJ-001" required />
                                {errors.code && <p className="text-red-400 text-sm mt-1">{errors.code}</p>}
                            </div>
                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Station Name <span className="text-red-400">*</span>
                                </label>
                                <input type="text" value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                    placeholder="e.g., Central Primary School" required />
                                {errors.name && <p className="text-red-400 text-sm mt-1">{errors.name}</p>}
                            </div>
                        </div>

                        {/* Address */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">Address</label>
                            <input type="text" value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                placeholder="Full station address" />
                        </div>

                        {/* Ward */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Ward <span className="text-red-400">*</span>
                            </label>
                            {wards.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">No wards found. Please configure the administrative hierarchy first.</p>
                                </div>
                            ) : (
                                <select value={data.ward_id}
                                    onChange={(e) => setData('ward_id', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                    required>
                                    <option value="">— Select a Ward —</option>
                                    {wards.map((ward) => (
                                        <option key={ward.id} value={ward.id}>{ward.name}</option>
                                    ))}
                                </select>
                            )}
                            {errors.ward_id && <p className="text-red-400 text-sm mt-1">{errors.ward_id}</p>}
                        </div>

                        {/* GPS Coordinates */}
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <label className="text-slate-600 font-semibold">
                                    GPS Coordinates <span className="text-red-400">*</span>
                                </label>
                                <button type="button" onClick={handleGPS} disabled={locating}
                                    className="px-4 py-2 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:bg-iec-pink-600/50 text-white text-sm rounded-lg">
                                    {locating ? '📍 Getting location…' : '📍 Use My GPS'}
                                </button>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-500 text-xs mb-1">Latitude</label>
                                    <input type="number" step="0.00000001" value={data.latitude}
                                        onChange={(e) => setData('latitude', e.target.value)}
                                        className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono"
                                        placeholder="13.4549" required />
                                    {errors.latitude && <p className="text-red-400 text-sm mt-1">{errors.latitude}</p>}
                                </div>
                                <div>
                                    <label className="block text-slate-500 text-xs mb-1">Longitude</label>
                                    <input type="number" step="0.00000001" value={data.longitude}
                                        onChange={(e) => setData('longitude', e.target.value)}
                                        className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy font-mono"
                                        placeholder="-16.5790" required />
                                    {errors.longitude && <p className="text-red-400 text-sm mt-1">{errors.longitude}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Registered Voters */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Registered Voters <span className="text-red-400">*</span>
                            </label>
                            <input type="number" min="0" value={data.registered_voters}
                                onChange={(e) => setData('registered_voters', e.target.value)}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                placeholder="0" required />
                            {errors.registered_voters && <p className="text-red-400 text-sm mt-1">{errors.registered_voters}</p>}
                        </div>

                        {/* Assigned Officer */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">Assigned Polling Officer</label>
                            <select value={data.assigned_officer_id}
                                onChange={(e) => setData('assigned_officer_id', e.target.value)}
                                className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy">
                                <option value="">— No officer assigned —</option>
                                {officers.map((officer) => (
                                    <option key={officer.id} value={officer.id}>
                                        {officer.name} ({officer.email})
                                    </option>
                                ))}
                            </select>
                            {errors.assigned_officer_id && <p className="text-red-400 text-sm mt-1">{errors.assigned_officer_id}</p>}
                        </div>

                        {/* Flags */}
                        <div className="grid grid-cols-2 gap-4">
                            <label className="flex items-center gap-3 p-4 bg-slate-50 rounded-lg cursor-pointer">
                                <input type="checkbox" checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="h-4 w-4 text-iec-pink-600 bg-white border-slate-200 rounded" />
                                <div>
                                    <div className="text-iec-navy font-medium">Active Station</div>
                                    <div className="text-slate-500 text-xs">Station is open for voting</div>
                                </div>
                            </label>
                            <label className="flex items-center gap-3 p-4 bg-slate-50 rounded-lg cursor-pointer">
                                <input type="checkbox" checked={data.is_test_station}
                                    onChange={(e) => setData('is_test_station', e.target.checked)}
                                    className="h-4 w-4 text-amber-600 bg-white border-slate-200 rounded" />
                                <div>
                                    <div className="text-amber-300 font-medium">Test Station</div>
                                    <div className="text-slate-500 text-xs">Results excluded from aggregation</div>
                                </div>
                            </label>
                        </div>

                        {/* Submit */}
                        <div className="flex gap-4">
                            <button type="submit" disabled={processing}
                                className="flex-1 px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white font-bold rounded-lg">
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                            <Link href="/admin/polling-stations"
                                className="flex-1 px-6 py-3 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg text-center">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
