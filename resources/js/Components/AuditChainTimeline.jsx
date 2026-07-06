import { useState, useEffect } from "react";

const STAGE_ICONS = {
    polling_officer:      "🗳️",
    ward:                 "🏘️",
    constituency:         "🏛️",
    admin_area:           "📍",
    national:             "🇬🇲",
    party_representative: "🤝",
    election_monitor:     "👁️",
};

const STAGE_LABELS = {
    polling_officer:      "Polling Officer",
    ward:                 "Ward Approver",
    constituency:         "Constituency Approver",
    admin_area:           "Admin Area Approver",
    national:             "IEC Chairman",
    party_representative: "Party Representative",
    election_monitor:     "Election Monitor",
};

const DECISION_STYLES = {
    approved:                  { ring: "ring-green-400",  bg: "bg-green-50",  text: "text-green-800",  label: "Approved" },
    approved_with_reservation: { ring: "ring-yellow-400", bg: "bg-yellow-50", text: "text-yellow-800", label: "Approved (reservation)" },
    rejected:                  { ring: "ring-red-400",    bg: "bg-red-50",    text: "text-red-800",    label: "Rejected" },
    accepted:                  { ring: "ring-green-400",  bg: "bg-green-50",  text: "text-green-800",  label: "Accepted" },
    accepted_with_reservation: { ring: "ring-yellow-400", bg: "bg-yellow-50", text: "text-yellow-800", label: "Accepted (reservation)" },
    certified:                 { ring: "ring-blue-400",   bg: "bg-blue-50",   text: "text-blue-800",   label: "Certified" },
    resubmitted:               { ring: "ring-orange-400", bg: "bg-orange-50", text: "text-orange-800", label: "Resubmitted" },
    default:                   { ring: "ring-gray-300",   bg: "bg-gray-50",   text: "text-gray-700",   label: null },
};

function getDecisionStyle(decision) {
    return DECISION_STYLES[decision] ?? DECISION_STYLES.default;
}

/**
 * AuditChainTimeline
 *
 * Props:
 *   resultId  {number}   – fetches chain from /admin/audit-logs/chain/{resultId}
 *   events    {Array}    – alternatively, pass pre-fetched events directly
 *   compact   {boolean}  – slim single-column layout for sidebar use
 */
