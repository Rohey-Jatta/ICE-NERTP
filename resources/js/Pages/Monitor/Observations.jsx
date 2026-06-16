import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';
import { useNotifications, ToastContainer } from '@/Components/Notifications';
import { useMonitorNotifications } from '@/Hooks/useMonitorNotifications';

const TYPE_CONFIG = {
    general:         { label: 'General',          color: 'bg-blue-600 text-white border-blue-700',     icon: '📋' },
    positive:        { label: 'Positive',         color: 'bg-green-600 text-white border-green-700',   icon: '✅' },
    process_concern: { label: 'Process Concern',  color: 'bg-amber-600 text-white border-amber-700',   icon: '⚠️' },
    irregularity:    { label: 'Irregularity',     color: 'bg-orange-600 text-white border-orange-700', icon: '🚨' },
    incident:        { label: 'Incident',         color: 'bg-red-600 text-white border-red-700',       icon: '🔴' },
};

const SEVERITY_CONFIG = {
    low:      { label: 'Low',      color: 'bg-green-600 text-white',   dot: 'bg-green-400' },
    medium:   { label: 'Medium',   color: 'bg-amber-600 text-white',   dot: 'bg-amber-400' },
    high:     { label: 'High',     color: 'bg-orange-600 text-white',  dot: 'bg-orange-400' },
    critical: { label: 'Critical', color: 'bg-red-600 text-white',     dot: 'bg-red-400' },
};

