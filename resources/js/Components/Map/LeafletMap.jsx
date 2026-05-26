import { useEffect, useRef, useState } from 'react';

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
            const pct     = validVotes > 0 ? ((cv.votes / validVotes) * 100).toFixed(1) : '0.0';
            const cvColor = (cv.color || '#6b7280').split(',')[0].trim();
            const isFirst = idx === 0;
            return `
                <div style="margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
                        <div style="display:flex;align-items:center;gap:5px;flex:1;min-width:0;">
                            ${isFirst ? '<span style="font-size:10px;flex-shrink:0;">🏆</span>' : '<span style="width:14px;flex-shrink:0;"></span>'}
                            <span style="width:9px;height:9px;border-radius:50%;background:${cvColor};display:inline-block;flex-shrink:0;"></span>
                            <span style="font-size:12px;font-weight:${isFirst ? '600' : '400'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:115px;">${cv.name}</span>
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
                </div>`;
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
        </div>` : `
        <div style="padding:10px 14px;">
            <div style="background:#f8fafc;border-radius:6px;padding:10px;text-align:center;color:#64748b;font-size:12px;">
                No results submitted yet<br/>
                <span style="font-size:11px;color:#94a3b8;">${Number(station.registered_voters || 0).toLocaleString()} registered voters</span>
            </div>
        </div>`;

    const candidatesSection = candidateRows ? `
        <div style="border-top:1px solid #f1f5f9;padding:10px 14px 12px;">
            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;font-weight:600;">Candidate Results</div>
            ${candidateRows}
        </div>` : '';

    return `
        <div style="min-width:270px;max-width:320px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
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
            ${statsGrid}
            ${candidatesSection}
        </div>`;
}

export default function LeafletMap({ stations = [], height = '460px' }) {
    const mapRef        = useRef(null);
    const mapInstance   = useRef(null);
    const markersLayer  = useRef(null);
    const LRef          = useRef(null);

    const [mapReady, setMapReady] = useState(false);

    // ── Zoom control callbacks ────────────────────────────────────────────────
    function zoomIn()  { mapInstance.current?.zoomIn();  }
    function zoomOut() { mapInstance.current?.zoomOut(); }
    function resetView() {
        if (mapInstance.current) {
            mapInstance.current.setView([13.45, -15.3], 9);
        }
    }

    // ── Initialize map ────────────────────────────────────────────────────────
    useEffect(() => {
        if (typeof window === 'undefined' || !mapRef.current) return;
        let cancelled = false;

        import('leaflet').then((mod) => {
            if (cancelled || mapInstance.current) return;
            const L = mod.default || mod;
            LRef.current = L;

            const map = L.map(mapRef.current, {
                center:      [13.45, -15.3],
                zoom:        9,
                zoomControl: false,   // disable default — we use custom controls
                zoomSnap:    0.5,
                zoomDelta:   1,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>',
                maxZoom: 18,
            }).addTo(map);

            markersLayer.current = L.layerGroup().addTo(map);
            mapInstance.current  = map;
            setMapReady(true);
            renderMarkers(L, map, markersLayer.current, stations);
        });

        return () => {
            cancelled = true;
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current  = null;
                markersLayer.current = null;
                LRef.current         = null;
                setMapReady(false);
            }
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // ── Update markers ────────────────────────────────────────────────────────
    useEffect(() => {
        if (!mapInstance.current || !markersLayer.current || !LRef.current) return;
        renderMarkers(LRef.current, mapInstance.current, markersLayer.current, stations);
    }, [stations]);

    const legendItems = [
        ['nationally_certified',   'Certified'],
        ['admin_area_certified',   'Area certified'],
        ['constituency_certified', 'Constituency certified'],
        ['ward_certified',         'Ward certified'],
        ['pending_ward',           'Pending review'],
        ['submitted',              'Submitted'],
        ['not_reported',           'Not reported'],
    ];

    const mapHeight = typeof height === 'string' ? height : `${height}px`;

    return (
        <div className="relative isolate z-0" style={{ height: mapHeight }}>
            {/* Map container */}
            <div ref={mapRef} className="w-full h-full rounded-xl" />

            {/* ── Custom zoom controls ─────────────────────────────────── */}
            {mapReady && (
                <div className="absolute top-4 right-4 z-[1000] flex flex-col gap-1.5">
                    {/* Zoom in */}
                    <button
                        onClick={zoomIn}
                        className="w-10 h-10 bg-white rounded-lg shadow-md border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors text-xl font-light select-none"
                        title="Zoom in"
                        aria-label="Zoom in"
                    >
                        +
                    </button>

                    {/* Zoom out */}
                    <button
                        onClick={zoomOut}
                        className="w-10 h-10 bg-white rounded-lg shadow-md border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors text-xl font-light select-none"
                        title="Zoom out"
                        aria-label="Zoom out"
                    >
                        −
                    </button>

                    {/* Reset view */}
                    <button
                        onClick={resetView}
                        className="w-10 h-10 bg-white rounded-lg shadow-md border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 hover:text-slate-900 transition-colors select-none"
                        title="Reset view"
                        aria-label="Reset map view"
                    >
                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </button>
                </div>
            )}

            {/* Empty state overlay */}
            {stations.length === 0 && (
                <div className="absolute inset-0 z-30 flex items-center justify-center rounded-xl bg-white/75 p-6 backdrop-blur-sm">
                    <div className="max-w-sm rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                        <h3 className="text-lg font-extrabold text-slate-950">No mapped stations match</h3>
                        <p className="mt-2 text-sm text-slate-500">Adjust the region, constituency, status, or search filters.</p>
                    </div>
                </div>
            )}

            {/* Legend */}
            <div className="pointer-events-none absolute bottom-4 left-4 right-16 z-20 rounded-xl border border-slate-200 bg-white/95 p-3 text-xs text-slate-600 shadow-lg backdrop-blur sm:left-auto sm:right-16 sm:w-52">
                <div className="mb-2 text-[0.65rem] font-bold uppercase tracking-[0.14em] text-slate-500">
                    Result status
                </div>
                <div className="grid grid-cols-2 gap-x-3 gap-y-1.5 sm:grid-cols-1">
                    {legendItems.map(([key, label]) => (
                        <div key={key} className="flex items-center gap-2">
                            <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ background: STATUS_COLORS[key] }} />
                            <span className="truncate">{label}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

// ── Render markers ─────────────────────────────────────────────────────────────
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
            radius:      6,
            fillColor:   color,
            color:       '#fff',
            weight:      1.5,
            opacity:     1,
            fillOpacity: 0.82,
        });

        marker.bindPopup(buildPopupHtml(station), {
            maxWidth:  340,
            minWidth:  270,
            className: 'station-popup-leaflet',
        });

        marker.addTo(layer);
        bounds.push([lat, lng]);
    });

    if (bounds.length > 0) {
        try {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
        } catch { /* ignore */ }
    } else {
        map.setView([13.45, -15.3], 9);
    }
}