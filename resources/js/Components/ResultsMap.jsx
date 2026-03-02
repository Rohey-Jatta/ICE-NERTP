import { useState, useEffect } from 'react';

/**
 * ResultsMap - Interactive polling station map.
 * 
 * From architecture: resources/js/Components/Map/LeafletMap.jsx
 * 
 * Shows all polling stations with certified results.
 * Click station to see detailed results + party acceptances.
 * 
 * Note: For production, replace with Leaflet/Mapbox implementation.
 * This is a simplified version for initial deployment.
 */
export default function ResultsMap({ election }) {
    const [stations, setStations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedStation, setSelectedStation] = useState(null);

    useEffect(() => {
        fetchMapData();
    }, [election]);

    const fetchMapData = async () => {
        try {
            const response = await fetch(`/api/public/results/${election.id}/map`);
            const data = await response.json();
            setStations(data.stations);
        } catch (error) {
            console.error('Failed to fetch map data:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleStationClick = async (station) => {
        try {
            const response = await fetch(`/api/public/results/${election.id}/station/${station.id}`);
            const data = await response.json();
            setSelectedStation(data.result);
        } catch (error) {
            console.error('Failed to fetch station details:', error);
        }
    };

    if (loading) {
        return (
            <div className="bg-white rounded-lg shadow-sm p-12 text-center">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-900 mx-auto"></div>
                <p className="mt-4 text-gray-600">Loading map data...</p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Station List */}
            <div className="lg:col-span-1 bg-white rounded-lg shadow-sm overflow-hidden">
                <div className="p-4 bg-gray-50 border-b border-gray-200">
                    <h3 className="font-semibold text-gray-900">Polling Stations</h3>
                    <p className="text-sm text-gray-600">{stations.length} stations with results</p>
                </div>
                <div className="overflow-y-auto" style={{ maxHeight: '600px' }}>
                    {stations.map((station) => (
                        <button
                            key={station.id}
                            onClick={() => handleStationClick(station)}
                            className="w-full text-left p-4 border-b border-gray-100 hover:bg-gray-50 transition-colors"
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <p className="font-medium text-gray-900">{station.name}</p>
                                    <p className="text-sm text-gray-500">{station.code}</p>
                                </div>
                                <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                                    station.certification_status === 'nationally_certified'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-blue-100 text-blue-800'
                                }`}>
                                    {station.turnout_percentage}%
                                </span>
                            </div>
                        </button>
                    ))}
                </div>
            </div>

            {/* Station Details */}
            <div className="lg:col-span-2 bg-white rounded-lg shadow-sm p-6">
                {selectedStation ? (
                    <>
                        <div className="mb-6">
                            <h2 className="text-xl font-bold text-gray-900">
                                {selectedStation.polling_station.name}
                            </h2>
                            <p className="text-sm text-gray-600">
                                Code: {selectedStation.polling_station.code}
                            </p>
                        </div>

                        {/* Vote Summary */}
                        <div className="grid grid-cols-3 gap-4 mb-6">
                            <div className="bg-gray-50 rounded-lg p-4">
                                <p className="text-sm text-gray-600">Registered</p>
                                <p className="text-xl font-bold text-gray-900">
                                    {selectedStation.total_registered_voters.toLocaleString()}
                                </p>
                            </div>
                            <div className="bg-gray-50 rounded-lg p-4">
                                <p className="text-sm text-gray-600">Votes Cast</p>
                                <p className="text-xl font-bold text-gray-900">
                                    {selectedStation.total_votes_cast.toLocaleString()}
                                </p>
                            </div>
                            <div className="bg-gray-50 rounded-lg p-4">
                                <p className="text-sm text-gray-600">Turnout</p>
                                <p className="text-xl font-bold text-gray-900">
                                    {((selectedStation.total_votes_cast / selectedStation.total_registered_voters) * 100).toFixed(1)}%
                                </p>
                            </div>
                        </div>

                        {/* Candidate Results */}
                        <div className="mb-6">
                            <h3 className="font-semibold text-gray-900 mb-3">Results by Candidate</h3>
                            <div className="space-y-3">
                                {selectedStation.candidate_votes.map((cv) => (
                                    <div key={cv.candidate_id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p className="font-medium text-gray-900">
                                                {cv.candidate.full_name}
                                            </p>
                                            <p className="text-sm text-gray-600">
                                                {cv.candidate.political_party?.name}
                                            </p>
                                        </div>
                                        <p className="text-lg font-bold text-blue-900">
                                            {cv.votes.toLocaleString()}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Party Acceptances */}
                        {selectedStation.party_acceptances?.length > 0 && (
                            <div>
                                <h3 className="font-semibold text-gray-900 mb-3">Party Representative Status</h3>
                                <div className="space-y-2">
                                    {selectedStation.party_acceptances.map((pa) => (
                                        <div key={pa.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <p className="text-sm text-gray-900">
                                                {pa.political_party.name}
                                            </p>
                                            <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                                pa.status === 'accepted'
                                                    ? 'bg-green-100 text-green-800'
                                                    : pa.status === 'rejected'
                                                    ? 'bg-red-100 text-red-800'
                                                    : 'bg-amber-100 text-amber-800'
                                            }`}>
                                                {pa.status === 'accepted' ? '✓ Accepted' : pa.status === 'rejected' ? '✗ Rejected' : '⚠ Reserved'}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="flex items-center justify-center h-full text-center py-12">
                        <div>
                            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p className="mt-4 text-gray-600">Select a polling station to view details</p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