export default function Observations({
    auth,
    monitor,
    observations = [],
    typeFilter = 'all',
    severityFilter = 'all',
    typeCounts = {},
}) {
    const [expandedId, setExpandedId]     = useState(null);
    const [selectedPhotos, setSelectedPhotos] = useState([]);
    const [refreshing, setRefreshing] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    const { toasts, removeNotification, notify } = useNotifications();

    // Monitor observations for new submissions
    useMonitorNotifications({
        observations: observations,
        onNotify: (message, type) => notify[type](message),
    });

    // Auto-refresh every 30 seconds
    useAutoRefreshWithVisibility({
        url: '/monitor/observations',
        interval: 30000,
        preserveScroll: true,
        preserveState: true,
        onBeforeRefresh: () => setRefreshing(true),
        onAfterRefresh: () => {
            setRefreshing(false);
            setLastRefreshTime(new Date());
        },
    });

    const handleFilterChange = (type, value) => {
        const params = {
            type:     typeFilter,
            severity: severityFilter,
            [type]:   value,
        };
        router.get('/monitor/observations', params, { preserveState: false });
    };

    const totalObservations = Object.values(typeCounts).reduce((s, v) => s + v, 0);

    return (
        <AppLayout user={auth?.user}>
            <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />
            
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/monitor/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                        Back to Monitor Dashboard
                    </Link>
                    <div className="flex items-start justify-between flex-wrap gap-4">
                        <div>
                            <h1 className="text-3xl font-bold text-iec-navy">My Observations</h1>
                            <p className="text-slate-500 mt-1">{totalObservations} total observations submitted</p>
                        </div>
                        
                        {/* Refresh Status & Export Buttons */}
                        <div className="flex flex-col items-end gap-2">
                            <div className={`text-xs flex items-center justify-end gap-2 px-3 py-2 rounded-lg ${refreshing ? 'bg-amber-500/20 text-amber-600' : 'bg-green-500/20 text-green-600'}`}>
                                {refreshing ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span className="font-semibold">Updating...</span>
                                    </>
                                ) : (
                                    <>
                                        <span>✓ Auto-refresh</span>
                                        <span className="text-xs opacity-75">{lastRefreshTime.toLocaleTimeString()}</span>
                                    </>
                                )}
                            </div>
                            
                            <div className="flex gap-2">
                                <a href="/monitor/observations/export"
                                    className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg flex items-center gap-2"
                                >
                                    ⬇ Export CSV
                                </a>
                                <a href={`/monitor/observations/pdf/batch?type=${typeFilter}&severity=${severityFilter}`}
                                    className="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white font-bold rounded-lg flex items-center gap-2"
                                >
                                    📄 Export PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Type summary pills */}
                {totalObservations > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-6">
                        {Object.entries(typeCounts).map(([type, count]) => {
                            const cfg = TYPE_CONFIG[type] || { label: type, color: 'bg-slate-600 text-white', icon: '📋' };
                            return (
                                <div key={type} className={`flex items-center gap-2 px-4 py-3 rounded-lg text-sm font-bold border-2 ${cfg.color}`}>
                                    <span className="text-lg">{cfg.icon}</span>
                                    <div>
                                        <div>{cfg.label}</div>
                                        <div className="text-xs font-bold opacity-90">{count} submitted</div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Filters */}
                <div className="bg-white rounded-xl p-4 border border-slate-200 mb-6">
                    <div className="flex flex-wrap gap-6">
                        {/* Type filter */}
                        <div>
                            <label className="block text-slate-500 text-xs mb-2 uppercase tracking-wide">Type</label>
                            <div className="flex flex-wrap gap-2">
                                {['all', 'general', 'positive', 'process_concern', 'irregularity', 'incident'].map(t => (
                                    <button
                                        key={t}
                                        onClick={() => handleFilterChange('type', t)}
                                        className={`px-3 py-1 rounded-lg text-xs font-semibold transition-colors ${
                                            typeFilter === t
                                                ? 'bg-iec-pink-600 text-white'
                                                : 'bg-white text-slate-600 hover:bg-slate-100'
                                        }`}
                                    >
                                        {t === 'all' ? 'All Types' : TYPE_CONFIG[t]?.label || t}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Severity filter */}
                        <div>
                            <label className="block text-slate-500 text-xs mb-2 uppercase tracking-wide">Severity</label>
                            <div className="flex flex-wrap gap-2">
                                {['all', 'low', 'medium', 'high', 'critical'].map(s => (
                                    <button
                                        key={s}
                                        onClick={() => handleFilterChange('severity', s)}
                                        className={`px-3 py-1 rounded-lg text-xs font-semibold transition-colors ${
                                            severityFilter === s
                                                ? 'bg-iec-pink-600 text-white'
                                                : 'bg-white text-slate-600 hover:bg-slate-100'
                                        }`}
                                    >
                                        {s === 'all' ? 'All Severities' : SEVERITY_CONFIG[s]?.label || s}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Observations list */}
                {observations.length === 0 ? (
                    <div className="bg-white rounded-xl p-12 border border-slate-200 text-center">
                        <div className="text-5xl mb-4">🗂</div>
                        <p className="text-slate-600 text-lg">
                            {typeFilter !== 'all' || severityFilter !== 'all'
                                ? 'No observations match the current filters.'
                                : 'No observations submitted yet.'}
                        </p>
                        <Link
                            href="/monitor/submit-observation"
                            className="mt-4 inline-block px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg"
                        >
                            Submit First Observation
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {observations.map((obs) => {
                            const typeCfg     = TYPE_CONFIG[obs.observation_type]     || TYPE_CONFIG.general;
                            const severityCfg = SEVERITY_CONFIG[obs.severity] || SEVERITY_CONFIG.low;
                            const isExpanded  = expandedId === obs.id;

                            return (
                                <div key={obs.id} className="bg-white rounded-xl border border-slate-200 overflow-hidden hover:border-slate-300 transition-colors">

                                    {/* Observation Header */}
                                    <button
                                        onClick={() => setExpandedId(isExpanded ? null : obs.id)}
                                        className="w-full p-5 text-left flex flex-wrap gap-3 justify-between items-start hover:bg-slate-50 transition-colors"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap mb-2">
                                                <span className="text-xl">{typeCfg.icon}</span>
                                                <h3 className="text-lg font-bold text-iec-navy">{obs.title}</h3>
                                            </div>
                                            
                                            {/* Enhanced Badges */}
                                            <div className="flex items-center gap-2 flex-wrap mb-2">
                                                <span className={`px-3 py-1.5 rounded-lg text-sm font-bold border ${typeCfg.color}`}>
                                                    {typeCfg.label}
                                                </span>
                                                <span className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-bold ${severityCfg.color}`}>
                                                    <span className={`w-2 h-2 rounded-full ${severityCfg.dot}`} />
                                                    {severityCfg.label}
                                                </span>
                                                {!obs.is_public && (
                                                    <span className="px-3 py-1.5 rounded-lg text-sm font-semibold bg-slate-200 text-slate-700">
                                                        🔒 Private
                                                    </span>
                                                )}
                                            </div>
                                            
                                            <div className="text-sm text-slate-600">
                                                <strong>{obs.station_name}</strong> ({obs.station_code}) • 
                                                <span className="font-mono text-xs ml-1">{obs.observed_at ? new Date(obs.observed_at).toLocaleString() : '—'}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 flex-shrink-0">
                                            {obs.photo_paths?.length > 0 && (
                                                <span className="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg flex items-center gap-1">
                                                    📷 {obs.photo_paths.length}
                                                </span>
                                            )}
                                            <span className="text-slate-500 text-lg">{isExpanded ? '▲' : '▼'}</span>
                                        </div>
                                    </button>

                                    {/* Expanded content */}
                                    {isExpanded && (
                                        <div className="border-t border-slate-200 p-5 space-y-4">
                                            {/* Observation text */}
                                            <div>
                                                <div className="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-2">Observation</div>
                                                <p className="text-slate-700 leading-relaxed whitespace-pre-wrap">{obs.observation}</p>
                                            </div>

                                            {/* GPS */}
                                            {(obs.latitude || obs.longitude) && (
                                                <div className="mb-4 flex items-center gap-2 text-sm text-slate-500">
                                                    <span>📍</span>
                                                    <span>
                                                        {obs.latitude}, {obs.longitude}
                                                    </span>
                                                    
                                                    <a href={`https://maps.google.com/?q=${obs.latitude},${obs.longitude}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-iec-pink-600 hover:text-iec-pink-600 text-xs underline"
                                                    >
                                                        View on Map
                                                    </a>
                                                </div>
                                            )}

                                            {/* Photos */}
                                            {obs.photo_paths?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-slate-500 uppercase tracking-wide mb-2">
                                                        Photos ({obs.photo_paths.length})
                                                    </div>
                                                    <div className="flex flex-wrap gap-3">
                                                        {obs.photo_paths.map((url, i) => (
                                                            <a                                                                key={i}
                                                                href={url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <img
                                                                    src={url}
                                                                    alt={`Photo ${i + 1}`}
                                                                    className="w-24 h-24 object-cover rounded-lg border border-slate-200 hover:opacity-80 transition-opacity"
                                                                />
                                                            </a>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Supporting Documents */}
                                            {obs.documents?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-2">
                                                        Supporting Documents ({obs.documents.length})
                                                    </div>
                                                    <div className="space-y-2">
                                                        {obs.documents.map((doc, i) => (
                                                            <a
                                                                key={i}
                                                                href={doc.path}
                                                                download={doc.name}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 hover:bg-slate-100 transition-colors group"
                                                            >
                                                                <div className="flex-shrink-0 w-8 h-8 bg-slate-200 rounded flex items-center justify-center text-xs font-bold text-slate-600 group-hover:bg-iec-pink-200 group-hover:text-iec-pink-600 transition-colors">
                                                                    {doc.name.split('.').pop().toUpperCase()}
                                                                </div>
                                                                <div className="flex-1 min-w-0">
                                                                    <div className="text-sm font-medium text-slate-700 truncate group-hover:text-iec-pink-600 transition-colors">
                                                                        {doc.name}
                                                                    </div>
                                                                    <div className="text-xs text-slate-500">
                                                                        {(doc.size / 1024 / 1024).toFixed(2)} MB
                                                                    </div>
                                                                </div>
                                                                <span className="flex-shrink-0 text-iec-pink-600 group-hover:translate-x-1 transition-transform">
                                                                    ⬇
                                                                </span>
                                                            </a>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Download PDF */}
                                            <div className="mt-4 pt-4 border-t border-slate-200 flex gap-2">
                                                <a
                                                    href={`/monitor/observations/${obs.id}/pdf`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex-1 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg text-sm flex items-center justify-center gap-2 transition-colors"
                                                >
                                                    📄 Download PDF
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Bottom navigation */}
                <div className="mt-8 flex flex-wrap gap-4">
                    <Link href="/monitor/submit-observation" className="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                        📝 Submit New Observation
                    </Link>
                    <Link href="/monitor/stations" className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
                        📍 View My Stations
                    </Link>
                    <a href="/monitor/observations/export" className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
                        ⬇ Export All (CSV)
                    </a>
                </div>
            </div>
        </AppLayout>
    );
}