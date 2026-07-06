import { useState, useCallback } from "react";
import { router, usePage } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";

// ── Helpers ───────────────────────────────────────────────────────────────────

const MODULE_COLORS = {
    Authentication:    "bg-blue-100 text-blue-800",
    Certification:     "bg-purple-100 text-purple-800",
    Results:           "bg-green-100 text-green-800",
    PartyAcceptance:   "bg-yellow-100 text-yellow-800",
    ElectionMonitor:   "bg-orange-100 text-orange-800",
    ElectionManagement:"bg-indigo-100 text-indigo-800",
    UserManagement:    "bg-pink-100 text-pink-800",
    DeviceManagement:  "bg-teal-100 text-teal-800",
    PollingStation:    "bg-cyan-100 text-cyan-800",
    PartyManagement:   "bg-rose-100 text-rose-800",
    System:            "bg-gray-100 text-gray-700",
};

const DECISION_COLORS = {
    approved:                    "bg-green-100 text-green-800",
    approved_with_reservation:   "bg-yellow-100 text-yellow-800",
    rejected:                    "bg-red-100 text-red-800",
    accepted:                    "bg-green-100 text-green-800",
    accepted_with_reservation:   "bg-yellow-100 text-yellow-800",
    certified:                   "bg-blue-100 text-blue-800",
    resubmitted:                 "bg-orange-100 text-orange-800",
};

const OUTCOME_COLORS = {
    success: "text-green-800",
    failure: "text-red-800",
    blocked: "text-orange-800",
};

const STAGE_LABELS = {
    polling_officer:    "Polling Officer",
    ward:               "Ward",
    constituency:       "Constituency",
    admin_area:         "Admin Area",
    national:           "National",
    party_representative:"Party Rep",
    election_monitor:   "Monitor",
};

function Badge({ text, colorClass, small = false }) {
    if (!text) return null;
    return (
        <span className={`inline-flex items-center rounded-full font-medium ${small ? "px-2 py-0.5 text-xs" : "px-2.5 py-1 text-xs"} ${colorClass ?? "bg-gray-100 text-gray-800"}`}>
            {text}
        </span>
    );
}

function FilterInput({ label, value, onChange, placeholder }) {
    return (
        <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-800 uppercase tracking-wide">{label}</label>
            <input
                type="text"
                value={value}
                onChange={e => onChange(e.target.value)}
                placeholder={placeholder ?? `Filter by ${label.toLowerCase()}…`}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition"
            />
        </div>
    );
}

