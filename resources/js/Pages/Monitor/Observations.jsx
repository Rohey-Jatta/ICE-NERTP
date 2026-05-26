import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const TYPE_CONFIG = {
    general:         { label: 'General',          color: 'bg-iec-pink-500/20 text-iec-pink-600 border-blue-500/30',     icon: '📋' },
    positive:        { label: 'Positive',          color: 'bg-green-500/20 text-green-300 border-green-500/30', icon: '✅' },
    process_concern: { label: 'Process Concern',   color: 'bg-amber-500/20 text-amber-300 border-amber-500/30', icon: '⚠️' },
    irregularity:    { label: 'Irregularity',      color: 'bg-orange-500/20 text-orange-300 border-orange-500/30', icon: '🚨' },
    incident:        { label: 'Incident',          color: 'bg-red-500/20 text-red-300 border-red-500/30',       icon: '🔴' },
};

const SEVERITY_CONFIG = {
    low:      { label: 'Low',      color: 'bg-green-500/20 text-green-300',  dot: 'bg-green-400' },
    medium:   { label: 'Medium',   color: 'bg-amber-500/20 text-amber-300',  dot: 'bg-amber-400' },
    high:     { label: 'High',     color: 'bg-orange-500/20 text-orange-300', dot: 'bg-orange-400' },
    critical: { label: 'Critical', color: 'bg-red-500/20 text-red-300',      dot: 'bg-red-400' },
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
                        {/* Export button */}
                        
                        <a href="/monitor/observations/export"
                            className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg flex items-center gap-2"
                        >
                            ⬇ Export CSV
                        </a>
                    </div>
                </div>

                {/* Type summary pills */}
                {totalObservations > 0 && (
                    <div className="flex flex-wrap gap-2 mb-6">
                        {Object.entries(typeCounts).map(([type, count]) => {
                            const cfg = TYPE_CONFIG[type] || { label: type, color: 'bg-slate-100 text-slate-600', icon: '📋' };
                            return (
                                <div key={type} className={`flex items-center gap-2 px-3 py-1.5 rounded-full text-sm border ${cfg.color}`}>
                                    <span>{cfg.icon}</span>
                                    <span className="font-medium">{cfg.label}</span>
                                    <span className="font-bold">{count}</span>
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
                                <div key={obs.id} className="bg-white rounded-xl border border-slate-200 overflow-hidden">

                                    {/* Observation Header */}
                                    <button
                                        onClick={() => setExpandedId(isExpanded ? null : obs.id)}
                                        className="w-full p-5 text-left flex flex-wrap gap-4 justify-between items-start hover:bg-slate-100 transition-colors"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap mb-1">
                                                <span>{typeCfg.icon}</span>
                                                <h3 className="text-iec-navy font-bold">{obs.title}</h3>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${typeCfg.color}`}>
                                                    {typeCfg.label}
                                                </span>
                                                <span className={`flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ${severityCfg.color}`}>
                                                    <span className={`w-1.5 h-1.5 rounded-full ${severityCfg.dot}`} />
                                                    {severityCfg.label}
                                                </span>
                                                {!obs.is_public && (
                                                    <span className="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-400">
                                                        Private
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-sm text-slate-500">
                                                {obs.station_name} ({obs.station_code}) —{' '}
                                                {obs.observed_at ? new Date(obs.observed_at).toLocaleString() : '—'}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {obs.photo_paths?.length > 0 && (
                                                <span className="text-xs text-slate-500 flex items-center gap-1">
                                                    📷 {obs.photo_paths.length}
                                                </span>
                                            )}
                                            <span className="text-slate-500 text-lg">{isExpanded ? '▲' : '▼'}</span>
                                        </div>
                                    </button>

                                    {/* Expanded content */}
                                    {isExpanded && (
                                        <div className="border-t border-slate-200 p-5">
                                            {/* Observation text */}
                                            <div className="mb-4">
                                                <div className="text-xs text-slate-500 uppercase tracking-wide mb-2">Observation</div>
                                                <p className="text-gray-200 leading-relaxed whitespace-pre-wrap">{obs.observation}</p>
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