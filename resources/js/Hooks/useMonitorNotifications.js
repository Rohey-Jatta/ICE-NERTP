import { useEffect, useRef } from 'react';

/**
 * useMonitorNotifications Hook
 * Detects data changes and triggers appropriate notifications
 * 
 * Usage:
 * useMonitorNotifications({
 *   observations: observationsList,
 *   results: resultsList,
 *   onNotify: (message, type) => {},
 * });
 */
export function useMonitorNotifications({
    observations = [],
    results = [],
    onNotify = () => {},
} = {}) {
    const prevObservationsRef = useRef([]);
    const prevResultsRef = useRef([]);

    useEffect(() => {
        // Check for new observations
        if (observations.length > prevObservationsRef.current.length) {
            const newCount = observations.length - prevObservationsRef.current.length;
            const newObs = observations.slice(-newCount)[0];

            if (newObs) {
                const severityEmoji = {
                    low: '🟢',
                    medium: '🟡',
                    high: '🟠',
                    critical: '🔴',
                }[newObs.severity] || '📝';

                onNotify(
                    `${severityEmoji} New observation: ${newObs.title}`,
                    newObs.severity === 'critical' ? 'warning' : 'info'
                );
            }
        }
        prevObservationsRef.current = observations;
    }, [observations, onNotify]);

    useEffect(() => {
        // Check for result status changes
        const prevResultsById = prevResultsRef.current.reduce((acc, r) => {
            acc[r.id] = r;
            return acc;
        }, {});

        results.forEach((result) => {
            const prevResult = prevResultsById[result.id];
            
            if (prevResult && prevResult.certification_status !== result.certification_status) {
                const statusEmoji = {
                    submitted: '📤',
                    pending_ward: '👮',
                    ward_certified: '✅',
                    pending_constituency: '👨‍💼',
                    constituency_certified: '✅',
                    pending_admin_area: '🏛️',
                    admin_area_certified: '✅',
                    pending_national: '🏦',
                    nationally_certified: '🎉',
                }[result.certification_status] || '📊';

                const statusLabel = result.certification_status
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (l) => l.toUpperCase());

                onNotify(
                    `${statusEmoji} Result ${result.station_code} status updated to ${statusLabel}`,
                    result.certification_status === 'nationally_certified' ? 'success' : 'info'
                );
            }
        });

        prevResultsRef.current = results;
    }, [results, onNotify]);
}

/**
 * usePollNotifications Hook
 * Triggers notifications on specific poll-related events
 */
export function usePollNotifications(events = [], onNotify = () => {}) {
    const prevEventsRef = useRef([]);

    useEffect(() => {
        const newEvents = events.filter(
            (e) => !prevEventsRef.current.find((pe) => pe.id === e.id)
        );

        newEvents.forEach((event) => {
            const messages = {
                result_approved: '✅ Result approved by reviewer',
                result_rejected: '❌ Result rejected - please review feedback',
                observation_flagged: '🚩 Your observation has been flagged',
                certification_stage_change: '📊 Certification stage updated',
                document_verified: '✓ Supporting document verified',
                pdf_generated: '📄 PDF report generated successfully',
            };

            const notificationType = event.type.includes('rejected')
                ? 'error'
                : event.type.includes('approved')
                ? 'success'
                : 'info';

            onNotify(messages[event.type] || event.message, notificationType);
        });

        prevEventsRef.current = events;
    }, [events, onNotify]);
}
