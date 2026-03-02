import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { useGeolocation } from '../../Hooks/useGeolocation';
import PhotoCapture from '../../Components/PhotoCapture';
import { offlineSync } from '../../Services/OfflineSync';
import { syncQueue } from '../../Services/SyncQueue';

/**
 * ResultEntry - Main result submission form for Polling Officers.
 *
 * From architecture: resources/js/Pages/PollingOfficer/ResultEntry.jsx
 *
 * Features:
 * - Offline-first (works without internet)
 * - GPS validation (officer must be at station)
 * - Photo capture with compression
 * - Real-time vote totals validation
 * - Queues in IndexedDB if offline
 */
export default function ResultEntry({
    pollingStation,
    election,
    candidates,
    registeredVoters
}) {
    const { location, error: gpsError, loading: gpsLoading } = useGeolocation();
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [submitting, setSubmitting] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [photo, setPhoto] = useState(null);

    // Form state
    const [formData, setFormData] = useState({
        total_votes_cast: '',
        valid_votes: '',
        rejected_votes: '',
        disputed_votes: '',
        candidate_votes: candidates.map(c => ({ candidate_id: c.id, votes: '' })),
    });

    // Initialize sync queue
    useEffect(() => {
        syncQueue.init();

        syncQueue.onSyncEvent((event, data) => {
            if (event === 'submission_synced') {
                setMessage({
                    type: 'success',
                    text: 'Offline submission synced successfully!'
                });
            }
        });

        const handleOnline = () => setIsOnline(true);
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    // Computed totals
    const totalCandidateVotes = formData.candidate_votes.reduce(
        (sum, cv) => sum + (parseInt(cv.votes) || 0),
        0
    );

    const validVotes = parseInt(formData.valid_votes) || 0;
    const rejectedVotes = parseInt(formData.rejected_votes) || 0;
    const disputedVotes = parseInt(formData.disputed_votes) || 0;
    const totalVotesCast = parseInt(formData.total_votes_cast) || 0;

    // Validation errors
    const validationErrors = [];

    if (totalVotesCast > registeredVoters) {
        validationErrors.push('Total votes cast cannot exceed registered voters');
    }

    if (validVotes + rejectedVotes + disputedVotes !== totalVotesCast) {
        validationErrors.push(
            `Valid (${validVotes}) + Rejected (${rejectedVotes}) + Disputed (${disputedVotes}) must equal Total Cast (${totalVotesCast})`
        );
    }

    if (totalCandidateVotes !== validVotes) {
        validationErrors.push(
            `Sum of candidate votes (${totalCandidateVotes}) must equal valid votes (${validVotes})`
        );
    }

    if (!photo) {
        validationErrors.push('Result sheet photo is required');
    }

    if (gpsError || !location) {
        validationErrors.push('GPS location is required');
    }

    const canSubmit = validationErrors.length === 0 && !submitting;

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!canSubmit) return;

        setSubmitting(true);
        setMessage({ type: '', text: '' });

        try {
            const submissionUuid = crypto.randomUUID();

            const submission = {
                submission_uuid: submissionUuid,
                polling_station_id: pollingStation.id,
                election_id: election.id,
                total_registered_voters: registeredVoters,
                total_votes_cast: totalVotesCast,
                valid_votes: validVotes,
                rejected_votes: rejectedVotes,
                disputed_votes: disputedVotes,
                candidate_votes: formData.candidate_votes.map(cv => ({
                    candidate_id: cv.candidate_id,
                    votes: parseInt(cv.votes) || 0,
                })),
                result_sheet_photo: photo ? {
                    data: photo.base64,
                    name: photo.name,
                    type: photo.type,
                } : null,
                submitted_latitude: location.latitude,
                submitted_longitude: location.longitude,
                gps_accuracy_meters: location.accuracy,
                was_offline: !isOnline,
            };

            if (!isOnline) {
                // Save to IndexedDB for later sync
                await offlineSync.saveSubmission(submission);

                setMessage({
                    type: 'warning',
                    text: 'You are offline. Result saved locally and will sync when connection is restored.',
                });

                // Reset form
                resetForm();
            } else {
                // Submit immediately
                await submitToServer(submission);
            }

        } catch (error) {
            console.error('Submission error:', error);
            setMessage({
                type: 'error',
                text: error.message || 'Failed to submit result. Please try again.',
            });
        } finally {
            setSubmitting(false);
        }
    };

    const submitToServer = async (submission) => {
        const formDataObj = new FormData();

        // Append all fields
        Object.keys(submission).forEach(key => {
            if (key === 'candidate_votes') {
                formDataObj.append('candidate_votes', JSON.stringify(submission[key]));
            } else if (key === 'result_sheet_photo' && submission[key]) {
                const blob = dataURLtoBlob(submission[key].data);
                formDataObj.append('result_sheet_photo', blob, submission[key].name);
            } else {
                formDataObj.append(key, submission[key]);
            }
        });

        const response = await fetch('/api/results/submit', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-GPS-Latitude': submission.submitted_latitude,
                'X-GPS-Longitude': submission.submitted_longitude,
                'X-GPS-Accuracy': submission.gps_accuracy_meters,
            },
            body: formDataObj,
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Submission failed');
        }

        setMessage({
            type: 'success',
            text: `✓ Result submitted successfully! ${data.warnings?.length ? 'Warnings: ' + data.warnings.join(', ') : ''}`,
        });

        // Reset form
        resetForm();
    };

    const resetForm = () => {
        setFormData({
            total_votes_cast: '',
            valid_votes: '',
            rejected_votes: '',
            disputed_votes: '',
            candidate_votes: candidates.map(c => ({ candidate_id: c.id, votes: '' })),
        });
        setPhoto(null);
    };

    const dataURLtoBlob = (dataurl) => {
        const arr = dataurl.split(',');
        const mime = arr[0].match(/:(.*?);/)[1];
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new Blob([u8arr], { type: mime });
    };

    return (
        <div className="min-h-screen bg-gray-50 py-6 px-4">
            {/* Header */}
            <div className="max-w-3xl mx-auto mb-6">
                <div className="bg-white rounded-lg shadow-sm p-4 border-l-4 border-blue-700">
                    <div className="flex items-start justify-between">
                        <div>
                            <h1 className="text-xl font-bold text-blue-700">
                                {pollingStation.name}
                            </h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Station Code: {pollingStation.code} • {election.name}
                            </p>
                            <p className="text-sm text-gray-500">
                                Registered Voters: {registeredVoters.toLocaleString()}
                            </p>
                        </div>
                        <div className="text-right">
                            <div className={`inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium ${
                                isOnline
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-amber-100 text-amber-800'
                            }`}>
                                <div className={`w-2 h-2 rounded-full ${
                                    isOnline ? 'bg-green-600' : 'bg-amber-600'
                                }`} />
                                {isOnline ? 'Online' : 'Offline'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* GPS Status */}
                {gpsLoading && (
                    <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                        <p className="text-sm text-blue-700">Acquiring GPS location...</p>
                    </div>
                )}

                {gpsError && (
                    <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                        <p className="text-sm text-red-700">❌ {gpsError}</p>
                    </div>
                )}

                {location && !gpsError && (
                    <div className="mt-3 p-3 bg-green-50 border border-green-200 rounded-md">
                        <p className="text-sm text-green-700">
                            ✓ GPS: {location.latitude.toFixed(6)}, {location.longitude.toFixed(6)}
                            (±{Math.round(location.accuracy)}m)
                        </p>
                    </div>
                )}

                {/* Message Banner */}
                {message.text && (
                    <div className={`mt-3 p-3 border rounded-md ${
                        message.type === 'success' ? 'bg-green-50 border-green-200 text-green-700' :
                        message.type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' :
                        'bg-red-50 border-red-200 text-red-700'
                    }`}>
                        <p className="text-sm">{message.text}</p>
                    </div>
                )}
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="max-w-3xl mx-auto space-y-6">
                {/* Vote Totals */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Vote Totals</h2>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Total Votes Cast <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                required
                                value={formData.total_votes_cast}
                                onChange={(e) => setFormData({ ...formData, total_votes_cast: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Valid Votes <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                required
                                value={formData.valid_votes}
                                onChange={(e) => setFormData({ ...formData, valid_votes: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Rejected Votes <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                required
                                value={formData.rejected_votes}
                                onChange={(e) => setFormData({ ...formData, rejected_votes: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Disputed Votes
                            </label>
                            <input
                                type="number"
                                min="0"
                                value={formData.disputed_votes}
                                onChange={(e) => setFormData({ ...formData, disputed_votes: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </div>

                {/* Candidate Votes */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Votes by Candidate</h2>

                    <div className="space-y-3">
                        {candidates.map((candidate, index) => (
                            <div key={candidate.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-md">
                                <div className="flex-1">
                                    <p className="font-medium text-gray-900">{candidate.full_name}</p>
                                    <p className="text-sm text-gray-600">{candidate.political_party?.name}</p>
                                </div>
                                <input
                                    type="number"
                                    min="0"
                                    required
                                    value={formData.candidate_votes[index].votes}
                                    onChange={(e) => {
                                        const newVotes = [...formData.candidate_votes];
                                        newVotes[index].votes = e.target.value;
                                        setFormData({ ...formData, candidate_votes: newVotes });
                                    }}
                                    className="w-24 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="0"
                                />
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                        <p className="text-sm text-blue-800">
                            Total Candidate Votes: <strong>{totalCandidateVotes}</strong>
                            {validVotes > 0 && totalCandidateVotes !== validVotes && (
                                <span className="text-red-600 ml-2">
                                    (Must equal Valid Votes: {validVotes})
                                </span>
                            )}
                        </p>
                    </div>
                </div>

                {/* Photo Capture */}
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <PhotoCapture onPhotoCapture={setPhoto} required />
                </div>

                {/* Validation Errors */}
                {validationErrors.length > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-md p-4">
                        <p className="text-sm font-medium text-red-900 mb-2">
                            Please fix the following errors:
                        </p>
                        <ul className="list-disc list-inside space-y-1">
                            {validationErrors.map((error, i) => (
                                <li key={i} className="text-sm text-red-700">{error}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Submit Button */}
                <button
                    type="submit"
                    disabled={!canSubmit}
                    className="w-full py-3 px-4 bg-blue-700 text-white font-medium rounded-md hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {submitting ? 'Submitting...' : isOnline ? 'Submit Result' : 'Save Offline & Sync Later'}
                </button>
            </form>
        </div>
    );
}
