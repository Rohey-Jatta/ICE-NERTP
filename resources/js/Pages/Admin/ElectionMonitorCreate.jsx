import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ElectionMonitorCreate({ auth, users, pollingStations }) {
    const { data, setData, post, processing, errors } = useForm({
        user_id: '',
        organization: '',
        type: 'domestic',
        polling_station_ids: [],
    });

    const [selectedStations, setSelectedStations] = useState([]);

    const handleStationToggle = (stationId) => {
        const newSelected = selectedStations.includes(stationId)
            ? selectedStations.filter(id => id !== stationId)
            : [...selectedStations, stationId];

        setSelectedStations(newSelected);
        setData('polling_station_ids', newSelected);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/election-monitors');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-3xl font-bold text-white">Add Election Monitor</h1>
                    <button
                        onClick={() => router.visit('/admin/election-monitors')}
                        className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg"
                    >
                        Back to Monitors
                    </button>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Select User</label>
                                <select
                                    value={data.user_id}
                                    onChange={(e) => setData('user_id', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    required
                                >
                                    <option value="">Choose a user</option>
                                    {users.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name} ({user.email})
                                        </option>
                                    ))}
                                </select>
                                {errors.user_id && <p className="text-red-400 text-sm mt-1">{errors.user_id}</p>}
                            </div>

                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Organization (Optional)</label>
                                <input
                                    type="text"
                                    value={data.organization}
                                    onChange={(e) => setData('organization', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder="e.g., Civil Society Organization"
                                />
                                {errors.organization && <p className="text-red-400 text-sm mt-1">{errors.organization}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Monitor Type</label>
                            <select
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                            >
                                <option value="domestic">Domestic</option>
                                <option value="international">International</option>
                                <option value="civil_society">Civil Society</option>
                            </select>
                            {errors.type && <p className="text-red-400 text-sm mt-1">{errors.type}</p>}
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-4 font-semibold">Assign to Polling Stations</label>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto">
                                {pollingStations.map((station) => (
                                    <label key={station.id} className="flex items-center space-x-3 p-3 bg-slate-900/30 rounded-lg hover:bg-slate-900/50 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={selectedStations.includes(station.id)}
                                            onChange={() => handleStationToggle(station.id)}
                                            className="form-checkbox h-4 w-4 text-teal-600 bg-slate-900 border-slate-600 rounded"
                                        />
                                        <div>
                                            <div className="text-white font-medium">{station.code}</div>
                                            <div className="text-gray-400 text-sm">{station.name}</div>
                                        </div>
                                    </label>
                                ))}
                            </div>
                            {errors.polling_station_ids && <p className="text-red-400 text-sm mt-1">{errors.polling_station_ids}</p>}
                            {selectedStations.length === 0 && (
                                <p className="text-yellow-400 text-sm mt-2">Please select at least one polling station</p>
                            )}
                        </div>

                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing || selectedStations.length === 0}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600 text-white font-bold rounded-lg"
                            >
                                {processing ? 'Creating...' : 'Create Election Monitor'}
                            </button>
                            <button
                                type="button"
                                onClick={() => router.visit('/admin/election-monitors')}
                                className="flex-1 px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
