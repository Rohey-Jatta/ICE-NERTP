import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function PartyRepresentativeCreate({ auth, users, parties, pollingStations }) {
    const { data, setData, post, processing, errors } = useForm({
        user_id: '',
        political_party_id: '',
        polling_station_ids: [],
        designation: '',
    });

    const [selectedStations, setSelectedStations] = useState([]);
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
        post('/admin/party-representatives');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <Link href="/admin/party-representatives" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                            ← Back to Party Representatives
                        </Link>
                        <h1 className="text-3xl font-bold text-white">Add Party Representative</h1>
                    </div>
                </div>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Select User + Designation */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Select Party Representative User
                                    {/* <span className="text-gray-500 font-normal text-xs ml-2">(users with party-representative role)</span> */}
                                </label>
                                <select
                                    value={data.user_id}
                                    onChange={(e) => setData('user_id', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    required
                                >
                                    <option value="">— Choose a user —</option>
                                    {users.length > 0 ? (
                                        users.map((user) => (
                                            <option key={user.id} value={user.id}>
                                                {user.name} ({user.email})
                                            </option>
                                        ))
                                    ) : (
                                        <option disabled>No available users — create users with party-representative role first</option>
                                    )}
                                </select>
                                {/* {errors.user_id && <p className="text-red-400 text-sm mt-1">{errors.user_id}</p>}
                                <p className="text-gray-500 text-xs mt-1">
                                    To add users here, go to{' '}
                                    <Link href="/admin/users/create" className="text-teal-400 underline">User Management</Link>{' '}
                                    and assign the <strong className="text-gray-300">party-representative</strong> role.
                                </p> */}
                            </div>

                            <div>
                                <label className="block text-gray-300 mb-2 font-semibold">Designation (Optional)</label>
                                <input
                                    type="text"
                                    value={data.designation}
                                    onChange={(e) => setData('designation', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder="e.g., Chairman, Secretary, Agent"
                                />
                                {errors.designation && <p className="text-red-400 text-sm mt-1">{errors.designation}</p>}
                            </div>
                        </div>

                        {/* Political Party */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Political Party <span className="text-red-400">*</span>
                            </label>
                            {parties.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">
                                        No parties registered yet.{' '}
                                        <Link href="/admin/parties/create" className="text-teal-400 underline">Register a party first</Link>.
                                    </p>
                                </div>
                            ) : (
                                <select
                                    value={data.political_party_id}
                                    onChange={(e) => setData('political_party_id', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    required
                                >
                                    <option value="">— Choose a party —</option>
                                    {parties.map((party) => (
                                        <option key={party.id} value={party.id}>
                                            {party.name}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {errors.political_party_id && <p className="text-red-400 text-sm mt-1">{errors.political_party_id}</p>}
                        </div>

                        {/* Polling Station Assignment */}
                        <div>
                            <label className="block text-gray-300 mb-2 font-semibold">
                                Assign to Polling Stations
                                <span className="text-gray-500 font-normal text-xs ml-2">
                                    ({selectedStations.length} selected)
                                </span>
                            </label>

                            {/* Search */}
                            <input
                                type="text"
                                placeholder="Search stations by name or code..."
                                value={stationSearch}
                                onChange={(e) => setStationSearch(e.target.value)}
                                className="w-full px-4 py-2 mb-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white text-sm"
                            />

                            {pollingStations.length === 0 ? (
                                <div className="p-6 bg-amber-500/10 border border-amber-500/30 rounded-lg text-center">
                                    <p className="text-amber-300 text-sm">No polling stations found. Please create polling stations first.</p>
                                    <Link href="/admin/polling-stations/create" className="text-teal-400 underline text-sm mt-2 inline-block">
                                        Create Polling Station →
                                    </Link>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-72 overflow-y-auto p-1">
                                    {filteredStations.map((station) => (
                                        <label
                                            key={station.id}
                                            className={`flex items-center space-x-3 p-3 rounded-lg cursor-pointer border transition-colors ${
                                                selectedStations.includes(station.id)
                                                    ? 'bg-teal-900/30 border-teal-500/50'
                                                    : 'bg-slate-900/30 border-slate-700/30 hover:bg-slate-900/50'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedStations.includes(station.id)}
                                                onChange={() => handleStationToggle(station.id)}
                                                className="h-4 w-4 text-teal-600 bg-slate-900 border-slate-600 rounded"
                                            />
                                            <div>
                                                <div className="text-white font-medium text-sm">{station.code}</div>
                                                <div className="text-gray-400 text-xs">{station.name}</div>
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
                                disabled={processing || selectedStations.length === 0 || !data.user_id || !data.political_party_id}
                                className="flex-1 px-6 py-3 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-600/50 disabled:cursor-not-allowed text-white font-bold rounded-lg"
                            >
                                {processing ? 'Creating…' : 'Create Party Representative'}
                            </button>
                            <Link href="/admin/party-representatives" className="flex-1 px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg text-center">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
