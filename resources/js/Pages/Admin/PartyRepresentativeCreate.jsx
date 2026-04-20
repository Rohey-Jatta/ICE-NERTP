import AppLayout from '@/Layouts/AppLayout';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';

export default function PartyRepresentativeCreate({ auth, users, parties, pollingStations }) {
    const { data, setData, post, processing, errors } = useForm({
        user_id: '',
        political_party_id: '',
        polling_station_ids: [],
        designation: '',
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
        post('/admin/party-representatives');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-3xl font-bold text-white">Add Party Representative</h1>
                    <button
                        onClick={() => router.visit('/admin/party-representatives')}
                        className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg"
                    >
                        Back to Representatives
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
                                    {users.filter(user => user.role === 'party_representative').map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name} ({user.email})
                                        </option>
                                    ))}
                                </select>
                                {errors.user_id && <p className="text-red-400 text-sm mt-1">{errors.user_id}</p>}
                            </div>

                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Political Party</label>
                                <select
                                    value={data.political_party_id}
                                    onChange={(e) => setData('political_party_id', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    required
                                >
                                    <option value="">Choose a party</option>
                                    {parties.map((party) => (
                                        <option key={party.id} value={party.id}>
                                            {party.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.political_party_id && <p className="text-red-400 text-sm mt-1">{errors.political_party_id}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">Designation (Optional)</label>
                            <input
                                type="text"
                                value={data.designation}
                                onChange={(e) => setData('designation', e.target.value)}
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="e.g., Chairman, Secretary"
                            />
                            {errors.designation && <p className="text-red-400 text-sm mt-1">{errors.designation}</p>}
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
                                {processing ? 'Creating...' : 'Create Party Representative'}
                            </button>
                            <button
                                type="button"
                                onClick={() => router.visit('/admin/party-representatives')}
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