export default function AuditChainTimeline({ resultId, events: propEvents = null, compact = false }) {
    const [events, setEvents]   = useState(propEvents ?? []);
    const [loading, setLoading] = useState(!propEvents && !!resultId);
    const [error, setError]     = useState(null);

    useEffect(() => {
        if (propEvents) { setEvents(propEvents); return; }
        if (!resultId)  return;

        setLoading(true);
        fetch(`/admin/audit-logs/chain/${resultId}`, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        })
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(data => { setEvents(data.chain ?? []); setLoading(false); })
            .catch(e => { setError(e.message); setLoading(false); });
    }, [resultId, propEvents]);

    if (loading) {
        return (
            <div className="flex items-center gap-2 py-6 text-gray-400 text-sm justify-center">
                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Loading custody chain…
            </div>
        );
    }

    if (error) {
        return (
            <div className="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                Failed to load chain of custody: {error}
            </div>
        );
    }

    if (events.length === 0) {
        return (
            <div className="text-center py-8 text-gray-400 text-sm">
                No custody events recorded for this result yet.
            </div>
        );
    }

    return (
        <div className={`relative ${compact ? "space-y-3" : "space-y-4"}`}>
            {/* Vertical connector */}
            <div className={`absolute ${compact ? "left-4" : "left-5"} top-5 bottom-5 w-0.5 bg-gray-200 rounded-full`} />

            {events.map((event, idx) => {
                const style = getDecisionStyle(event.decision);
                const isLast = idx === events.length - 1;

                return (
                    <div
                        key={event.id}
                        className={`relative flex gap-3 ${compact ? "pl-10" : "pl-14"}`}
                    >
                        {/* Stage icon bubble */}
                        <div
                            className={`absolute ${compact ? "left-0 w-8 h-8 text-base" : "left-0 w-10 h-10 text-xl"} rounded-full flex items-center justify-center ring-2 ${style.ring} ${style.bg} shadow-sm flex-shrink-0`}
                        >
                            {STAGE_ICONS[event.workflow_stage] ?? "📋"}
                        </div>

                        {/* Card */}
                        <div className={`flex-1 rounded-xl border ${style.ring.replace("ring-", "border-").replace("ring-", "border-")} border-opacity-50 ${style.bg} p-3 min-w-0`}>

                            {/* Header row */}
                            <div className="flex flex-wrap items-center gap-1.5 mb-1.5">
                                <span className={`text-xs font-bold ${style.text}`}>
                                    {STAGE_LABELS[event.workflow_stage] ?? event.workflow_stage ?? "System"}
                                </span>
                                {event.decision && (
                                    <span className={`text-xs px-1.5 py-0.5 rounded-full font-medium ${style.bg} ${style.text} border border-current border-opacity-20`}>
                                        {style.label ?? event.decision.replace(/_/g, " ")}
                                    </span>
                                )}
                                <span className="ml-auto text-xs text-gray-400 whitespace-nowrap">
                                    {event.created_at}
                                </span>
                            </div>

                            {/* Actor */}
                            <p className="text-xs text-gray-600 mb-1.5">
                                <span className="font-medium text-gray-800">{event.user_name}</span>
                                {event.user_role && <span className="text-gray-400"> · {event.user_role}</span>}
                            </p>

                            {/* Description */}
                            {event.description && !compact && (
                                <p className="text-xs text-gray-700 leading-relaxed mb-1.5">
                                    {event.description}
                                </p>
                            )}

                            {/* Comment */}
                            {event.comment_text && (
                                <div className="bg-white/70 rounded-lg px-2.5 py-1.5 mb-1.5 border border-white/50">
                                    <p className="text-xs text-gray-500 font-medium mb-0.5">Comment</p>
                                    <p className="text-xs text-gray-800 italic leading-relaxed">
                                        "{event.comment_text}"
                                    </p>
                                </div>
                            )}

                            {/* Custody arrow */}
                            {!compact && (event.previous_custodian_role || event.next_custodian_role) && (
                                <div className="flex items-center gap-1.5 text-xs text-gray-500 mb-1.5 flex-wrap">
                                    {event.previous_custodian_role && (
                                        <span className="bg-white/60 px-1.5 py-0.5 rounded border border-gray-200">
                                            {event.previous_custodian_role}
                                        </span>
                                    )}
                                    {event.previous_custodian_role && event.next_custodian_role && (
                                        <span className="text-gray-400">→</span>
                                    )}
                                    {event.next_custodian_role && (
                                        <span className="bg-white/60 px-1.5 py-0.5 rounded border border-gray-200 font-medium text-gray-700">
                                            {event.next_custodian_role}
                                        </span>
                                    )}
                                </div>
                            )}

                            {/* Status transition */}
                            {(event.previous_status || event.new_status) && (
                                <div className="flex items-center gap-1 text-xs text-gray-400 flex-wrap">
                                    {event.previous_status && (
                                        <span>{event.previous_status.replace(/_/g, " ")}</span>
                                    )}
                                    {event.previous_status && event.new_status && <span>→</span>}
                                    {event.new_status && (
                                        <span className="font-medium text-gray-600">
                                            {event.new_status.replace(/_/g, " ")}
                                        </span>
                                    )}
                                </div>
                            )}

                            {/* Outcome badge for failures */}
                            {event.outcome && event.outcome !== "success" && (
                                <div className="mt-1.5">
                                    <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${event.outcome === "rejected" || event.outcome === "failure" ? "bg-red-100 text-red-700" : "bg-orange-100 text-orange-700"}`}>
                                        {event.outcome}
                                        {event.failure_reason && `: ${event.failure_reason}`}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}

            {/* Final seal — shows when the last event is a certification */}
            {events.length > 0 && events[events.length - 1]?.decision === "certified" && (
                <div className={`relative ${compact ? "pl-10" : "pl-14"}`}>
                    <div className={`absolute ${compact ? "left-0 w-8 h-8 text-base" : "left-0 w-10 h-10 text-xl"} rounded-full flex items-center justify-center bg-blue-600 text-white shadow-md`}>
                        ✓
                    </div>
                    <div className="rounded-xl border-2 border-blue-500 bg-blue-600 text-white p-3">
                        <p className="text-xs font-bold">Nationally Certified</p>
                        <p className="text-xs opacity-80 mt-0.5">Result is publicly visible and permanently sealed.</p>
                    </div>
                </div>
            )}
        </div>
    );
}