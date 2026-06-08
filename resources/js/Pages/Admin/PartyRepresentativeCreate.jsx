import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';

export default function PartyRepresentativeCreate({ auth, users, parties, pollingStations, hasElection = true }) {
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

    const canSubmit = !processing && data.user_id && data.political_party_id && selectedStations.length > 0;

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <Link href="/admin/party-representatives" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-block">
                            ← Back to Party Representatives
                        </Link>
                        <h1 className="text-3xl font-bold text-iec-navy">Add Party Representative</h1>
                    </div>
                </div>

                {/* No election warning */}
                {!hasElection && (
                    <div className="mb-5 p-4 bg-amber-50 border border-amber-300 rounded-xl flex items-start gap-3">
                        <span className="text-amber-500 text-xl flex-shrink-0">⚠</span>
                        <div>
                            <p className="text-amber-800 font-semibold">No elections found</p>
                            <p className="text-amber-700 text-sm mt-1">
                                Please{' '}
                                <Link href="/admin/elections/create" className="underline font-semibold">
                                    create an election
                                </Link>{' '}
                                first. Party representatives must be linked to an election record.
                            </p>
                        </div>
                    </div>
                )}

                {/* Generic server error */}
                {errors.error && (
                    <div className="mb-5 p-4 bg-red-50 border border-red-300 rounded-xl text-red-700">
                        {errors.error}
                    </div>
                )}

                <div className="bg-white rounded-xl p-6 border border-slate-200">
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Select User + Designation */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Select Party Representative User <span className="text-red-400">*</span>
                                </label>
                                <SearchableSelect
                                    value={String(data.user_id)}
                                    onChange={(val) => setData('user_id', val)}
                                    options={[{ value: '', label: '— Choose a user —' }, ...users.map((user) => ({ value: String(user.id), label: `${user.name} (${user.email})` }))]}
                                    placeholder="Select user"
                                    className="w-full"
                                    emptyLabel={users.length === 0 ? 'No users with party-representative role found' : 'No results found'}
                                    required
                                />
                                {errors.user_id && <p className="text-red-400 text-sm mt-1">{errors.user_id}</p>}
                                {users.length === 0 && (
                                    <p className="text-amber-600 text-xs mt-1">
                                        Go to{' '}
                                        <Link href="/admin/users/create" className="underline text-iec-pink-600">
                                            User Management
                                        </Link>{' '}
                                        and create a user with the <strong>party-representative</strong> role first.
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">Designation (Optional)</label>
                                <input
                                    type="text"
                                    value={data.designation}
                                    onChange={(e) => setData('designation', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                    placeholder="e.g., Chairman, Secretary, Agent"
                                />
                                {errors.designation && <p className="text-red-400 text-sm mt-1">{errors.designation}</p>}
                            </div>
                        </div>

                        {/* Political Party */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Political Party <span className="text-red-400">*</span>
                            </label>
                            {parties.length === 0 ? (
                                <div className="p-4 bg-amber-50 border border-amber-300 rounded-lg">
                                    <p className="text-amber-700 text-sm">
                                        No parties registered yet.{' '}
                                        <Link href="/admin/parties/create" className="text-iec-pink-600 underline">Register a party first</Link>.
                                    </p>
                                </div>
                            ) : (
                                <SearchableSelect
                                    value={String(data.political_party_id)}
                                    onChange={(val) => setData('political_party_id', val)}
                                    options={[{ value: '', label: '— Choose a party —' }, ...parties.map((party) => ({ value: String(party.id), label: party.name }))]}
                                    placeholder="Select party"
                                    className="w-full"
                                    required
                                />
                            )}
                            {errors.political_party_id && <p className="text-red-400 text-sm mt-1">{errors.political_party_id}</p>}
                        </div>

                        {/* Polling Station Assignment */}
                        <div>
                            <label className="block text-slate-600 mb-2 font-semibold">
                                Assign to Polling Stations <span className="text-red-400">*</span>
                                <span className="text-slate-500 font-normal text-xs ml-2">
                                    ({selectedStations.length} selected)
                                </span>
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
                                    <p className="text-amber-700 text-sm">No polling stations found. Please create polling stations first.</p>
                                    <Link href="/admin/polling-stations/create" className="text-iec-pink-600 underline text-sm mt-2 inline-block">
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
                                disabled={!canSubmit}
                                className="flex-1 px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-lg"
                            >
                                {processing ? 'Creating…' : 'Create Party Representative'}
                            </button>
                            <Link href="/admin/party-representatives" className="flex-1 px-6 py-3 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg text-center">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}