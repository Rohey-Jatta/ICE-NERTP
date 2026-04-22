import { useEffect, useRef } from 'react';

const STATUS_COLORS = {
    nationally_certified:     '#10b981',
    admin_area_certified:     '#14b8a6',
    constituency_certified:   '#06b6d4',
    ward_certified:           '#3b82f6',
    pending_national:         '#6366f1',
    pending_admin_area:       '#8b5cf6',
    pending_constituency:     '#a855f7',
    pending_ward:             '#f59e0b',
    pending_party_acceptance: '#f59e0b',
    submitted:                '#f97316',
    not_reported:             '#64748b',
};

const STATUS_LABELS = {
    nationally_certified:     'Certified ✓',
    admin_area_certified:     'Area Certified',
    constituency_certified:   'Const. Certified',
    ward_certified:           'Ward Certified',
    pending_national:         'At Chairman',
    pending_admin_area:       'At Admin Area',
    pending_constituency:     'At Constituency',
    pending_ward:             'At Ward',
    pending_party_acceptance: 'Party Review',
    submitted:                'Submitted',
    not_reported:             'Not Reported',
};

function buildPopupHtml(station) {
    const status      = station.status || 'not_reported';
    const statusLabel = STATUS_LABELS[status] || status;
    const color       = STATUS_COLORS[status] || '#64748b';
    const hasResult   = station.total_votes_cast != null && station.total_votes_cast > 0;

    const turnout = hasResult && station.registered_voters > 0
        ? ((station.total_votes_cast / station.registered_voters) * 100).toFixed(1)
        : null;

    // Safely parse candidate votes (may be JSON string or array)
    let candidates = [];
    if (station.candidate_votes) {
        try {
            candidates = typeof station.candidate_votes === 'string'
                ? JSON.parse(station.candidate_votes)
                : Array.isArray(station.candidate_votes)
                ? station.candidate_votes
                : [];
        } catch { candidates = []; }
    }
    candidates = candidates.filter(Boolean);

    const validVotes = station.valid_votes || station.total_votes_cast || 0;

    const candidateRows = candidates.length > 0
        ? candidates.map((cv, idx) => {
            const pct       = validVotes > 0 ? ((cv.votes / validVotes) * 100).toFixed(1) : '0.0';
            const cvColor   = (cv.color || '#6b7280').split(',')[0].trim();
            const isLeading = idx === 0;
            return `
                <div style="margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
                        <div style="display:flex;align-items:center;gap:5px;flex:1;min-width:0;">
                            ${isLeading
                                ? '<span style="font-size:10px;flex-shrink:0;">🏆</span>'
                                : '<span style="width:14px;flex-shrink:0;"></span>'
                            }
                            <span style="width:9px;height:9px;border-radius:50%;background:${cvColor};display:inline-block;flex-shrink:0;"></span>
                            <span style="font-size:12px;font-weight:${isLeading ? '600' : '400'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">${cv.name}</span>
                            <span style="font-size:10px;color:#6b7280;flex-shrink:0;margin-left:3px;">${cv.party}</span>
                        </div>
                        <div style="display:flex;align-items:baseline;gap:3px;flex-shrink:0;margin-left:8px;">
                            <span style="font-size:13px;font-weight:700;">${Number(cv.votes).toLocaleString()}</span>
                            <span style="font-size:10px;color:#6b7280;">${pct}%</span>
                        </div>
                    </div>
                    <div style="background:#e5e7eb;border-radius:3px;height:5px;overflow:hidden;margin-left:19px;">
                        <div style="background:${cvColor};height:5px;width:${pct}%;border-radius:3px;"></div>
                    </div>
                </div>
            `;
        }).join('')
        : '';

    const statsGrid = hasResult ? `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:10px 14px;">
            <div style="background:#f8fafc;border-radius:6px;padding:7px;text-align:center;">
                <div style="font-size:10px;color:#64748b;">Registered</div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;">${Number(station.registered_voters || 0).toLocaleString()}</div>
            </div>
            <div style="background:#f8fafc;border-radius:6px;padding:7px;text-align:center;">
                <div style="font-size:10px;color:#64748b;">Votes Cast</div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;">${Number(station.total_votes_cast || 0).toLocaleString()}</div>
            </div>
            <div style="background:#f0fdf4;border-radius:6px;padding:7px;text-align:center;">
                <div style="font-size:10px;color:#64748b;">Valid</div>
                <div style="font-size:13px;font-weight:700;color:#10b981;">${Number(station.valid_votes || 0).toLocaleString()}</div>
            </div>
            <div style="background:#eff6ff;border-radius:6px;padding:7px;text-align:center;">
                <div style="font-size:10px;color:#64748b;">Turnout</div>
                <div style="font-size:13px;font-weight:700;color:#3b82f6;">${turnout ? turnout + '%' : '—'}</div>
            </div>
        </div>
    ` : `
        <div style="padding:10px 14px;">
            <div style="background:#f8fafc;border-radius:6px;padding:10px;text-align:center;color:#64748b;font-size:12px;">
                No results submitted yet<br/>
                <span style="font-size:11px;color:#94a3b8;">${Number(station.registered_voters || 0).toLocaleString()} registered voters</span>
            </div>
        </div>
    `;

    const candidatesSection = candidateRows ? `
        <div style="border-top:1px solid #f1f5f9;padding:10px 14px 12px;">
            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;font-weight:600;">Candidate Results</div>
            ${candidateRows}
        </div>
    ` : '';

    return `
        <div style="min-width:270px;max-width:320px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
            <!-- Header -->
            <div style="padding:12px 14px 10px;border-bottom:1px solid #f1f5f9;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:14px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${station.name}</div>
                        <div style="font-size:11px;color:#64748b;margin-top:1px;">Code: <strong>${station.code}</strong></div>
                    </div>
                    <span style="flex-shrink:0;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap;background:${color}1a;color:${color};border:1px solid ${color}55;">
                        ${statusLabel}
                    </span>
                </div>
            </div>
            <!-- Stats -->
            ${statsGrid}
            <!-- Candidates -->
            ${candidatesSection}
        </div>
    `;
}

