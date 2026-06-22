import AppLayout from '@/Layouts/AppLayout';
import { useState, useEffect, useCallback } from 'react';
import {
    LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
} from 'recharts';

// ── SVG Ring Progress ─────────────────────────────────────────────────────────
function RingProgress({ percentage = 0, size = 130, strokeWidth = 13 }) {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (Math.min(percentage, 100) / 100) * circumference;

    return (
        <div className="relative flex items-center justify-center" style={{ width: size, height: size }}>
            <svg width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
                <circle cx={size / 2} cy={size / 2} r={radius}
                    fill="none" stroke="#e5e7eb" strokeWidth={strokeWidth} />
                <circle cx={size / 2} cy={size / 2} r={radius}
                    fill="none" stroke="#2563eb" strokeWidth={strokeWidth}
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    style={{ transition: 'stroke-dashoffset 0.6s ease' }}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-2xl font-extrabold text-slate-900">{percentage}%</span>
                <span className="text-[10px] text-slate-500 font-medium">Reporting Rate</span>
            </div>
        </div>
    );
}

// ── Mini Donut Chart ──────────────────────────────────────────────────────────
function MiniDonut({ received = 0, outstanding = 0 }) {
    const total = received + outstanding;
    const pct = total > 0 ? (received / total) * 100 : 0;
    const size = 88;
    const sw = 17;
    const r = (size - sw) / 2;
    const circ = 2 * Math.PI * r;
    const dashOffset = circ - (pct / 100) * circ;

    return (
        <svg width={size} height={size} style={{ transform: 'rotate(-90deg)', flexShrink: 0 }}>
            <circle cx={size / 2} cy={size / 2} r={r}
                fill="none" stroke="#f97316" strokeWidth={sw} />
            <circle cx={size / 2} cy={size / 2} r={r}
                fill="none" stroke="#3b82f6" strokeWidth={sw}
                strokeDasharray={circ} strokeDashoffset={dashOffset}
                strokeLinecap="butt" />
        </svg>
    );
}

// ── Area Progress Bar Row ─────────────────────────────────────────────────────
function AreaRow({ name, rate }) {
    const color = rate >= 80 ? '#16a34a' : rate >= 60 ? '#ca8a04' : rate >= 40 ? '#ea580c' : '#dc2626';
    return (
        <div className="flex items-center gap-2 mb-2">
            <span className="text-[11px] font-medium text-slate-700 truncate"
                style={{ minWidth: 80, maxWidth: 80 }}>
                {name}
            </span>
            <div className="flex-1 bg-gray-200 rounded-full h-3.5 overflow-hidden">
                <div
                    className="h-3.5 rounded-full transition-all duration-700"
                    style={{ width: `${rate}%`, backgroundColor: color }}
                />
            </div>
            <span className="text-[11px] font-bold text-slate-700 text-right" style={{ minWidth: 32 }}>
                {rate}%
            </span>
        </div>
    );
}

// ── Stat Pill ─────────────────────────────────────────────────────────────────
function StatPill({ icon, label, value, bg, textColor }) {
    return (
        <div className={`flex flex-col items-center p-2 rounded-lg ${bg}`}>
            <span className="text-xl mb-0.5">{icon}</span>
            <span className={`text-[10px] font-bold uppercase tracking-wide ${textColor}`}>{label}</span>
            <span className={`text-2xl font-extrabold ${textColor}`}>{value}</span>
        </div>
    );
}

