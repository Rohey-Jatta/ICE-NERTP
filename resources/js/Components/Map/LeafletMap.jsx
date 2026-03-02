import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect } from 'react';
import L from 'leaflet';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

// NEUTRAL MARKER COLORS (NO POLITICAL PARTY COLORS)
const createCustomIcon = (status) => {
    const colors = {
        'nationally_certified': '#14b8a6', // teal (neutral)
        'admin_area_certified': '#64748b', // slate
        'constituency_certified': '#64748b', // slate
        'ward_certified': '#64748b', // slate
        'submitted': '#d97706', // amber (warning)
        'not_reported': '#475569', // dark slate
    };

    const color = colors[status] || '#475569';

    return L.divIcon({
        className: 'custom-marker',
        html: `
            <div style="
                background-color: ${color};
                width: 30px;
                height: 30px;
                border-radius: 50%;
                border: 3px solid white;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="width: 12px; height: 12px; background: white; border-radius: 50%;"></div>
            </div>
        `,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -15],
    });
};

function MapBounds({ stations }) {
    const map = useMap();
    useEffect(() => {
        if (stations.length > 0) {
            const bounds = stations.filter(s => s.latitude && s.longitude).map(s => [s.latitude, s.longitude]);
            if (bounds.length > 0) map.fitBounds(bounds, { padding: [50, 50] });
        }
    }, [stations, map]);
    return null;
}

export default function LeafletMap({ stations }) {
    const validStations = stations.filter(s => s.latitude && s.longitude);
    const center = [13.4549, -16.5790];

    if (validStations.length === 0) {
        return (
            <div className="bg-slate-800/40 rounded-xl p-8 text-center border border-slate-700/50">
                <div className="text-4xl mb-4">Ì∑∫Ô∏è</div>
                <p className="text-white">No stations with coordinates</p>
            </div>
        );
    }

    return (
        <div className="bg-slate-800/40 rounded-xl overflow-hidden border border-slate-700/50 shadow-2xl">
            <MapContainer center={center} zoom={10} style={{ height: '600px', width: '100%' }} scrollWheelZoom={true}>
                <TileLayer
                    attribution='&copy; OpenStreetMap'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <MapBounds stations={validStations} />
                {validStations.map((station) => (
                    <Marker key={station.id} position={[station.latitude, station.longitude]} icon={createCustomIcon(station.result_status)}>
                        <Popup>
                            <div className="p-2 min-w-[200px]">
                                <h3 className="font-bold text-base mb-2">{station.name}</h3>
                                <div className="text-sm space-y-1">
                                    <div>Code: {station.code}</div>
                                    <div>Voters: {station.registered_voters}</div>
                                    {station.result && (
                                        <div className="border-t pt-2 mt-2">
                                            <div>Valid: {station.result.valid_votes}</div>
                                            <div>Rejected: {station.result.rejected_votes}</div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </Popup>
                    </Marker>
                ))}
            </MapContainer>
            
            <div className="bg-slate-900/50 p-4 border-t border-slate-700/30">
                <div className="flex flex-wrap gap-4 justify-center text-xs sm:text-sm text-white">
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-4 rounded-full bg-teal-500 border-2 border-white"></div>
                        <span>Certified</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-4 rounded-full bg-slate-500 border-2 border-white"></div>
                        <span>In Review</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-4 rounded-full bg-amber-600 border-2 border-white"></div>
                        <span>Submitted</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-4 rounded-full bg-slate-600 border-2 border-white"></div>
                        <span>Not Reported</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