function FilterSelect({ label, value, onChange, options, placeholder }) {
    return (
        <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-800 uppercase tracking-wide">{label}</label>
            <select
                value={value}
                onChange={e => onChange(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white"
            >
                <option value="">{placeholder ?? `All ${label}`}</option>
                {options.map(o => (
                    <option key={o.value ?? o} value={o.value ?? o}>
                        {o.label ?? o}
                    </option>
                ))}
            </select>
        </div>
    );
}

// ── Chain-of-Custody Timeline Modal ──────────────────────────────────────────

function ChainModal({ resultId, onClose }) {
    const [chain, setChain] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Fetch on mount
    useState(() => {
        if (!resultId) return;
        fetch(`/admin/audit-logs/chain/${resultId}`, {
            headers: { Accept: "application/json" },
        })
            .then(r => r.json())
            .then(data => { setChain(data.chain ?? []); setLoading(false); })
            .catch(() => { setError("Failed to load chain of custody."); setLoading(false); });
    }, [resultId]);

    const stageIcon = (stage) => {
        const icons = {
            polling_officer:     "🗳️",
            ward:                "🏘️",
            constituency:        "🏛️",
            admin_area:          "📍",
            national:            "🇬🇲",
            party_representative:"🤝",
            election_monitor:    "👁️",
        };
        return icons[stage] ?? "📋";
    };

    const decisionColor = (decision) => {
        if (!decision) return "border-gray-300 bg-gray-50";
        if (decision === "approved" || decision === "certified" || decision === "accepted") return "border-green-400 bg-green-50";
        if (decision.includes("reservation")) return "border-yellow-400 bg-yellow-50";
        if (decision === "rejected") return "border-red-400 bg-red-50";
        if (decision === "resubmitted") return "border-blue-400 bg-blue-50";
        return "border-gray-300 bg-gray-50";
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">Chain of Custody</h2>
                        <p className="text-sm text-gray-800">Result ID: {resultId}</p>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-gray-800 hover:text-gray-900 transition text-2xl leading-none"
                        aria-label="Close"
                    >
                        ×
                    </button>
                </div>

                {/* Body */}
                <div className="overflow-y-auto flex-1 px-6 py-4">
                    {loading && (
                        <div className="flex items-center justify-center py-12 text-gray-700 text-sm gap-2">
                            <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Loading custody chain…
                        </div>
                    )}

                    {error && (
                        <div className="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                            {error}
                        </div>
                    )}

                    {!loading && !error && chain?.length === 0 && (
                        <div className="text-center py-12 text-gray-800 text-sm">
                            No audit events found for this result.
                        </div>
                    )}

                    {!loading && !error && chain?.length > 0 && (
                        <div className="relative">
                            {/* Vertical line */}
                            <div className="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200" />

                            <ol className="space-y-4">
                                {chain.map((event, idx) => (
                                    <li key={event.id} className="relative pl-14">
                                        {/* Node icon */}
                                        <div className={`absolute left-0 w-12 h-12 rounded-full flex items-center justify-center text-xl border-2 ${decisionColor(event.decision)} shadow-sm`}>
                                            {stageIcon(event.workflow_stage)}
                                        </div>

                                        <div className={`rounded-xl border-2 p-4 ${decisionColor(event.decision)}`}>
                                            {/* Top row */}
                                            <div className="flex flex-wrap items-start gap-2 mb-2">
                                                <span className="font-semibold text-sm text-gray-900">
                                                    {event.user_name}
                                                </span>
                                                {event.user_role && (
                                                    <Badge text={event.user_role} colorClass="bg-white/70 text-gray-800 border border-gray-200" small />
                                                )}
                                                {event.workflow_stage && (
                                                    <Badge
                                                        text={STAGE_LABELS[event.workflow_stage] ?? event.workflow_stage}
                                                        colorClass="bg-indigo-100 text-indigo-800"
                                                        small
                                                    />
                                                )}
                                                {event.decision && (
                                                    <Badge
                                                        text={event.decision.replace(/_/g, " ")}
                                                        colorClass={DECISION_COLORS[event.decision] ?? "bg-gray-100 text-gray-800"}
                                                        small
                                                    />
                                                )}
                                            </div>

                                            {/* Description */}
                                            {event.description && (
                                                <p className="text-sm text-gray-800 mb-2 leading-relaxed">
                                                    {event.description}
                                                </p>
                                            )}

                                            {/* Comment */}
                                            {event.comment_text && (
                                                <div className="bg-white/60 border border-current/20 rounded-lg px-3 py-2 mb-2">
                                                    <p className="text-xs font-medium text-gray-700 mb-0.5">Comment</p>
                                                    <p className="text-sm text-gray-800 italic">"{event.comment_text}"</p>
                                                </div>
                                            )}

                                            {/* Custody arrow */}
                                            {(event.previous_custodian_role || event.next_custodian_role) && (
                                                <div className="flex items-center gap-2 text-xs text-gray-800 mb-2">
                                                    {event.previous_custodian_role && (
                                                        <span className="bg-white/70 px-2 py-0.5 rounded border border-gray-200">
                                                            {event.previous_custodian_role}
                                                        </span>
                                                    )}
                                                    {event.previous_custodian_role && event.next_custodian_role && (
                                                        <span className="text-gray-700">→</span>
                                                    )}
                                                    {event.next_custodian_role && (
                                                        <span className="bg-white/70 px-2 py-0.5 rounded border border-gray-200 font-medium text-gray-700">
                                                            {event.next_custodian_role}
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {/* Status transition */}
                                            {(event.previous_status || event.new_status) && (
                                                <div className="flex items-center gap-2 text-xs text-gray-800 mb-2">
                                                    <span className="font-medium">Status:</span>
                                                    {event.previous_status && <span className="bg-white/60 px-2 py-0.5 rounded">{event.previous_status.replace(/_/g, " ")}</span>}
                                                    {event.previous_status && event.new_status && <span>→</span>}
                                                    {event.new_status && <span className="bg-white/60 px-2 py-0.5 rounded font-medium text-gray-800">{event.new_status.replace(/_/g, " ")}</span>}
                                                </div>
                                            )}

                                            {/* Footer metadata */}
                                            <div className="flex flex-wrap items-center gap-3 text-xs text-gray-700 mt-1">
                                                <span>{event.created_at}</span>
                                                {event.ip_address && <span>IP: {event.ip_address}</span>}
                                                {event.os && <span>{event.os} / {event.browser}</span>}
                                                {event.outcome && event.outcome !== "success" && (
                                                    <span className={`font-medium ${OUTCOME_COLORS[event.outcome] ?? ""}`}>
                                                        {event.outcome}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="px-6 py-4 border-t border-gray-100 flex justify-end">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Expandable Row Detail ─────────────────────────────────────────────────────

function RowDetail({ log }) {
    return (
        <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            {/* Description */}
            {log.description && (
                <div className="md:col-span-2">
                    <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Full Description</p>
                    <p className="text-gray-800 leading-relaxed bg-white border border-gray-200 rounded-lg px-3 py-2">
                        {log.description}
                    </p>
                </div>
            )}

            {/* Comment */}
            {log.comment_text && (
                <div className="md:col-span-2">
                    <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Comment (Exact)</p>
                    <p className="text-gray-800 italic bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2">
                        "{log.comment_text}"
                    </p>
                </div>
            )}

            {/* Chain of custody */}
            {(log.previous_custodian_role || log.current_custodian_role || log.next_custodian_role) && (
                <div>
                    <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Custody Transfer</p>
                    <div className="flex items-center gap-2 flex-wrap">
                        {log.previous_custodian_role && <span className="bg-gray-200 text-gray-700 px-2 py-0.5 rounded text-xs">{log.previous_custodian_role}</span>}
                        {log.previous_custodian_role && <span className="text-gray-800 text-xs">→</span>}
                        {log.current_custodian_role && <span className="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-medium">{log.current_custodian_role}</span>}
                        {log.next_custodian_role && <span className="text-gray-800 text-xs">→</span>}
                        {log.next_custodian_role && <span className="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs">{log.next_custodian_role}</span>}
                    </div>
                </div>
            )}

            {/* Status transition */}
            {(log.previous_status || log.new_status) && (
                <div>
                    <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Status Change</p>
                    <div className="flex items-center gap-2 flex-wrap text-xs">
                        {log.previous_status && <span className="bg-gray-200 text-gray-700 px-2 py-0.5 rounded">{log.previous_status.replace(/_/g, " ")}</span>}
                        {log.previous_status && log.new_status && <span className="text-gray-800">→</span>}
                        {log.new_status && <span className="bg-green-100 text-green-800 px-2 py-0.5 rounded font-medium">{log.new_status.replace(/_/g, " ")}</span>}
                    </div>
                </div>
            )}

            {/* Before / after values */}
            {(log.old_values || log.new_values) && (
                <div className="md:col-span-2 grid grid-cols-2 gap-3">
                    {log.old_values && (
                        <div>
                            <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Before</p>
                            <pre className="bg-red-50 border border-red-100 rounded-lg px-3 py-2 text-xs overflow-auto text-red-800 whitespace-pre-wrap">
                                {JSON.stringify(log.old_values, null, 2)}
                            </pre>
                        </div>
                    )}
                    {log.new_values && (
                        <div>
                            <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">After</p>
                            <pre className="bg-green-50 border border-green-100 rounded-lg px-3 py-2 text-xs overflow-auto text-green-800 whitespace-pre-wrap">
                                {JSON.stringify(log.new_values, null, 2)}
                            </pre>
                        </div>
                    )}
                </div>
            )}

            {/* Device & request metadata */}
            <div>
                <p className="text-xs font-semibold text-gray-800 uppercase tracking-wide mb-1">Device / Request</p>
                <div className="space-y-0.5 text-xs text-gray-700">
                    {log.ip_address && <p>IP: <span className="font-mono">{log.ip_address}</span></p>}
                    {log.os && <p>OS: {log.os}</p>}
                    {log.browser && <p>Browser: {log.browser}</p>}
                    {log.device_type && <p>Device: {log.device_type}</p>}
                </div>
            </div>

            {/* Outcome */}
            {log.failure_reason && (
                <div>
                    <p className="text-xs font-semibold text-gray-800 uppercase tracking-wide mb-1">Failure Reason</p>
                    <p className="text-red-700 text-xs bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                        {log.failure_reason}
                    </p>
                </div>
            )}

            {/* Correlation ID */}
            {log.correlation_id && (
                <div className="md:col-span-2">
                    <p className="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Correlation ID</p>
                    <p className="font-mono text-xs text-gray-500 bg-gray-100 px-3 py-1.5 rounded-lg">{log.correlation_id}</p>
                </div>
            )}
        </div>
    );
}

// ── Main Page ─────────────────────────────────────────────────────────────────

export default function AuditLogs({ auth, logs, filters, modules, workflowStages, decisions, elections }) {
    const [expandedRow, setExpandedRow]     = useState(null);
    const [chainResultId, setChainResultId] = useState(null);
    const [localFilters, setLocalFilters]   = useState({
        search:             filters.search             ?? "",
        module:             filters.module             ?? "",
        action:             filters.action             ?? "",
        workflow_stage:     filters.workflow_stage     ?? "",
        decision:           filters.decision           ?? "",
        election_id:        filters.election_id        ?? "",
        polling_station_id: filters.polling_station_id ?? "",
        date_from:          filters.date_from          ?? "",
        date_to:            filters.date_to            ?? "",
        outcome:            filters.outcome            ?? "",
    });
    const [filtersOpen, setFiltersOpen] = useState(false);

    const setFilter = (key, value) => setLocalFilters(prev => ({ ...prev, [key]: value }));

    const applyFilters = useCallback(() => {
        const params = {};
        Object.entries(localFilters).forEach(([k, v]) => { if (v) params[k] = v; });
        router.get("/admin/audit-logs", params, { preserveState: true, replace: true });
    }, [localFilters]);

    const clearFilters = () => {
        const empty = Object.fromEntries(Object.keys(localFilters).map(k => [k, ""]));
        setLocalFilters(empty);
        router.get("/admin/audit-logs", {}, { preserveState: false });
    };

    const activeFilterCount = Object.values(localFilters).filter(Boolean).length;

    const toggleRow = (id) => setExpandedRow(prev => (prev === id ? null : id));

    return (
        <AppLayout auth={auth} title="Audit Logs">
            <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

                {/* Page header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Audit Trail</h1>
                        <p className="text-sm text-gray-700 mt-1">
                            Forensic, append-only record of every action in the system.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        
                        <a href={`/admin/audit-logs/export?${new URLSearchParams(
                                Object.fromEntries(Object.entries(localFilters).filter(([,v]) => v))
                            )}`}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 bg-white text-sm text-gray-700 hover:bg-gray-50 transition shadow-sm"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            </svg>
                            Export CSV
                        </a>
                        <button
                            onClick={() => setFiltersOpen(p => !p)}
                            className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition shadow-sm ${filtersOpen ? "bg-blue-600 border-blue-600 text-white" : "bg-white border-gray-200 text-gray-700 hover:bg-gray-50"}`}
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                            </svg>
                            Filters
                            {activeFilterCount > 0 && (
                                <span className="bg-white text-blue-700 rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                                    {activeFilterCount}
                                </span>
                            )}
                        </button>
                    </div>
                </div>

                {/* Filter panel */}
                {filtersOpen && (
                    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            <FilterInput
                                label="Search"
                                value={localFilters.search}
                                onChange={v => setFilter("search", v)}
                                placeholder="Description, action, IP, user…"
                            />
                            <FilterSelect
                                label="Module"
                                value={localFilters.module}
                                onChange={v => setFilter("module", v)}
                                options={modules?.map(m => ({ value: m, label: m })) ?? []}
                            />
                            <FilterInput
                                label="Action"
                                value={localFilters.action}
                                onChange={v => setFilter("action", v)}
                                placeholder="e.g. result.submitted"
                            />
                            <FilterSelect
                                label="Workflow Stage"
                                value={localFilters.workflow_stage}
                                onChange={v => setFilter("workflow_stage", v)}
                                options={
                                    (workflowStages ?? []).map(s => ({
                                        value: s,
                                        label: STAGE_LABELS[s] ?? s,
                                    }))
                                }
                            />
                            <FilterSelect
                                label="Decision"
                                value={localFilters.decision}
                                onChange={v => setFilter("decision", v)}
                                options={(decisions ?? []).map(d => ({ value: d, label: d.replace(/_/g, " ") }))}
                            />
                            <FilterSelect
                                label="Election"
                                value={localFilters.election_id}
                                onChange={v => setFilter("election_id", v)}
                                options={(elections ?? []).map(e => ({ value: e.id, label: e.name }))}
                                placeholder="All Elections"
                            />
                            <FilterInput
                                label="Polling Station ID"
                                value={localFilters.polling_station_id}
                                onChange={v => setFilter("polling_station_id", v)}
                                placeholder="Station ID"
                            />
                            <FilterSelect
                                label="Outcome"
                                value={localFilters.outcome}
                                onChange={v => setFilter("outcome", v)}
                                options={[
                                    { value: "success", label: "Success" },
                                    { value: "failure", label: "Failure" },
                                    { value: "blocked", label: "Blocked" },
                                ]}
                            />
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-medium text-gray-800 uppercase tracking-wide">Date From</label>
                                <input
                                    type="date"
                                    value={localFilters.date_from}
                                    onChange={e => setFilter("date_from", e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition"
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-medium text-gray-600 uppercase tracking-wide">Date To</label>
                                <input
                                    type="date"
                                    value={localFilters.date_to}
                                    onChange={e => setFilter("date_to", e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none transition"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-3 pt-1">
                            <button
                                onClick={applyFilters}
                                className="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition shadow-sm"
                            >
                                Apply Filters
                            </button>
                            {activeFilterCount > 0 && (
                                <button
                                    onClick={clearFilters}
                                    className="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 text-sm hover:bg-gray-50 transition"
                                >
                                    Clear All
                                </button>
                            )}
                            <span className="text-xs text-gray-700 ml-auto">
                                {logs?.total?.toLocaleString() ?? 0} record{logs?.total !== 1 ? "s" : ""}
                            </span>
                        </div>
                    </div>
                )}

                {/* Summary stats */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { label: "Total Events",  value: logs?.total?.toLocaleString() ?? 0,        color: "text-gray-900" },
                        { label: "Approvals",     value: logs?.data?.filter(l => l.decision === "approved").length ?? "—", color: "text-green-700" },
                        { label: "Rejections",    value: logs?.data?.filter(l => l.decision === "rejected").length ?? "—", color: "text-red-700"   },
                        { label: "Auth Events",   value: logs?.data?.filter(l => l.module === "Authentication").length ?? "—", color: "text-blue-700" },
                    ].map(stat => (
                        <div key={stat.label} className="bg-white rounded-xl border border-gray-200 px-4 py-3 shadow-sm">
                            <p className="text-xs text-gray-800 font-medium">{stat.label}</p>
                            <p className={`text-xl font-bold mt-0.5 ${stat.color}`}>{stat.value}</p>
                        </div>
                    ))}
                </div>

                {/* Table */}
                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    {(!logs?.data || logs.data.length === 0) ? (
                        <div className="flex flex-col items-center justify-center py-20 text-gray-800 gap-3">
                            <svg className="w-12 h-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p className="text-sm font-medium">No audit records found</p>
                            {activeFilterCount > 0 && (
                                <button onClick={clearFilters} className="text-sm text-blue-600 hover:underline">
                                    Clear filters
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-gray-50 border-b border-gray-200">
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-8"/>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Timestamp</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Module</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Stage</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Decision</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Outcome</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Chain</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {logs.data.map(log => (
                                        <>
                                            <tr
                                                key={log.id}
                                                className={`hover:bg-gray-50 transition cursor-pointer ${expandedRow === log.id ? "bg-blue-50" : ""}`}
                                                onClick={() => toggleRow(log.id)}
                                            >
                                                {/* Expand toggle */}
                                                <td className="px-4 py-3 text-center">
                                                    <span className={`text-gray-800 transition-transform inline-block ${expandedRow === log.id ? "rotate-90" : ""}`}>
                                                        ▶
                                                    </span>
                                                </td>

                                                {/* Timestamp */}
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <p className="text-xs font-mono text-gray-700">{log.created_at}</p>
                                                    <p className="text-xs text-gray-800">{log.created_at_human}</p>
                                                </td>

                                                {/* User */}
                                                <td className="px-4 py-3">
                                                    <p className="font-medium text-gray-900 text-xs">{log.user_name}</p>
                                                    {log.user_role && (
                                                        <p className="text-xs text-gray-700">{log.user_role}</p>
                                                    )}
                                                </td>

                                                {/* Module */}
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        text={log.module}
                                                        colorClass={MODULE_COLORS[log.module] ?? "bg-gray-100 text-gray-800"}
                                                        small
                                                    />
                                                </td>

                                                {/* Action */}
                                                <td className="px-4 py-3">
                                                    <span className="font-mono text-xs text-gray-700 bg-gray-100 px-2 py-0.5 rounded">
                                                        {log.action}
                                                    </span>
                                                </td>

                                                {/* Stage */}
                                                <td className="px-4 py-3">
                                                    {log.workflow_stage && (
                                                        <Badge
                                                            text={STAGE_LABELS[log.workflow_stage] ?? log.workflow_stage}
                                                            colorClass="bg-indigo-100 text-indigo-800"
                                                            small
                                                        />
                                                    )}
                                                </td>

                                                {/* Decision */}
                                                <td className="px-4 py-3">
                                                    {log.decision && (
                                                        <Badge
                                                            text={log.decision.replace(/_/g, " ")}
                                                            colorClass={DECISION_COLORS[log.decision] ?? "bg-gray-100 text-gray-800"}
                                                            small
                                                        />
                                                    )}
                                                </td>

                                                {/* Description — truncated */}
                                                <td className="px-4 py-3 max-w-xs">
                                                    <p className="text-xs text-gray-800 line-clamp-2 leading-relaxed">
                                                        {log.description ?? "—"}
                                                    </p>
                                                </td>

                                                {/* Outcome */}
                                                <td className="px-4 py-3">
                                                    <span className={`text-xs font-medium ${OUTCOME_COLORS[log.outcome] ?? "text-gray-800"}`}>
                                                        {log.outcome ?? "—"}
                                                    </span>
                                                </td>

                                                {/* Chain of custody button */}
                                                <td className="px-4 py-3" onClick={e => e.stopPropagation()}>
                                                    {log.result_id && (
                                                        <button
                                                            onClick={() => setChainResultId(log.result_id)}
                                                            className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 text-xs font-medium hover:bg-purple-100 transition border border-purple-200"
                                                            title="View full chain of custody"
                                                        >
                                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                            </svg>
                                                            Chain
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>

                                            {/* Expanded detail row */}
                                            {expandedRow === log.id && (
                                                <tr key={`${log.id}-detail`}>
                                                    <td colSpan={10} className="p-0">
                                                        <RowDetail log={log} />
                                                    </td>
                                                </tr>
                                            )}
                                        </>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {logs?.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-sm text-gray-600">
                            <span>
                                Page {logs.current_page} of {logs.last_page} &nbsp;·&nbsp; {logs.total?.toLocaleString()} total records
                            </span>
                            <div className="flex gap-2">
                                {logs.prev_page_url && (
                                    <button
                                        onClick={() => router.get(logs.prev_page_url, localFilters, { preserveState: true })}
                                        className="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition text-xs font-medium"
                                    >
                                        ← Previous
                                    </button>
                                )}
                                {/* Page number pills — show at most 7 */}
                                {Array.from({ length: logs.last_page }, (_, i) => i + 1)
                                    .filter(p => p === 1 || p === logs.last_page || Math.abs(p - logs.current_page) <= 2)
                                    .reduce((acc, p, i, arr) => {
                                        if (i > 0 && p - arr[i - 1] > 1) acc.push("…");
                                        acc.push(p);
                                        return acc;
                                    }, [])
                                    .map((p, i) =>
                                        p === "…" ? (
                                            <span key={`ellipsis-${i}`} className="px-2 py-1.5 text-xs text-gray-400">…</span>
                                        ) : (
                                            <button
                                                key={p}
                                                onClick={() => router.get(logs.path + "?page=" + p, Object.fromEntries(Object.entries(localFilters).filter(([,v]) => v)), { preserveState: true })}
                                                className={`w-8 h-8 rounded-lg text-xs font-medium transition ${p === logs.current_page ? "bg-blue-600 text-white shadow-sm" : "border border-gray-200 hover:bg-gray-50 text-gray-700"}`}
                                            >
                                                {p}
                                            </button>
                                        )
                                    )
                                }
                                {logs.next_page_url && (
                                    <button
                                        onClick={() => router.get(logs.next_page_url, localFilters, { preserveState: true })}
                                        className="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition text-xs font-medium"
                                    >
                                        Next →
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Chain-of-custody modal */}
            {chainResultId && (
                <ChainModal
                    resultId={chainResultId}
                    onClose={() => setChainResultId(null)}
                />
            )}
        </AppLayout>
    );
}