// ── Main Dashboard ────────────────────────────────────────────────────────────
export default function ElectionOperationsDashboard({ auth, data: initialData }) {
    const [data, setData]           = useState(initialData ?? {});
    const [refreshing, setRefreshing] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(new Date());

    const refresh = useCallback(async () => {
        setRefreshing(true);
        try {
            const res = await fetch('/election-operations/data', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                setData(await res.json());
                setLastUpdated(new Date());
                // Bust cache so next Inertia visit is fresh
                if (typeof window !== 'undefined') {
                    window.dispatchEvent(new Event('iec:ops-refreshed'));
                }
            }
        } catch (e) {
            console.warn('[ElectionOps] Refresh failed:', e);
        } finally {
            setRefreshing(false);
        }
    }, []);

    useEffect(() => {
        const id = setInterval(refresh, 30_000);
        return () => clearInterval(id);
    }, [refresh]);

    const p   = data.progress     ?? {};
    const inc = data.incidents    ?? {};
    const ua  = data.userActivity ?? {};

    const byArea         = p.byArea   ?? [];
    const incByArea      = inc.byArea ?? [];
    const loginActivity  = ua.loginActivity ?? [];

    return (
        <AppLayout user={auth?.user}>
            <div className="min-h-screen bg-gray-50 font-sans">

                {/* ── Page Header ─────────────────────────────────────────── */}
                <div className="bg-white border-b border-gray-200 px-6 py-5">
                    <div className="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">
                                Analytics and Reporting
                            </h1>
                            <p className="mt-1 text-slate-500 text-sm max-w-xl">
                                The system provides comprehensive analytics for transparent, data-driven decision making.
                            </p>
                            {data.election && (
                                <div className="mt-2 flex items-center gap-2 flex-wrap">
                                    <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide ${
                                        data.election.status === 'active'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-amber-100 text-amber-800'
                                    }`}>
                                        {data.election.status?.replace(/_/g, ' ')}
                                    </span>
                                    <span className="text-sm font-semibold text-slate-700">{data.election.name}</span>
                                </div>
                            )}
                        </div>
                        <div className="text-right flex-shrink-0">
                            <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold ${
                                refreshing ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'
                            }`}>
                                <span className={`w-2 h-2 rounded-full ${
                                    refreshing ? 'bg-amber-400 animate-pulse' : 'bg-green-500'
                                }`} />
                                {refreshing ? 'Refreshing…' : `Updated ${lastUpdated.toLocaleTimeString()}`}
                            </div>
                            <p className="text-[10px] text-slate-400 mt-1 text-right">Auto-refresh every 30s</p>
                        </div>
                    </div>
                </div>

                {/* ── Operational Reports Bar ─────────────────────────────── */}
                <div className="bg-[#1a237e] text-white px-6 py-3">
                    <div className="max-w-7xl mx-auto flex flex-wrap items-center gap-2">
                        <span className="text-lg" aria-hidden>📋</span>
                        <span className="font-extrabold text-base tracking-wide">OPERATIONAL REPORTS</span>
                        <span className="text-blue-300 mx-1 hidden sm:inline">|</span>
                        <span className="text-blue-200 text-sm hidden sm:inline">
                            Real-time operational insights to support effective election management.
                        </span>
                    </div>
                </div>

                {/* ── Three-Column Grid ────────────────────────────────────── */}
                <div className="max-w-7xl mx-auto px-4 py-5 grid grid-cols-1 lg:grid-cols-3 gap-5">

                    {/* ── Column 1: Election Progress Report ── */}
                    <div className="bg-white rounded-xl border-2 border-blue-200 shadow-sm overflow-hidden flex flex-col">
                        {/* Card header */}
                        <div className="bg-blue-800 text-white px-4 py-3 flex items-center gap-2">
                            <span className="text-xl" aria-hidden>📊</span>
                            <span className="font-extrabold text-sm tracking-widest uppercase">
                                Election Progress Report
                            </span>
                        </div>

                        <div className="p-4 flex flex-col gap-4 flex-1">
                            {/* Displays list */}
                            <div>
                                <p className="text-[10px] font-extrabold text-blue-800 uppercase tracking-widest mb-1">Displays:</p>
                                <ul className="text-xs text-slate-600 space-y-0.5 list-disc list-inside">
                                    <li>Reporting rates</li>
                                    <li>Outstanding stations</li>
                                </ul>
                            </div>

                            {/* Ring + donut */}
                            <div className="flex items-center justify-between gap-2">
                                <RingProgress percentage={p.reportingRate ?? 0} />
                                <div className="flex-1">
                                    <div className="flex items-center gap-2 justify-end">
                                        <MiniDonut
                                            received={p.stationsReceived ?? 0}
                                            outstanding={p.outstandingStations ?? 0}
                                        />
                                    </div>
                                    <div className="flex gap-2 mt-1 justify-end flex-wrap">
                                        <div className="flex items-center gap-1 text-[10px] text-slate-500">
                                            <span className="w-2.5 h-2.5 rounded-sm bg-blue-500 inline-block" />
                                            Received ({(p.stationsReceived ?? 0).toLocaleString()})
                                        </div>
                                        <div className="flex items-center gap-1 text-[10px] text-slate-500">
                                            <span className="w-2.5 h-2.5 rounded-sm bg-orange-500 inline-block" />
                                            Outstanding ({(p.outstandingStations ?? 0).toLocaleString()})
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Reporting Overview table */}
                            <div>
                                <p className="text-[10px] font-extrabold text-blue-800 uppercase tracking-widest mb-2 border-b border-blue-100 pb-1">
                                    Reporting Overview
                                </p>
                                <div className="space-y-1">
                                    {[
                                        { label: 'Total Polling Stations', value: (p.totalStations ?? 0).toLocaleString(), color: 'text-slate-900' },
                                        { label: 'Stations Received', value: (p.stationsReceived ?? 0).toLocaleString(), color: 'text-blue-700 font-bold' },
                                        { label: 'Reporting Rate', value: `${p.reportingRate ?? 0}%`, color: 'text-blue-700 font-bold' },
                                        { label: 'Outstanding Stations', value: (p.outstandingStations ?? 0).toLocaleString(), color: 'text-orange-600 font-bold' },
                                    ].map(row => (
                                        <div key={row.label} className="flex justify-between text-xs">
                                            <span className="text-slate-600">{row.label}</span>
                                            <span className={row.color}>{row.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Progress by Admin Area */}
                            <div className="flex-1">
                                <p className="text-[10px] font-extrabold text-blue-800 uppercase tracking-widest mb-2 border-b border-blue-100 pb-1">
                                    Progress by Administrative Area
                                </p>
                                {byArea.length > 0 ? (
                                    byArea.map(area => (
                                        <AreaRow key={area.name} name={area.name} rate={area.rate} />
                                    ))
                                ) : (
                                    <p className="text-xs text-slate-400 text-center py-4">No area data available yet.</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* ── Column 2: Incident Report ── */}
                    <div className="bg-white rounded-xl border-2 border-green-200 shadow-sm overflow-hidden flex flex-col">
                        <div className="bg-green-800 text-white px-4 py-3 flex items-center gap-2">
                            <span className="text-xl" aria-hidden>🛡️</span>
                            <span className="font-extrabold text-sm tracking-widest uppercase">
                                Incident Report
                            </span>
                        </div>

                        <div className="p-4 flex flex-col gap-4 flex-1">
                            {/* Displays */}
                            <div>
                                <p className="text-[10px] font-extrabold text-green-800 uppercase tracking-widest mb-1">Displays:</p>
                                <ul className="text-xs text-slate-600 space-y-0.5 list-disc list-inside">
                                    <li>Disputes</li>
                                    <li>Rejections</li>
                                    <li>Resubmissions</li>
                                </ul>
                            </div>

                            {/* Incident Summary */}
                            <div>
                                <p className="text-[10px] font-extrabold text-green-800 uppercase tracking-widest mb-2 text-center border-b border-green-100 pb-1">
                                    Incident Summary
                                </p>
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="text-center py-3 px-1 bg-red-50 rounded-xl border border-red-100">
                                        <div className="text-2xl mb-0.5" aria-hidden>👥</div>
                                        <div className="text-[9px] font-extrabold text-red-700 uppercase tracking-wider">Disputes</div>
                                        <div className="text-3xl font-extrabold text-red-600 leading-tight mt-0.5">
                                            {inc.disputes ?? 0}
                                        </div>
                                    </div>
                                    <div className="text-center py-3 px-1 bg-orange-50 rounded-xl border border-orange-100">
                                        <div className="text-2xl mb-0.5" aria-hidden>✕</div>
                                        <div className="text-[9px] font-extrabold text-orange-700 uppercase tracking-wider">Rejections</div>
                                        <div className="text-3xl font-extrabold text-orange-500 leading-tight mt-0.5">
                                            {inc.rejections ?? 0}
                                        </div>
                                    </div>
                                    <div className="text-center py-3 px-1 bg-blue-50 rounded-xl border border-blue-100">
                                        <div className="text-2xl mb-0.5" aria-hidden>🔄</div>
                                        <div className="text-[9px] font-extrabold text-blue-700 uppercase tracking-wider">Resubmissions</div>
                                        <div className="text-3xl font-extrabold text-blue-600 leading-tight mt-0.5">
                                            {inc.resubmissions ?? 0}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Incidents by Area Table */}
                            <div className="flex-1">
                                <p className="text-[10px] font-extrabold text-green-800 uppercase tracking-widest mb-2 border-b border-green-100 pb-1">
                                    Incidents by Administrative Area
                                </p>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs border-collapse">
                                        <thead>
                                            <tr className="bg-green-800 text-white">
                                                <th className="px-2 py-2 text-left font-semibold text-[10px] rounded-tl-md">Administrative Area</th>
                                                <th className="px-2 py-2 text-center font-semibold text-[10px]">Disputes</th>
                                                <th className="px-2 py-2 text-center font-semibold text-[10px]">Rejections</th>
                                                <th className="px-2 py-2 text-center font-semibold text-[10px] rounded-tr-md">Resubmissions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {incByArea.length > 0 ? incByArea.map((area, i) => (
                                                <tr key={area.administrative_area_name}
                                                    className={i % 2 === 0 ? 'bg-white' : 'bg-green-50'}>
                                                    <td className="px-2 py-1.5 font-medium text-slate-700">
                                                        {area.administrative_area_name}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-bold text-red-600">
                                                        {area.disputes}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-bold text-orange-500">
                                                        {area.rejections}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-bold text-blue-600">
                                                        {area.resubmissions}
                                                    </td>
                                                </tr>
                                            )) : (
                                                <tr>
                                                    <td colSpan={4} className="px-2 py-5 text-center text-slate-400 text-[11px]">
                                                        No incidents recorded yet.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                        {incByArea.length > 0 && (
                                            <tfoot>
                                                <tr className="bg-green-100 border-t-2 border-green-300">
                                                    <td className="px-2 py-1.5 font-extrabold text-green-900 text-[11px]">TOTAL</td>
                                                    <td className="px-2 py-1.5 text-center font-extrabold text-red-700">
                                                        {inc.disputes ?? 0}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-extrabold text-orange-600">
                                                        {inc.rejections ?? 0}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-extrabold text-blue-700">
                                                        {inc.resubmissions ?? 0}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        )}
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ── Column 3: User Activity Report ── */}
                    <div className="bg-white rounded-xl border-2 border-purple-200 shadow-sm overflow-hidden flex flex-col">
                        <div className="bg-[#4a148c] text-white px-4 py-3 flex items-center gap-2">
                            <span className="text-xl" aria-hidden>👤</span>
                            <span className="font-extrabold text-sm tracking-widest uppercase">
                                User Activity Report
                            </span>
                        </div>

                        <div className="p-4 flex flex-col gap-4 flex-1">
                            {/* Tracks */}
                            <div>
                                <p className="text-[10px] font-extrabold text-purple-900 uppercase tracking-widest mb-1">Tracks:</p>
                                <ul className="text-xs text-slate-600 space-y-0.5 list-disc list-inside">
                                    <li>Login activity</li>
                                    <li>Submission history</li>
                                    <li>Certification actions</li>
                                </ul>
                            </div>

                            {/* User Activity Overview */}
                            <div>
                                <p className="text-[10px] font-extrabold text-purple-900 uppercase tracking-widest mb-2 border-b border-purple-100 pb-1">
                                    User Activity Overview
                                </p>
                                <div className="grid grid-cols-3 gap-2 text-center">
                                    <div>
                                        <p className="text-[9px] text-slate-500 uppercase tracking-wide font-semibold mb-0.5">Total Users</p>
                                        <p className="text-2xl font-extrabold text-slate-900">
                                            {(ua.totalUsers ?? 0).toLocaleString()}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[9px] text-slate-500 uppercase tracking-wide font-semibold mb-0.5">Total Logins</p>
                                        <p className="text-2xl font-extrabold text-green-700">
                                            {(ua.totalLogins ?? 0).toLocaleString()}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[9px] text-slate-500 uppercase tracking-wide font-semibold mb-0.5">Cert. Actions</p>
                                        <p className="text-2xl font-extrabold text-purple-700">
                                            {(ua.certificationActions ?? 0).toLocaleString()}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Login Activity Chart */}
                            <div>
                                <p className="text-[10px] font-extrabold text-purple-900 uppercase tracking-widest mb-2 border-b border-purple-100 pb-1">
                                    Login Activity (Last 7 Days)
                                </p>
                                <div style={{ height: 130 }}>
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={loginActivity} margin={{ top: 5, right: 5, left: -20, bottom: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#f0e6ff" />
                                            <XAxis dataKey="day" tick={{ fontSize: 10, fill: '#6b7280' }} axisLine={false} tickLine={false} />
                                            <YAxis tick={{ fontSize: 9, fill: '#6b7280' }} axisLine={false} tickLine={false} />
                                            <Tooltip
                                                contentStyle={{ fontSize: 11, borderRadius: 8, border: '1px solid #e9d5ff' }}
                                                labelStyle={{ fontWeight: 700 }}
                                            />
                                            <Line
                                                type="monotone" dataKey="count" name="Logins"
                                                stroke="#7c3aed" strokeWidth={2.5}
                                                dot={{ r: 3.5, fill: '#7c3aed', strokeWidth: 0 }}
                                                activeDot={{ r: 5 }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>

                            {/* Top Actions */}
                            <div className="flex-1">
                                <p className="text-[10px] font-extrabold text-purple-900 uppercase tracking-widest mb-2 border-b border-purple-100 pb-1">
                                    Top Actions Performed
                                </p>
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="text-center bg-blue-50 rounded-xl p-2.5 border border-blue-100">
                                        <div className="text-xl mb-0.5" aria-hidden>📄</div>
                                        <div className="text-[9px] text-slate-500 font-semibold uppercase">Submissions</div>
                                        <div className="text-xl font-extrabold text-blue-700 mt-0.5">
                                            {(ua.submissions ?? 0).toLocaleString()}
                                        </div>
                                    </div>
                                    <div className="text-center bg-green-50 rounded-xl p-2.5 border border-green-100">
                                        <div className="text-xl mb-0.5" aria-hidden>✅</div>
                                        <div className="text-[9px] text-slate-500 font-semibold uppercase">Validations</div>
                                        <div className="text-xl font-extrabold text-green-700 mt-0.5">
                                            {(ua.validations ?? 0).toLocaleString()}
                                        </div>
                                    </div>
                                    <div className="text-center bg-purple-50 rounded-xl p-2.5 border border-purple-100">
                                        <div className="text-xl mb-0.5" aria-hidden>🏆</div>
                                        <div className="text-[9px] text-slate-500 font-semibold uppercase">Certifications</div>
                                        <div className="text-xl font-extrabold text-purple-700 mt-0.5">
                                            {(ua.certifications ?? 0).toLocaleString()}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── Key Benefits ─────────────────────────────────────────── */}
                <div className="bg-slate-100 border-t border-slate-200 px-4 py-4">
                    <div className="max-w-7xl mx-auto flex flex-wrap items-center gap-6">
                        <span className="text-xs font-extrabold text-slate-700 uppercase tracking-widest">
                            Key Benefits
                        </span>
                        {[
                            { icon: '🕐', label: 'Real-time visibility',                        color: 'text-blue-700'   },
                            { icon: '🎯', label: 'Proactive incident management',               color: 'text-green-700'  },
                            { icon: '📈', label: 'Data-driven decision making',                 color: 'text-orange-700' },
                            { icon: '🛡️', label: 'Improved accountability & transparency',      color: 'text-purple-700' },
                            { icon: '👥', label: 'Greater public trust & confidence',            color: 'text-pink-700'   },
                        ].map(b => (
                            <div key={b.label} className="flex items-center gap-2">
                                <span className="text-xl">{b.icon}</span>
                                <span className={`text-[11px] font-semibold ${b.color}`}>{b.label}</span>
                            </div>
                        ))}
                    </div>
                </div>

            </div>
        </AppLayout>
    );
}