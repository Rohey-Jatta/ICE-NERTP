/**
 * OfflineSync - IndexedDB storage for offline submissions.
 * 
 * From architecture: resources/js/Services/OfflineSync.js
 * 
 * Stores pending result submissions in IndexedDB when offline.
 * When connection restored, syncs to server via SyncQueue.
 */

const DB_NAME = 'iec_nertp_offline';
const DB_VERSION = 1;
const STORE_NAME = 'pending_submissions';

class OfflineSync {
    constructor() {
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, { 
                        keyPath: 'submission_uuid' 
                    });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('status', 'status', { unique: false });
                }
            };
        });
    }

    async saveSubmission(data) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);

            const submission = {
                ...data,
                submission_uuid: data.submission_uuid || crypto.randomUUID(),
                timestamp: Date.now(),
                status: 'pending',
                was_offline: true,
                queued_at: new Date().toISOString(),
            };

            const request = store.add(submission);

            request.onsuccess = () => resolve(submission);
            request.onerror = () => reject(request.error);
        });
    }

    async getPendingSubmissions() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const index = store.index('status');
            const request = index.getAll('pending');

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async updateSubmissionStatus(uuid, status, response = null) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.get(uuid);

            request.onsuccess = () => {
                const submission = request.result;
                if (submission) {
                    submission.status = status;
                    submission.synced_at = new Date().toISOString();
                    submission.server_response = response;
                    
                    const updateRequest = store.put(submission);
                    updateRequest.onsuccess = () => resolve(submission);
                    updateRequest.onerror = () => reject(updateRequest.error);
                } else {
                    reject(new Error('Submission not found'));
                }
            };

            request.onerror = () => reject(request.error);
        });
    }

    async deleteSubmission(uuid) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.delete(uuid);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getAllSubmissions() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async clearSyncedSubmissions() {
        if (!this.db) await this.init();

        const allSubmissions = await this.getAllSubmissions();
        const syncedUuids = allSubmissions
            .filter(s => s.status === 'synced')
            .map(s => s.submission_uuid);

        for (const uuid of syncedUuids) {
            await this.deleteSubmission(uuid);
        }

        return syncedUuids.length;
    }
}

export const offlineSync = new OfflineSync();
