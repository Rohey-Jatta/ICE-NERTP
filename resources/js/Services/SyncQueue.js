import { offlineSync } from './OfflineSync';

/**
 * SyncQueue - Background sync manager.
 * 
 * From architecture: resources/js/Services/SyncQueue.js
 * 
 * Monitors online/offline status and syncs pending submissions.
 * Uses Background Sync API when available, polling fallback.
 *
 * Configuration (background_sync_interval_minutes, max_offline_retries)
 * comes from the admin-configured global settings, bridged in from React
 * via configure() — see resources/js/Layouts/AppLayout.jsx, which reads
 * usePage().props.settings (shared globally by HandleInertiaRequests) and
 * calls syncQueue.configure(settings) whenever it changes.
 */

// Fallback defaults — used until configure() is called with real settings,
// and mirror App\Services\SettingsService::DEFAULTS on the backend.
const DEFAULT_INTERVAL_MINUTES = 5;
const DEFAULT_MAX_RETRIES = 3;

class SyncQueue {
    constructor() {
        this.isSyncing = false;
        this.syncCallbacks = [];
        this._initialized = false;
        this._intervalId = null;
        this.intervalMinutes = DEFAULT_INTERVAL_MINUTES;
        this.maxRetries = DEFAULT_MAX_RETRIES;
    }

    init() {
        // Guard against double-init — AppLayout can remount across
        // navigations, but listeners/timers should only be registered once
        // per page session.
        if (this._initialized) return;
        this._initialized = true;

        // Listen for online/offline events, but never block navigation or UI
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Register service worker sync if available (background only)
        if ('serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype) {
            this.registerBackgroundSync();
        }

        // Start the interval-based fallback sync loop using whatever
        // interval is currently configured (default until configure()
        // is called with the admin's actual setting).
        this.startPeriodicSync();

        // Never block navigation or UI on sync; all syncs are background-only
        // Do not trigger syncPendingSubmissions on init; only on explicit background events
    }

    /**
     * Apply admin-configured PWA & Offline Sync settings.
     *
     * @param {object} settings
     * @param {number} [settings.background_sync_interval_minutes]
     * @param {number} [settings.max_offline_retries]
     */
    configure(settings = {}) {
        const interval = Number(settings.background_sync_interval_minutes);
        const maxRetries = Number(settings.max_offline_retries);

        const nextIntervalMinutes = Number.isFinite(interval) && interval > 0
            ? interval
            : DEFAULT_INTERVAL_MINUTES;

        const nextMaxRetries = Number.isFinite(maxRetries) && maxRetries > 0
            ? maxRetries
            : DEFAULT_MAX_RETRIES;

        this.maxRetries = nextMaxRetries;

        // Only restart the timer if the interval actually changed, to
        // avoid resetting the countdown on every unrelated re-render.
        if (nextIntervalMinutes !== this.intervalMinutes) {
            this.intervalMinutes = nextIntervalMinutes;
            if (this._initialized) {
                this.startPeriodicSync();
            }
        }
    }

    /**
     * (Re)start the setInterval-based background sync loop. This is the
     * practical fallback to the browser Background Sync API, which has
     * inconsistent support and requires an active service worker
     * registration; polling on an interval works everywhere.
     */
    startPeriodicSync() {
        if (this._intervalId) {
            clearInterval(this._intervalId);
            this._intervalId = null;
        }

        const intervalMs = this.intervalMinutes * 60 * 1000;

        this._intervalId = setInterval(() => {
            if (navigator.onLine) {
                this.syncPendingSubmissions();
            }
        }, intervalMs);
    }

    async registerBackgroundSync() {
        try {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register('sync-results');
            console.log('Background sync registered');
        } catch (error) {
            console.error('Background sync registration failed:', error);
        }
    }

    handleOnline() {
        console.log('Connection restored - syncing pending submissions (background only)');
        // Only trigger background sync, never block UI
        this.syncPendingSubmissions();
    }

    handleOffline() {
        console.log('Connection lost - submissions will be queued');
    }

