import { useForm } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";

/**
 * AdminAreaEdit
 *
 * Shows an amber warning banner when the stored election is not the
 * most recent one, with a one-click "Switch to latest" button.
 *
 * The `elections` prop is ordered by created_at DESC by the route,
 * so elections[0] is always the most recent.
 */
export default function AdminAreaEdit({ auth, adminArea, elections = [] }) {
    const mostRecentId   = elections[0]?.id   ?? null;
    const mostRecentName = elections[0]?.name ?? null;

    const { data, setData, put, processing, errors } = useForm({
        election_id: adminArea.election_id ?? mostRecentId ?? "",
        code:        adminArea.code        ?? "",
        name:        adminArea.name        ?? "",
    });

    // Is the currently selected election NOT the most recent?
    const isStale = mostRecentId !== null
        && String(data.election_id) !== String(mostRecentId);

    const switchToLatest = () => {
        if (mostRecentId) setData("election_id", mostRecentId);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route("admin.hierarchy.admin-areas.update", adminArea.id));
    };

    return (
        <AppLayout auth={auth} title={`Edit: ${adminArea.name}`}>
            <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

                {/* Back */}
                
                <a href={route("admin.hierarchy.admin-areas")}
                    className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-6 transition"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Administrative Areas
                </a>

                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                    <h1 className="text-xl font-bold text-gray-900 mb-1">
                        Edit Administrative Area
                    </h1>
                    <p className="text-sm text-gray-500 mb-6">
                        Update the name, code, or election for{" "}
                        <span className="font-semibold text-gray-700">{adminArea.name}</span>.
                    </p>

                    {/* Stale-election warning */}
                    {isStale && (
                        <div className="mb-6 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
                            <svg className="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-semibold text-amber-800">
                                    This area is assigned to an older election.
                                </p>
                                {mostRecentName && (
                                    <p className="text-xs text-amber-600 mt-0.5">
                                        Most recent election: <strong>{mostRecentName}</strong>
                                    </p>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={switchToLatest}
                                className="flex-shrink-0 text-xs font-semibold text-amber-700 underline hover:text-amber-900 transition whitespace-nowrap"
                            >
                                Switch to latest
                            </button>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">

                        {/* Election */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                Election <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={data.election_id}
                                onChange={e => setData("election_id", e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition bg-white"
                            >
                                <option value="">— Select Election —</option>
                                {elections.map(election => (
                                    <option key={election.id} value={election.id}>
                                        {election.name}
                                        {election.id === mostRecentId ? " ✓ most recent" : ""}
                                    </option>
                                ))}
                            </select>
                            {errors.election_id && (
                                <p className="mt-1.5 text-xs text-red-600">{errors.election_id}</p>
                            )}
                        </div>

                        {/* Code */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                Area Code <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={e => setData("code", e.target.value.toUpperCase())}
                                placeholder="e.g. BJ"
                                maxLength={20}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition uppercase"
                            />
                            {errors.code && (
                                <p className="mt-1.5 text-xs text-red-600">{errors.code}</p>
                            )}
                        </div>

                        {/* Name */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                Area Name <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={e => setData("name", e.target.value)}
                                placeholder="e.g. Banjul"
                                maxLength={255}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition"
                            />
                            {errors.name && (
                                <p className="mt-1.5 text-xs text-red-600">{errors.name}</p>
                            )}
                        </div>

                        {/* Global error */}
                        {errors.error && (
                            <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                                {errors.error}
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                            >
                                {processing ? "Saving…" : "Save Changes"}
                            </button>
                            
                            <a href={route("admin.hierarchy.admin-areas")}
                                className="px-4 py-2.5 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition"
                            >
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}