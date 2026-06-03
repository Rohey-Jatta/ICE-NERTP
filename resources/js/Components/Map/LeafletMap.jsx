import { useEffect, useRef, useState } from 'react';
import GAMBIA_GEO from '@/data/gambiaAdm1.json';
import { RESULT_STATUS_MAP_COLORS, RESULT_STATUS_MAP_LABELS } from '@/Utils/resultStatus';

const NO_DATA_FILL = '#e2e8f0';

function firstColor(value, fallback = '#94a3b8') {
    return (value || fallback).split(',')[0].trim();
}

// ── Region ↔ boundary name matching ───────────────────────────────────────────
// DB admin-area names ("Greater Banjul", "Brikama (West Coast)") don't match the
// boundary shapeNames ("Banjul", "Brikama"); normalize + alias to bridge them.
function normalizeName(s) {
    return String(s || '').toLowerCase().replace(/\(.*?\)/g, '').replace(/[^a-z]/g, '');
}

// Boundary feature → keywords that should match a DB region's normalized name.
// Banjul and Kanifing are now separate regions in the DB — no aliases needed.
const FEATURE_ALIASES = {};

function matchRegion(featureName, regions) {
    const f = normalizeName(featureName);
    const aliases = FEATURE_ALIASES[f] || [f];
    return regions.find((r) => {
        const rn = normalizeName(r.name);
        return aliases.some((a) => rn.includes(a) || a.includes(rn));
    }) || null;
}

