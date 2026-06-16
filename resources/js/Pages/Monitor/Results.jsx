import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { RESULT_STATUS, getResultStatusMeta, ACTIVE_CERTIFICATION_PIPELINE } from '@/Utils/resultStatus';
import { useAutoRefreshWithVisibility } from '@/Hooks/useAutoRefresh';
import { useNotifications, ToastContainer } from '@/Components/Notifications';

/**
 * Certification Stage Component
 * Displays current stage and timeline of result progression
 */
function CertificationStage({ status, submitted_at, updated_at }) {
    const stageIndex = ACTIVE_CERTIFICATION_PIPELINE.findIndex(s => s.key === status);
    const currentStage = stageIndex >= 0 ? ACTIVE_CERTIFICATION_PIPELINE[stageIndex] : null;
    
    if (!currentStage) return null;

    // Color coding by stage type
    const getStageColor = (key) => {
        if (key === RESULT_STATUS.SUBMITTED) return 'bg-blue-500/20 text-blue-300 border-blue-500/30';
        if (key === RESULT_STATUS.NATIONALLY_CERTIFIED) return 'bg-green-500/20 text-green-300 border-green-500/30';
        if (key.includes('PENDING')) return 'bg-amber-500/20 text-amber-300 border-amber-500/30';
        if (key.includes('CERTIFIED')) return 'bg-teal-500/20 text-teal-300 border-teal-500/30';
        return 'bg-slate-500/20 text-slate-300 border-slate-500/30';
    };

    return (
        <div className="space-y-3">
            {/* Current Stage Badge */}
            <div className="flex items-center gap-3">
                <div className="text-xs text-slate-500 uppercase tracking-wide font-semibold">Current Stage</div>
                <span className={`px-3 py-1 rounded-full text-sm font-semibold border ${getStageColor(status)}`}>
                    Step {currentStage.step}/9 — {currentStage.label}
                </span>
            </div>

            {/* Timeline Visualization */}
            <div className="mt-4">
                <div className="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-2">Certification Progress</div>
                <div className="flex items-center gap-1 mb-2">
                    {ACTIVE_CERTIFICATION_PIPELINE.map((stage, idx) => {
                        const isCompleted = stageIndex >= idx;
                        const isCurrent = stageIndex === idx;
                        return (
                            <div key={stage.key} className="flex-1 flex items-center gap-1">
                                <div
                                    className={`h-2 flex-1 rounded-full transition-all ${
                                        isCompleted
                                            ? isCurrent
                                                ? 'bg-amber-500'
                                                : 'bg-green-500'
                                            : 'bg-slate-300'
                                    }`}
                                />
                                {idx < ACTIVE_CERTIFICATION_PIPELINE.length - 1 && (
                                    <div className="text-slate-300 text-xs">→</div>
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* Stage Labels */}
                <div className="flex justify-between text-xs">
                    <span className="text-slate-500">Submitted</span>
                    <span className="text-slate-500">Ward</span>
                    <span className="text-slate-500">Constituency</span>
                    <span className="text-slate-500">Admin</span>
                    <span className="text-slate-500">Certified</span>
                </div>
            </div>

            {/* Timestamps */}
            <div className="mt-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div className="text-xs text-slate-500">
                    <div>📤 Submitted: <span className="text-slate-700 font-mono">{submitted_at}</span></div>
                    <div className="mt-1">⏱️ Last Updated: <span className="text-slate-700 font-mono">{updated_at}</span></div>
                </div>
            </div>
        </div>
    );
}

export default function MonitorResults({ auth, monitor, results = [] }) {
    const [expandedId, setExpandedId] = useState(null);
    const [refreshing, setRefreshing] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    const { toasts, removeNotification, notify } = useNotifications();

    // Auto-refresh every 30 seconds
    useAutoRefreshWithVisibility({
        url: '/monitor/results',
        interval: 30000,
        preserveScroll: true,
        preserveState: true,
        onBeforeRefresh: () => setRefreshing(true),
        onAfterRefresh: () => {
            setRefreshing(false);
            setLastRefreshTime(new Date());
            notify.info('Results updated');
        },
    });

    return (
        <AppLayout user={auth?.user}>
            <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />
            
            <div className="container mx-auto px-4 py-8">

                {/* Header */}
                <div className="mb-6 flex justify-between items-start">
                    <div>
                        <Link href="/monitor/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                            Back to Monitor Dashboard
                        </Link>
                        <h1 className="text-3xl font-bold text-iec-navy">Station Results</h1>
                        <p className="text-slate-500 mt-1">
                            Read-only view of results for your assigned polling stations
                        </p>
                        <div className="mt-2 inline-flex items-center gap-2 px-3 py-1 bg-iec-pink-500/10 border border-blue-500/30 rounded-lg text-iec-pink-600 text-xs">
                            ℹ️ View only — you cannot modify results
                        </div>
                    </div>
                    
                    {/* Refresh Status */}
                    <div className={`text-xs flex items-center justify-end gap-2 px-3 py-2 rounded-lg ${refreshing ? 'bg-amber-500/20 text-amber-600' : 'bg-green-500/20 text-green-600'}`}>
                        {refreshing ? (
                            <>
                                <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span className="font-semibold">Refreshing...</span>
                            </>
                        ) : (
                            <>
                                <span>✓ Auto-refresh</span>
                                <span className="text-xs opacity-75">{lastRefreshTime.toLocaleTimeString()}</span>
                            </>
                        )}
                    </div>
                </div>

                {/* Summary */}
                {results.length > 0 && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-navy">{results.length}</div>
                            <div className="text-slate-500 text-sm">Total Results</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-green-300">
                                {results.filter(r => r.status === RESULT_STATUS.NATIONALLY_CERTIFIED).length}
                            </div>
                            <div className="text-slate-500 text-sm">Nationally Certified</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-navy">
                                {results.reduce((s, r) => s + (r.total_votes_cast || 0), 0).toLocaleString()}
                            </div>
                            <div className="text-slate-500 text-sm">Total Votes Cast</div>
                        </div>
                        <div className="bg-white rounded-xl p-4 border border-slate-200">
                            <div className="text-2xl font-bold text-iec-pink-600">
                                {results.length > 0
                                    ? (results.reduce((s, r) => s + r.turnout, 0) / results.length).toFixed(1)
                                    : 0}%
                            </div>
                            <div className="text-slate-500 text-sm">Avg Turnout</div>
                        </div>
                    </div>
                )}

                {/* Results */}
                {results.length === 0 ? (
                    <div className="bg-white rounded-xl p-12 border border-slate-200 text-center">
                        <div className="text-5xl mb-4">📊</div>
                        <p className="text-slate-500 text-lg">No results available for your assigned stations yet.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {results.map((result) => {
                            const statusCfg  = getResultStatusMeta(result.status);
                            const isExpanded = expandedId === result.id;
                            const stageIndex = ACTIVE_CERTIFICATION_PIPELINE.findIndex(s => s.key === result.status);
                            const stageProgress = stageIndex >= 0 ? `${stageIndex + 1}/9` : 'N/A';

                            return (
                                <div key={result.id} className="bg-white rounded-xl border border-slate-200 overflow-hidden hover:border-slate-300 transition-colors">
                                    <button
                                        onClick={() => setExpandedId(isExpanded ? null : result.id)}
                                        className="w-full p-5 text-left flex flex-wrap gap-4 justify-between items-start hover:bg-slate-50 transition-colors"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap mb-2">
                                                <h3 className="text-lg font-bold text-iec-navy">{result.station_name}</h3>
                                                <span className="text-xs font-mono text-slate-400">{result.station_code}</span>
                                            </div>
                                            
                                            {/* Enhanced Certification Badge */}
                                            <div className="flex items-center gap-2 mb-2 flex-wrap">
                                                <span className={`px-3 py-1.5 rounded-lg text-xs font-bold border ${statusCfg.badgeClass}`}>
                                                    Stage {stageProgress}
                                                </span>
                                                <span className="text-xs text-slate-500 px-2 py-1 bg-slate-100 rounded">
                                                    {statusCfg.label}
                                                </span>
                                            </div>

                                            <div className="text-sm text-slate-600">
                                                Ward: <span className="font-semibold">{result.ward}</span> — 
                                                Submitted: <span className="font-mono text-xs">{result.submitted_at || 'Pending'}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-4 text-sm">
                                            <div className="text-right">
                                                <div className="text-iec-navy font-bold text-lg">{result.total_votes_cast?.toLocaleString() || '—'}</div>
                                                <div className="text-slate-500 text-xs">Votes Cast</div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-iec-pink-600 font-bold">{result.turnout}%</div>
                                                <div className="text-slate-500 text-xs">Turnout</div>
                                            </div>
                                            <span className="text-slate-500 text-lg">{isExpanded ? '▲' : '▼'}</span>
                                        </div>
                                    </button>

                                    {isExpanded && (
                                        <div className="border-t border-slate-200 p-5 space-y-6">
                                            {/* Certification Stage & Timeline */}
                                            <CertificationStage 
                                                status={result.status}
                                                submitted_at={result.submitted_at}
                                                updated_at={result.updated_at}
                                            />

                                            {/* Vote Statistics */}
                                            <div>
                                                <div className="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-3">Vote Breakdown</div>
                                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                    {[
                                                        { label: 'Valid Votes',    value: result.valid_votes?.toLocaleString(), color: 'text-iec-pink-600' },
                                                        { label: 'Rejected Votes', value: result.rejected_votes?.toLocaleString(), color: 'text-amber-300' },
                                                        { label: 'Votes Cast',     value: result.total_votes_cast?.toLocaleString(), color: 'text-iec-navy' },
                                                        { label: 'Turnout',        value: `${result.turnout}%`, color: 'text-iec-pink-600' },
                                                    ].map(stat => (
                                                        <div key={stat.label} className="bg-white p-3 rounded-lg border border-slate-200">
                                                            <div className="text-xs text-slate-500 mb-1">{stat.label}</div>
                                                            <div className={`font-bold ${stat.color}`}>{stat.value || '—'}</div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            {result.candidate_votes?.length > 0 && (
                                                <div>
                                                    <div className="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-3">Candidate Results</div>
                                                    <div className="space-y-2">
                                                        {result.candidate_votes.map((cv, idx) => (
                                                            <div key={idx} className="flex items-center gap-3">
                                                                <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: cv.party_color }} />
                                                                <span className="text-slate-600 text-sm w-40 truncate">{cv.candidate}</span>
                                                                <span className="text-xs text-slate-500 w-10">{cv.party}</span>
                                                                <div className="flex-1 bg-white rounded-full h-2">
                                                                    <div
                                                                        className="h-2 rounded-full"
                                                                        style={{
                                                                            width: result.valid_votes > 0
                                                                                ? `${(cv.votes / result.valid_votes) * 100}%`
                                                                                : '0%',
                                                                            backgroundColor: cv.party_color,
                                                                        }}
                                                                    />
                                                                </div>
                                                                <span className="text-iec-navy text-sm font-semibold w-16 text-right">
                                                                    {cv.votes?.toLocaleString()}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Submit observation for this station */}
                                            <div className="mt-4 pt-4 border-t border-slate-200">
                                                <Link
                                                    href={`/monitor/submit-observation?station_id=${result.id}`}
                                                    className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600/20 hover:bg-amber-600/30 border border-amber-500/30 text-amber-300 rounded-lg text-sm font-semibold transition-colors"
                                                >
                                                    📝 Submit Observation for this Station
                                                </Link>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Navigation */}
                <div className="mt-8 flex flex-wrap gap-4">
                    <Link href="/monitor/stations" className="px-6 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
                        📍 View My Stations
                    </Link>
                    <Link href="/monitor/submit-observation" className="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                        📝 Submit Observation
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
