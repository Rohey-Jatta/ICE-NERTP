import { offlineSync } from './OfflineSync';

/**
 * SyncQueue - Background sync manager.
 * 
 * From architecture: resources/js/Services/SyncQueue.js
 * 
 * Monitors online/offline status and syncs pending submissions.
 * Uses Background Sync API when available, polling fallback.
 */

class SyncQueue {
    constructor() {
        this.isSyncing = false;
        this.syncCallbacks = [];
    }

    init() {
        // Listen for online/offline events
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Register service worker sync if available
        if ('serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype) {
            this.registerBackgroundSync();
        }

        // Immediate sync check if online
        if (navigator.onLine) {
            this.syncPendingSubmissions();
        }
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
        console.log('Connection restored - syncing pending submissions');
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

            for (const submission of pending) {
                await this.syncSubmission(submission);
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
                } else if (key !== 'timestamp' && key !== 'status' && key !== 'synced_at' && key !== 'server_response') {
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
            throw error;
        }
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