// ── Station popup (station-dot mode) ──────────────────────────────────────────
function buildPopupHtml(station) {
    const status      = station.status || 'not_reported';
    const statusLabel = RESULT_STATUS_MAP_LABELS[status] || status;
    const color       = RESULT_STATUS_MAP_COLORS[status] || '#64748b';
    const hasResult   = station.total_votes_cast != null && station.total_votes_cast > 0;

    const turnout = hasResult && station.registered_voters > 0
        ? ((station.total_votes_cast / station.registered_voters) * 100).toFixed(1)
        : null;

    let candidates = [];
    if (station.candidate_votes) {
        try {
            candidates = typeof station.candidate_votes === 'string'
                ? JSON.parse(station.candidate_votes)
                : Array.isArray(station.candidate_votes) ? station.candidate_votes : [];
        } catch { candidates = []; }
    }
    candidates = candidates.filter(Boolean);
    const validVotes = station.valid_votes || station.total_votes_cast || 0;

    const candidateRows = candidates.length > 0
        ? candidates.map((cv, idx) => {
            const pct     = validVotes > 0 ? ((cv.votes / validVotes) * 100).toFixed(1) : '0.0';
            const cvColor = firstColor(cv.color, '#6b7280');
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

// ── Region choropleth tooltip ─────────────────────────────────────────────────
function regionTooltipHtml(featureName, region) {
    if (!region) {
        return `<div style="font-family:-apple-system,sans-serif;font-size:12px;"><b>${featureName}</b><br/><span style="color:#94a3b8;">No reported results</span></div>`;
    }
    const leader = region.leader;
    const lead = leader
        ? `<span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:9px;height:9px;border-radius:50%;background:${firstColor(leader.color)};"></span><b>${leader.party}</b> ${leader.pct}%</span>`
        : '<span style="color:#94a3b8;">Awaiting results</span>';
    return `
        <div style="font-family:-apple-system,sans-serif;font-size:12px;line-height:1.5;">
            <b style="font-size:13px;">${region.name}</b><br/>
            ${lead}<br/>
            <span style="color:#64748b;">${region.reporting_pct}% reporting · ${Number(region.total_votes).toLocaleString()} votes</span>
        </div>`;
}

export default function LeafletMap({
    stations = [],
    regions = [],
    drillStations = [],
    mode = 'regions',
    selectedRegion = null,
    drillLevel = null,   // 'region' | 'constituency' | 'ward' | null
    onRegionClick = null,
    height = '460px',
}) {
    const mapRef       = useRef(null);
    const mapInstance  = useRef(null);
    const markersLayer = useRef(null);
    const geoLayer     = useRef(null);
    const LRef         = useRef(null);
    // Keep latest props for event handlers bound once.
    const regionsRef   = useRef(regions);
    const onClickRef   = useRef(onRegionClick);
    regionsRef.current = regions;
    onClickRef.current = onRegionClick;

    const [mapReady, setMapReady] = useState(false);

    function zoomIn()  { mapInstance.current?.zoomIn();  }
    function zoomOut() { mapInstance.current?.zoomOut(); }
    function resetView() {
        if (!mapInstance.current) return;
        if (geoLayer.current && mode === 'regions') {
            mapInstance.current.fitBounds(geoLayer.current.getBounds(), { padding: [20, 20] });
        } else {
            mapInstance.current.setView([13.45, -15.3], 8);
        }
    }

    // ── Init map (once) ───────────────────────────────────────────────────────
    useEffect(() => {
        if (typeof window === 'undefined' || !mapRef.current) return;
        let cancelled = false;

        import('leaflet').then((mod) => {
            if (cancelled || mapInstance.current) return;
            const L = mod.default || mod;
            LRef.current = L;

            const map = L.map(mapRef.current, {
                center: [13.45, -15.3],
                zoom: 8,
                zoomControl: false,
                zoomSnap: 0.5,
            });

            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors © <a href="https://carto.com/attributions" target="_blank">CARTO</a>',
                subdomains: 'abcd',
                maxZoom: 19,
            }).addTo(map);

            // Choropleth layer
            geoLayer.current = L.geoJSON(GAMBIA_GEO, {
                style: () => ({ weight: 1, color: '#fff', fillColor: NO_DATA_FILL, fillOpacity: 0.6 }),
                onEachFeature: (feature, layer) => {
                    layer.on({
                        mouseover: (e) => {
                            e.target.setStyle({ weight: 2.5, color: '#0f172a' });
                            e.target.bringToFront();
                        },
                        mouseout: (e) => {
                            const fname = feature.properties.name;
                            const isSel = matchRegion(fname, regionsRef.current)?.name === selectedRegionRef.current;
                            e.target.setStyle({ weight: isSel ? 2.5 : 1, color: isSel ? '#0f172a' : '#fff' });
                        },
                        click: () => {
                            const region = matchRegion(feature.properties.name, regionsRef.current);
                            if (onClickRef.current) onClickRef.current(region?.name || null, feature.properties.name);
                        },
                    });
                },
            });

            markersLayer.current = L.layerGroup().addTo(map);
            mapInstance.current = map;
            setMapReady(true);
        });

        return () => {
            cancelled = true;
            if (mapInstance.current) {
                mapInstance.current.remove();
                mapInstance.current = null;
                markersLayer.current = null;
                geoLayer.current = null;
                LRef.current = null;
                setMapReady(false);
            }
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Keep selectedRegion accessible to the once-bound mouseout handler.
    const selectedRegionRef = useRef(selectedRegion);
    selectedRegionRef.current = selectedRegion;

    // ── Show/hide choropleth layer by mode ────────────────────────────────────
    useEffect(() => {
        const map = mapInstance.current;
        if (!map || !mapReady || !geoLayer.current) return;
        if (mode === 'regions') {
            if (!map.hasLayer(geoLayer.current)) geoLayer.current.addTo(map);
        } else if (map.hasLayer(geoLayer.current)) {
            map.removeLayer(geoLayer.current);
        }
    }, [mode, mapReady]);

    // ── Restyle choropleth + zoom on data / selection change ──────────────────
    useEffect(() => {
        const map = mapInstance.current;
        if (!mapReady || mode !== 'regions' || !geoLayer.current || !map) return;

        let selectedLayer = null;
        geoLayer.current.eachLayer((layer) => {
            const fname = layer.feature.properties.name;
            const region = matchRegion(fname, regions);
            const isSel = region?.name === selectedRegion;
            const dimmed = selectedRegion && !isSel;
            const hasLeader = region && region.leader;
            const fill = hasLeader ? firstColor(region.leader.color) : NO_DATA_FILL;
            // Dim fill by reporting completeness; fade non-selected regions when drilling.
            let opacity = hasLeader ? 0.45 + 0.4 * (Math.min(100, region.reporting_pct) / 100) : 0.55;
            if (dimmed) opacity = 0.12;
            layer.setStyle({
                weight: isSel ? 2.5 : 1,
                color: isSel ? '#0f172a' : '#fff',
                fillColor: fill,
                fillOpacity: opacity,
            });
            layer.bindTooltip(regionTooltipHtml(fname, region), { sticky: true, opacity: 0.97 });
            if (isSel) selectedLayer = layer;
        });

        try {
            if (selectedLayer) {
                map.fitBounds(selectedLayer.getBounds(), { padding: [40, 40], maxZoom: 11 });
            } else {
                map.fitBounds(geoLayer.current.getBounds(), { padding: [20, 20] });
            }
        } catch { /* ignore */ }
    }, [regions, selectedRegion, mode, mapReady]);

    // ── Render station markers (stations mode = all; regions mode = drill set) ─
    useEffect(() => {
        if (!mapReady || !LRef.current || !markersLayer.current) return;
        const L = LRef.current;
        const map = mapInstance.current;
        if (mode === 'stations') {
            renderMarkers(L, map, markersLayer.current, stations, true);
        } else if (selectedRegion) {
            // At constituency/ward level, fit to the (smaller) dot set so the
            // user sees the zoom-in without the choropleth re-fitting overriding it.
            const fitToDots = drillLevel === 'constituency' || drillLevel === 'ward';
            renderMarkers(L, map, markersLayer.current, drillStations, fitToDots);
        } else {
            markersLayer.current.clearLayers();
        }
    }, [stations, drillStations, selectedRegion, drillLevel, mode, mapReady]);

    const mapHeight = typeof height === 'string' ? height : `${height}px`;
    const showEmpty = mode === 'stations' && stations.length === 0;

    return (
        <div className="relative isolate z-0" style={{ height: mapHeight }}>
            <div ref={mapRef} className="h-full w-full rounded-xl" />

            {mapReady && (
                <div className="absolute right-4 top-4 z-[1000] flex flex-col gap-1.5">
                    <button onClick={zoomIn} className="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-xl font-light text-slate-600 shadow-md transition-colors hover:bg-slate-50" title="Zoom in" aria-label="Zoom in">+</button>
                    <button onClick={zoomOut} className="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-xl font-light text-slate-600 shadow-md transition-colors hover:bg-slate-50" title="Zoom out" aria-label="Zoom out">−</button>
                    <button onClick={resetView} className="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-md transition-colors hover:bg-slate-50" title="Reset view" aria-label="Reset map view">
                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    </button>
                </div>
            )}

            {showEmpty && (
                <div className="absolute inset-0 z-30 flex items-center justify-center rounded-xl bg-white/75 p-6 backdrop-blur-sm">
                    <div className="max-w-sm rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                        <h3 className="text-lg font-extrabold text-slate-950">No mapped stations match</h3>
                        <p className="mt-2 text-sm text-slate-500">Adjust the region, constituency, status, or search filters.</p>
                    </div>
                </div>
            )}

            {/* Boundary attribution (CC BY 4.0) */}
            <div className="pointer-events-none absolute bottom-1 left-2 z-[400] text-[10px] text-slate-400">
                Boundaries © geoBoundaries (CC BY 4.0)
            </div>
        </div>
    );
}

// ── Station markers (dots) ────────────────────────────────────────────────────
function renderMarkers(L, map, layer, stations, fit = true) {
    layer.clearLayers();
    const bounds = [];

    stations.forEach((station) => {
        const lat = parseFloat(station.latitude);
        const lng = parseFloat(station.longitude);
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

        const status = station.status || 'not_reported';
        const color  = RESULT_STATUS_MAP_COLORS[status] || '#64748b';

        const marker = L.circleMarker([lat, lng], {
            radius: 6, fillColor: color, color: '#fff', weight: 1.5, opacity: 1, fillOpacity: 0.9,
        });
        marker.bindPopup(buildPopupHtml(station), { maxWidth: 340, minWidth: 270, className: 'station-popup-leaflet' });
        marker.addTo(layer);
        marker.bringToFront();
        bounds.push([lat, lng]);
    });

    if (fit) {
        if (bounds.length > 0) {
            try { map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 }); } catch { /* ignore */ }
        } else {
            map.setView([13.45, -15.3], 8);
        }
    }
}
