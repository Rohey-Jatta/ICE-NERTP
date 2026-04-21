import { useEffect, useRef } from 'react';
export default function LeafletMap({ stations = [] }) {
    const mapRef = useRef(null);
    const mapInstanceRef = useRef(null);

    useEffect(() => {
        if (mapInstanceRef.current) return; // Already initialized

        // Dynamically import Leaflet to avoid SSR issues
        import('leaflet').then((L) => {
            // Fix default icon paths (Leaflet webpack issue)
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                iconUrl:       'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                shadowUrl:     'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            });

            // Default center: Banjul, The Gambia
            const defaultCenter = [13.4549, -16.5790];
            const defaultZoom   = 13;

            // Create map with attribution control COMPLETELY removed
            const map = L.map(mapRef.current, {
                center:            defaultCenter,
                zoom:              defaultZoom,
                attributionControl: false,  // ← removes the Leaflet | © OpenStreetMap tag
                zoomControl:       true,
                maxZoom:           22,       // ← deep zoom enabled
                preferCanvas:      true,
            });

            // Use a tile provider that supports zoom 19-22 seamlessly
            // CartoDB Voyager — clean, detailed, no branding overlay, supports z=22
            L.tileLayer(
                'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                {
                    maxZoom:     22,
                    maxNativeZoom: 19, // tiles exist up to 19, Leaflet upscales 20-22
                    subdomains:  'abcd',
                    // No attribution string — control is off
                }
            ).addTo(map);

            // ── Custom coloured circle markers per status ──────────────────
            const statusColors = {
                nationally_certified:    '#10b981', // green
                admin_area_certified:    '#3b82f6', // blue
                constituency_certified:  '#6366f1', // indigo
                ward_certified:          '#8b5cf6', // violet
                pending_national:        '#f59e0b', // amber
                pending_admin_area:      '#f59e0b',
                pending_constituency:    '#f59e0b',
                pending_ward:            '#f59e0b',
                submitted:               '#f97316', // orange
                not_reported:            '#ff6ed4', // pink
            };

            const statusLabel = {
                nationally_certified:   'Certified',
                admin_area_certified:   'Admin Area Certified',
                constituency_certified: 'Constituency Certified',
                ward_certified:         'Ward Certified',
                submitted:              'Submitted',
                not_reported:           'Not Reported',
            };

            // Add station markers
            stations.forEach((station) => {
                if (!station.latitude || !station.longitude) return;

                const color  = statusColors[station.status] ?? statusColors.not_reported;
                const label  = statusLabel[station.status]  ?? 'Unknown';

                const circleMarker = L.circleMarker(
                    [parseFloat(station.latitude), parseFloat(station.longitude)],
                    {
                        radius:      8,
                        fillColor:   color,
                        color:       '#ffffff',
                        weight:      2,
                        opacity:     1,
                        fillOpacity: 0.9,
                    }
                );

                // Build popup content
                const totalVotes   = station.total_votes_cast?.toLocaleString() ?? '—';
                const validVotes   = station.valid_votes?.toLocaleString()       ?? '—';
                const rejectedVotes = station.rejected_votes?.toLocaleString()   ?? '—';
                const registered   = station.registered_voters?.toLocaleString() ?? '—';
                const turnout      = station.registered_voters && station.total_votes_cast
                    ? ((station.total_votes_cast / station.registered_voters) * 100).toFixed(1) + '%'
                    : '—';

                circleMarker.bindPopup(`
                    <div style="min-width:200px; font-family: system-ui, sans-serif;">
                        <div style="font-weight:700; font-size:14px; margin-bottom:6px; color:#1e293b;">
                            ${station.name}
                        </div>
                        <div style="font-size:11px; color:#64748b; margin-bottom:8px;">
                            Code: <strong>${station.code}</strong>
                        </div>
                        <div style="display:inline-block; padding:2px 8px; border-radius:9999px;
                                    background:${color}22; color:${color};
                                    font-size:11px; font-weight:600; margin-bottom:10px;">
                            ${label}
                        </div>
                        <table style="width:100%; font-size:12px; border-collapse:collapse;">
                            <tr>
                                <td style="padding:2px 0; color:#64748b;">Registered</td>
                                <td style="padding:2px 0; text-align:right; font-weight:600;">${registered}</td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; color:#64748b;">Votes Cast</td>
                                <td style="padding:2px 0; text-align:right; font-weight:600;">${totalVotes}</td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; color:#64748b;">Valid</td>
                                <td style="padding:2px 0; text-align:right; font-weight:600; color:#10b981;">${validVotes}</td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; color:#64748b;">Rejected</td>
                                <td style="padding:2px 0; text-align:right; font-weight:600; color:#ef4444;">${rejectedVotes}</td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; color:#64748b;">Turnout</td>
                                <td style="padding:2px 0; text-align:right; font-weight:600; color:#3b82f6;">${turnout}</td>
                            </tr>
                        </table>
                    </div>
                `, { maxWidth: 260 });

                circleMarker.addTo(map);
            });

            // Auto-fit bounds to all stations if any
            if (stations.length > 0) {
                const validStations = stations.filter(s => s.latitude && s.longitude);
                if (validStations.length > 0) {
                    const bounds = L.latLngBounds(
                        validStations.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)])
                    );
                    map.fitBounds(bounds.pad(0.15), { maxZoom: 15 });
                }
            }

            mapInstanceRef.current = map;
        });

        // Cleanup on unmount
        return () => {
            if (mapInstanceRef.current) {
                mapInstanceRef.current.remove();
                mapInstanceRef.current = null;
            }
        };
    }, []); // run once on mount

    // When stations prop changes, re-render markers (without re-creating the map)
    useEffect(() => {
        if (!mapInstanceRef.current || stations.length === 0) return;
        // markers are added during init — for live updates a more complex diff would be needed
        // For now the initial load covers the use case
    }, [stations]);

    return (
        <div className="relative w-full rounded-xl overflow-hidden shadow-2xl border border-slate-700/50"
             style={{ height: '70vh', minHeight: '480px' }}>
            {/* Map container */}
            <div ref={mapRef} className="w-full h-full" />

            {/* Legend overlay — positioned bottom-left, above the map */}
            <div className="absolute bottom-4 left-4 z-[1000] bg-slate-900/90 backdrop-blur-sm
                            rounded-lg px-4 py-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-white
                            border border-slate-700/50 shadow-lg">
                {[
                    { color: '#10b981', label: 'Certified' },
                    { color: '#6b7280', label: 'In Review' },
                    { color: '#f97316', label: 'Submitted' },
                    { color: '#ff6ed4', label: 'Not Reported' },
                ].map(({ color, label }) => (
                    <span key={label} className="flex items-center gap-1.5">
                        <span className="w-3 h-3 rounded-full inline-block flex-shrink-0"
                              style={{ background: color, border: '2px solid white' }} />
                        {label}
                    </span>
                ))}
            </div>
        </div>
    );
}
