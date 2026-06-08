import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

export default function ElectionMonitorEdit({ auth, monitor, pollingStations }) {
    const assignedIds = monitor.polling_stations?.map(s => s.id) || [];

    const { data, setData, put, processing, errors } = useForm({
        organization:        monitor.organization || '',
        type:                monitor.type || 'domestic',
        is_active:           monitor.is_active ?? true,
        polling_station_ids: assignedIds,
    });

    const [selectedStations, setSelectedStations] = useState(assignedIds);
    const [stationSearch, setStationSearch] = useState('');

    const handleStationToggle = (stationId) => {
        const newSelected = selectedStations.includes(stationId)
            ? selectedStations.filter(id => id !== stationId)
            : [...selectedStations, stationId];
        setSelectedStations(newSelected);
        setData('polling_station_ids', newSelected);
    };

    const filteredStations = pollingStations.filter(s =>
        s.name.toLowerCase().includes(stationSearch.toLowerCase()) ||
        s.code.toLowerCase().includes(stationSearch.toLowerCase())
    );

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/admin/election-monitors/${monitor.id}`);
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <Link href="/admin/election-monitors" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-block">
                            ← Back to Election Monitors
                        </Link>
                        <h1 className="text-3xl font-bold text-iec-navy">Edit Election Monitor</h1>
                        <p className="text-slate-500 mt-1">
                            {monitor.user?.name} — {monitor.user?.email}
                        </p>
                    </div>
                </div>

                {errors.error && (
                    <div className="mb-5 p-4 bg-red-50 border border-red-300 rounded-xl text-red-700">
                        {errors.error}
                    </div>
                )}

                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Organization + Type */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">Organization (Optional)</label>
                                <input
                                    type="text"
                                    value={data.organization}
                                    onChange={(e) => setData('organization', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                    placeholder="e.g., Civil Society Organization"
                                />
                                {errors.organization && <p className="text-red-400 text-sm mt-1">{errors.organization}</p>}
                            </div>

                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">Monitor Type</label>
                                <SearchableSelect
                                    value={data.type}
                                    onChange={(val) => setData('type', val)}
                                    options={[
                                        { value: 'domestic', label: 'Domestic' },
                                        { value: 'international', label: 'International' },
                                        { value: 'civil_society', label: 'Civil Society' }
                                    ]}
                                    placeholder="Select monitor type"
                                    className="w-full"
                                />
                                {errors.type && <p className="text-red-400 text-sm mt-1">{errors.type}</p>}
                            </div>
                        </div>

                        {/* Active Status */}
                        <div>
                            <label className="flex items-center gap-3 p-4 bg-slate-50 rounded-lg cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="h-4 w-4 text-iec-pink-600 bg-white border-slate-200 rounded"
                                />
                                <div>
                                    <div className="text-iec-navy font-medium">Active</div>
                                    <div className="text-slate-500 text-xs">Monitor can log in and access their stations</div>
                                </div>
                            </label>
                        </div>

                        {/* Polling Station Assignment */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Assigned Polling Stations
                                <span className="text-slate-500 font-normal text-xs ml-2">({selectedStations.length} selected)</span>
                            </label>

                            <input
                                type="text"
                                placeholder="Search stations by name or code..."
                                value={stationSearch}
                                onChange={(e) => setStationSearch(e.target.value)}
                                className="w-full px-4 py-2 mb-3 bg-white border border-slate-200 rounded-lg text-iec-navy text-sm"
                            />

                            {pollingStations.length === 0 ? (
                                <div className="p-6 bg-amber-50 border border-amber-300 rounded-lg text-center">
                                    <p className="text-amber-700 text-sm">No polling stations found.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-72 overflow-y-auto p-1">
                                    {filteredStations.map((station) => (
                                        <label
                                            key={station.id}
                                            className={`flex items-center space-x-3 p-3 rounded-lg cursor-pointer border transition-colors ${
                                                selectedStations.includes(station.id)
                                                    ? 'bg-teal-900/30 border-teal-500/50'
                                                    : 'bg-slate-50 border-slate-200 hover:bg-white'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedStations.includes(station.id)}
                                                onChange={() => handleStationToggle(station.id)}
                                                className="h-4 w-4 text-iec-pink-600 bg-white border-slate-200 rounded"
                                            />
                                            <div>
                                                <div className="text-iec-navy font-medium text-sm">{station.code}</div>
                                                <div className="text-slate-500 text-xs">{station.name}</div>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            )}

                            {errors.polling_station_ids && (
                                <p className="text-red-400 text-sm mt-1">{errors.polling_station_ids}</p>
                            )}
                        </div>

                        {/* Actions */}
                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing || selectedStations.length === 0}
                                className="flex-1 px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-lg"
                            >
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                            <Link
                                href="/admin/election-monitors"
                                className="flex-1 px-6 py-3 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg text-center"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}