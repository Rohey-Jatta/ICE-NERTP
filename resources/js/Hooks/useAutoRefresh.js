import { useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';

/**
 * useAutoRefresh Hook
 * Automatically refreshes data at specified intervals (default 30 seconds)
 * Preserves scroll position, filters, expanded panels, and other state
 * 
 * Usage:
 * const { isRefreshing, lastRefresh } = useAutoRefresh({
 *   url: '/monitor/observations',
 *   interval: 30000,
 *   preserveScroll: true,
 *   onBeforeRefresh: () => console.log('Refreshing...'),
 *   onAfterRefresh: () => console.log('Refreshed!'),
 * });
 */
export default function useAutoRefresh({
    url = window.location.pathname,
    interval = 30000, // 30 seconds default
    preserveScroll = true,
    preserveState = true,
    enabled = true,
    onBeforeRefresh = null,
    onAfterRefresh = null,
} = {}) {
    const refreshIntervalRef = useRef(null);
    const scrollPositionRef = useRef(null);
    const isRefreshingRef = useRef(false);

    const performRefresh = useCallback(async () => {
        if (isRefreshingRef.current || !enabled) return;

        try {
            isRefreshingRef.current = true;

            // Save scroll position
            if (preserveScroll) {
                scrollPositionRef.current = {
                    x: window.scrollX,
                    y: window.scrollY,
                };
            }

            // Call before refresh callback
            if (onBeforeRefresh) onBeforeRefresh();

            // Get current URL params
            const currentUrl = new URL(window.location.href);
            const params = Object.fromEntries(currentUrl.searchParams);

            // Perform refresh using Inertia router
            router.get(url, params, {
                preserveState: preserveState,
                preserveScroll: false, // We'll handle scroll manually
                only: ['observations', 'results', 'stations', 'dashboard'], // Only refresh relevant data
            });

            // Restore scroll position after a small delay
            if (preserveScroll) {
                setTimeout(() => {
                    window.scrollTo(scrollPositionRef.current.x, scrollPositionRef.current.y);
                }, 100);
            }

            // Call after refresh callback
            if (onAfterRefresh) onAfterRefresh();
        } catch (error) {
            console.error('Auto-refresh failed:', error);
        } finally {
            isRefreshingRef.current = false;
        }
    }, [url, enabled, preserveScroll, preserveState, onBeforeRefresh, onAfterRefresh]);

    // Set up interval on component mount
    useEffect(() => {
        if (!enabled) return;

        // Initial refresh after interval delay
        refreshIntervalRef.current = setInterval(() => {
            performRefresh();
        }, interval);

        // Cleanup on unmount
        return () => {
            if (refreshIntervalRef.current) {
                clearInterval(refreshIntervalRef.current);
            }
        };
    }, [interval, enabled, performRefresh]);

    // Manual refresh trigger
    const triggerRefresh = useCallback(() => {
        performRefresh();
    }, [performRefresh]);

    // Stop refresh
    const stopRefresh = useCallback(() => {
        if (refreshIntervalRef.current) {
            clearInterval(refreshIntervalRef.current);
            refreshIntervalRef.current = null;
        }
    }, []);

    // Resume refresh
    const resumeRefresh = useCallback(() => {
        stopRefresh();
        if (enabled) {
            refreshIntervalRef.current = setInterval(() => {
                performRefresh();
            }, interval);
        }
    }, [enabled, interval, performRefresh, stopRefresh]);

    return {
        isRefreshing: isRefreshingRef.current,
        triggerRefresh,
        stopRefresh,
        resumeRefresh,
        lastRefresh: scrollPositionRef.current,
    };
}

/**
 * Hook for detecting page visibility and auto-stopping refresh
 * Pauses refresh when tab is not visible, resumes when it becomes visible
 */
export function useAutoRefreshWithVisibility({
    url = window.location.pathname,
    interval = 30000,
    preserveScroll = true,
    preserveState = true,
    onBeforeRefresh = null,
    onAfterRefresh = null,
} = {}) {
    const { triggerRefresh, stopRefresh, resumeRefresh } = useAutoRefresh({
        url,
        interval,
        preserveScroll,
        preserveState,
        enabled: !document.hidden,
        onBeforeRefresh,
        onAfterRefresh,
    });

    useEffect(() => {
        const handleVisibilityChange = () => {
            if (document.hidden) {
                stopRefresh();
            } else {
                resumeRefresh();
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };
    }, [stopRefresh, resumeRefresh]);

    return {
        triggerRefresh,
        stopRefresh,
        resumeRefresh,
    };
}