    async syncPendingSubmissions() {
        if (this.isSyncing) return;

        this.isSyncing = true;

        try {
            const pending = await offlineSync.getPendingSubmissions();
            console.log(`Found ${pending.length} pending submissions`);

            // Sync in background, never block UI or navigation
            for (const submission of pending) {
                this.syncSubmission(submission).catch(() => {});
            }

            // Clean up synced submissions older than 7 days
            await offlineSync.clearSyncedSubmissions();

            this.notifyCallbacks('sync_complete', pending.length);
        } catch (error) {
            console.error('Sync failed:', error);
            this.notifyCallbacks('sync_error', error);
        } finally {
            this.isSyncing = false;
        }
    }

    async syncSubmission(submission) {
        try {
            const formData = new FormData();

            // Append all fields
            Object.keys(submission).forEach(key => {
                if (key === 'candidate_votes') {
                    formData.append('candidate_votes', JSON.stringify(submission[key]));
                } else if (key === 'result_sheet_photo' && submission[key]) {
                    // Photo is stored as base64, convert back to blob
                    const blob = this.base64ToBlob(submission[key].data, submission[key].type);
                    formData.append('result_sheet_photo', blob, submission[key].name);
                } else if (key !== 'timestamp' && key !== 'status' && key !== 'synced_at' && key !== 'server_response'
                    && key !== 'retry_count' && key !== 'last_error' && key !== 'last_attempt_at' && key !== 'failed_at') {
                    formData.append(key, submission[key]);
                }
            });

            const response = await fetch('/api/results/submit', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-GPS-Latitude': submission.submitted_latitude,
                    'X-GPS-Longitude': submission.submitted_longitude,
                    'X-GPS-Accuracy': submission.gps_accuracy_meters || 0,
                },
                body: formData,
            });

            const data = await response.json();

            if (response.ok) {
                await offlineSync.updateSubmissionStatus(
                    submission.submission_uuid,
                    'synced',
                    data
                );
                console.log(`Synced submission ${submission.submission_uuid}`);
                this.notifyCallbacks('submission_synced', submission);
            } else {
                throw new Error(data.message || 'Sync failed');
            }
        } catch (error) {
            console.error(`Failed to sync submission ${submission.submission_uuid}:`, error);

            // Retry-budget bookkeeping — this is where max_offline_retries
            // actually takes effect. Below the cap, the submission stays
            // 'pending' and will be retried on the next online event or
            // periodic sync tick. At/above the cap, it's marked 'failed'
            // and getPendingSubmissions() will stop returning it, so we
            // don't hammer the server (or the officer's data plan)
            // indefinitely for a submission that keeps rejecting.
            try {
                const attempts = await offlineSync.incrementRetryCount(
                    submission.submission_uuid,
                    error.message
                );

                if (attempts >= this.maxRetries) {
                    await offlineSync.markFailed(submission.submission_uuid, error.message);
                    console.warn(
                        `Submission ${submission.submission_uuid} exceeded max_offline_retries (${this.maxRetries}) — marked failed.`
                    );
                    this.notifyCallbacks('submission_failed_permanently', { submission, error, attempts });
                } else {
                    this.notifyCallbacks('submission_retry_scheduled', { submission, error, attempts, maxRetries: this.maxRetries });
                }
            } catch (bookkeepingError) {
                console.error('Failed to update retry bookkeeping:', bookkeepingError);
            }

            throw error;
        }
    }

    /**
     * Manually requeue a submission that was marked 'failed' after
     * exhausting max_offline_retries, then attempt to sync it again
     * immediately (if online). Intended for a "Retry" button in the UI.
     */
    async retrySubmission(uuid) {
        const submission = await offlineSync.requeueSubmission(uuid);
        if (navigator.onLine) {
            this.syncSubmission(submission).catch(() => {});
        }
        return submission;
    }

    base64ToBlob(base64, contentType) {
        const byteCharacters = atob(base64.split(',')[1]);
        const byteArrays = [];

        for (let offset = 0; offset < byteCharacters.length; offset += 512) {
            const slice = byteCharacters.slice(offset, offset + 512);
            const byteNumbers = new Array(slice.length);
            
            for (let i = 0; i < slice.length; i++) {
                byteNumbers[i] = slice.charCodeAt(i);
            }
            
            byteArrays.push(new Uint8Array(byteNumbers));
        }

        return new Blob(byteArrays, { type: contentType });
    }

    onSyncEvent(callback) {
        this.syncCallbacks.push(callback);
    }

    notifyCallbacks(event, data) {
        this.syncCallbacks.forEach(cb => cb(event, data));
    }
}

export const syncQueue = new SyncQueue();