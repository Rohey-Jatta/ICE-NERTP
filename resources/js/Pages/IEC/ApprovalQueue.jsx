import { useState } from 'react';
import { router } from '@inertiajs/react';
import { apiPost, AppError } from '@/Utils/apiClient';
import { useGlobalNotify } from '@/Contexts/NotificationContext';

/**
 * ApprovalQueue - Dashboard for Ward/Constituency/Admin Area/Chairman approvers.
 *
 * Reworked to use the shared apiClient + global notification system:
 *  - Every failure (permission, validation, network, server) now shows a
 *    visible toast with a specific, human-readable reason.
 *  - The modal/buttons always return to a usable state on failure —
 *    nothing stays stuck on "Processing...".
 *  - Field-level errors (e.g. missing rejection reason) are highlighted
 *    inline in addition to the toast.
 */
export default function ApprovalQueue({ level, queue, filters }) {
    const [selectedResult, setSelectedResult] = useState(null);
    const [action, setAction] = useState(null); // 'approve' or 'reject'
    const [comments, setComments] = useState('');
    const [processing, setProcessing] = useState(false);
    const [fieldError, setFieldError] = useState('');
    const { notify, notifyError } = useGlobalNotify();

    const levelNames = {
        ward: 'Ward',
        constituency: 'Constituency',
        admin_area: 'Administrative Area',
        national: 'National',
    };

    const handleAction = (result, actionType) => {
        setSelectedResult(result);
        setAction(actionType);
        setComments('');
        setFieldError('');
    };

    const closeModal = () => {
        setSelectedResult(null);
        setAction(null);
        setComments('');
        setFieldError('');
    };

    const submitAction = async () => {
        if (!selectedResult) return;

        // Field-level validation — shown inline, not just as a toast.
        if (action === 'reject' && !comments.trim()) {
            setFieldError('Rejection reason is required.');
            notify.warning('Please provide a reason before rejecting this result.');
            return;
        }

        setProcessing(true);
        setFieldError('');

        const endpoint = action === 'approve' ? '/api/approval/approve' : '/api/approval/reject';
        const body =
            action === 'approve'
                ? { result_id: selectedResult.id, comments }
                : { result_id: selectedResult.id, reason: comments };

        try {
            const data = await apiPost(endpoint, body);

            notify.success(
                data.message ||
                    (action === 'approve'
                        ? 'Result approved successfully.'
                        : 'Result rejected and returned to the polling station.')
            );

            closeModal();
            // Refresh the queue in the background — this is a data refresh,
            // not the confirmation itself, so it doesn't gate the toast.
            router.reload({ only: ['queue'] });
        } catch (err) {
            if (err instanceof AppError && err.fields?.result_id) {
                setFieldError(err.fields.result_id[0] || err.message);
            } else if (err instanceof AppError && err.code === 'VALIDATION_ERROR') {
                setFieldError(err.message);
            }
            notifyError(err, `Unable to ${action === 'approve' ? 'approve' : 'reject'} this result. Please try again.`);
        } finally {
            // ALWAYS restore the button — this is the fix for the
            // "stuck on Processing..." failure mode.
            setProcessing(false);
        }
    };

    return (
        <div className="min-h-screen bg-gray-50 py-6 px-4">
            {/* Header */}
            <div className="max-w-7xl mx-auto mb-6">
                <div className="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-700">
                    <h1 className="text-2xl font-bold text-blue-800">
                        {levelNames[level]} Approval Queue
                    </h1>
                    <p className="text-sm text-slate-600 mt-1">
                        Review and certify results pending approval at the {levelNames[level].toLowerCase()} level
                    </p>
                </div>
            </div>

            {/* Queue Table */}
            <div className="max-w-7xl mx-auto">
                <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                    {queue.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <svg className="mx-auto h-12 w-12 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p className="mt-4 text-slate-600">No results pending approval</p>
                            <p className="text-sm text-slate-500 mt-1">All results in your queue have been processed</p>
                        </div>
                    ) : (
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Station</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Submitted</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Votes Cast</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Turnout</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Party Status</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {queue.data.map((result) => {
                                    const turnout = result.total_registered_voters > 0
                                        ? ((result.total_votes_cast / result.total_registered_voters) * 100).toFixed(1)
                                        : 0;

                                    const partyAccepted = result.party_acceptances?.filter(pa => pa.status === 'accepted').length || 0;
                                    const partyTotal = result.election.political_parties_count || 0;

                                    return (
                                        <tr key={result.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4">
                                                <div className="text-sm font-medium text-gray-900">
                                                    {result.polling_station.name}
                                                </div>
                                                <div className="text-sm text-slate-500">
                                                    {result.polling_station.code}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-slate-500">
                                                {new Date(result.submitted_at).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                {result.total_votes_cast.toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                {turnout}%
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${
                                                    partyAccepted === partyTotal
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-amber-100 text-amber-800'
                                                }`}>
                                                    {partyAccepted}/{partyTotal} Accepted
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right text-sm font-medium space-x-2">
                                                <button
                                                    onClick={() => handleAction(result, 'approve')}
                                                    className="text-green-600 hover:text-green-900"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    onClick={() => handleAction(result, 'reject')}
                                                    className="text-red-600 hover:text-red-900"
                                                >
                                                    Reject
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Pagination */}
                {queue.last_page > 1 && (
                    <div className="mt-4 flex justify-between items-center">
                        <p className="text-sm text-slate-600">
                            Showing {queue.from} to {queue.to} of {queue.total} results
                        </p>
                        <div className="flex gap-2">
                            {queue.links.map((link, i) => (
                                <button
                                    key={i}
                                    onClick={() => router.visit(link.url)}
                                    disabled={!link.url}
                                    className={`px-3 py-1 rounded ${
                                        link.active
                                            ? 'bg-iec-pink-600 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-100'
                                    } disabled:opacity-50`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Action Modal */}
            {selectedResult && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-lg max-w-2xl w-full p-6">
                        <h2 className="text-xl font-bold text-gray-900 mb-4">
                            {action === 'approve' ? 'Approve Result' : 'Reject Result'}
                        </h2>

                        <div className="mb-4 p-4 bg-gray-50 rounded-md">
                            <p className="text-sm text-slate-600">
                                <strong>Station:</strong> {selectedResult.polling_station.name}
                            </p>
                            <p className="text-sm text-slate-600 mt-1">
                                <strong>Votes Cast:</strong> {selectedResult.total_votes_cast.toLocaleString()}
                            </p>
                        </div>

                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {action === 'approve' ? 'Comments (optional)' : 'Rejection Reason (required)'}
                            </label>
                            <textarea
                                value={comments}
                                onChange={(e) => { setComments(e.target.value); setFieldError(''); }}
                                rows={4}
                                required={action === 'reject'}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 ${
                                    fieldError ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-blue-500'
                                }`}
                                placeholder={action === 'approve' ? 'Add any notes...' : 'Explain why this result is being rejected...'}
                            />
                            {fieldError && (
                                <p className="mt-1.5 text-sm text-red-800">{fieldError}</p>
                            )}
                        </div>

                        <div className="flex gap-3">
                            <button
                                onClick={submitAction}
                                disabled={processing || (action === 'reject' && !comments.trim())}
                                className={`flex-1 py-2 px-4 rounded-md font-medium text-white ${
                                    action === 'approve'
                                        ? 'bg-green-600 hover:bg-green-700'
                                        : 'bg-red-600 hover:bg-red-700'
                                } disabled:opacity-50 disabled:cursor-not-allowed`}
                            >
                                {processing ? 'Processing...' : action === 'approve' ? 'Confirm Approval' : 'Confirm Rejection'}
                            </button>
                            <button
                                onClick={closeModal}
                                disabled={processing}
                                className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}