export default function LeafletMap({ stations = [] }) {
    const mapRef        = useRef(null);
    const mapInstance   = useRef(null);
    const markersLayer  = useRef(null);
    const LRef          = useRef(null);

    // ── Initialise map once ─────────────────────────────────────────────
    useEffect(() => {
        if (typeof window === 'undefined' || !mapRef.current) return;

        let cancelled = false;

        import('leaflet').then((mod) => {
            if (cancelled || mapInstance.current) return;
            const L = mod.default || mod;
            LRef.current = L;

            const map = L.map(mapRef.current, {
                center: [13.45, -15.3],
                zoom:   9,
                zoomControl: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>',
                maxZoom: 18,
            }).addTo(map);

            markersLayer.current = L.layerGroup().addTo(map);
            mapInstance.current  = map;

            // Render initial stations
            renderMarkers(L, map, markersLayer.current, stations);
        });

        return () => {
            cancelled = true;
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current  = null;
                markersLayer.current = null;
            }
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // ── Re-render markers when stations change ──────────────────────────
    useEffect(() => {
        if (!mapInstance.current || !markersLayer.current || !LRef.current) return;
        renderMarkers(LRef.current, mapInstance.current, markersLayer.current, stations);
    }, [stations]);

    return (
        <div style={{ position: 'relative' }}>
            <div ref={mapRef} style={{ height: '580px', width: '100%', borderRadius: '12px' }} />

            {/* Legend */}
            <div style={{
                position: 'absolute',
                bottom: 24,
                right: 12,
                zIndex: 1000,
                background: 'rgba(15,23,42,0.92)',
                border: '1px solid rgba(100,116,139,0.4)',
                borderRadius: 10,
                padding: '10px 14px',
                fontSize: 11,
                color: '#cbd5e1',
                pointerEvents: 'none',
                backdropFilter: 'blur(8px)',
            }}>
                {[
                    ['nationally_certified',   'Nationally Certified'],
                    ['admin_area_certified',   'Area Certified'],
                    ['constituency_certified', 'Constituency Cert.'],
                    ['ward_certified',         'Ward Certified'],
                    ['pending_ward',           'Pending / In Review'],
                    ['submitted',              'Submitted'],
                    ['not_reported',           'Not Reported'],
                ].map(([key, label]) => (
                    <div key={key} style={{ display: 'flex', alignItems: 'center', gap: 7, marginBottom: 5 }}>
                        <span style={{ width: 10, height: 10, borderRadius: '50%', background: STATUS_COLORS[key], display: 'inline-block', flexShrink: 0 }} />
                        {label}
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Helpers ─────────────────────────────────────────────────────────────────

function renderMarkers(L, map, layer, stations) {
    layer.clearLayers();

    const bounds = [];

    stations.forEach((station) => {
        const lat = parseFloat(station.latitude);
        const lng = parseFloat(station.longitude);
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

        const status = station.status || 'not_reported';
        const color  = STATUS_COLORS[status] || '#64748b';

        const marker = L.circleMarker([lat, lng], {
            radius:      7,
            fillColor:   color,
            color:       '#fff',
            weight:      2,
            opacity:     1,
            fillOpacity: 0.85,
        });

        marker.bindPopup(buildPopupHtml(station), {
            maxWidth:  340,
            minWidth:  270,
            className: 'station-popup-leaflet',
        });

        marker.addTo(layer);
        bounds.push([lat, lng]);
    });

    // Fit map to station bounds if we have data
    if (bounds.length > 0) {
        try {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
        } catch { /* ignore */ }
    }
}
