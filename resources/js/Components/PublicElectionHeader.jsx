import { router } from '@inertiajs/react';
import { electionTypeLabel, electionYear, publicElectionTitle } from '@/Utils/publicElection';

const STATUS_LABELS = {
    active: 'Receiving results',
    results_pending: 'Results pending',
    certifying: 'Certification in progress',
    certified: 'Official results',
};

export function ElectionSelector({ elections = [], selectedElectionId, basePath = '/results' }) {
    if (elections.length <= 1) return null;

    return (
        <div className="flex flex-wrap items-center justify-center gap-2">
            <span className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                Election
            </span>
            {elections.map((election) => (
                <button
                    key={election.id}
                    type="button"
                    onClick={() => router.get(basePath, { election: election.id }, { preserveScroll: false })}
                    className={`rounded-md border px-3 py-1.5 text-sm font-semibold transition-all ${
                        selectedElectionId === election.id
                            ? 'border-iec-pink-500 bg-iec-pink-500 text-white shadow-sm'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-iec-pink-200 hover:text-iec-pink-600'
                    }`}
                >
                    {publicElectionTitle(election)}
                </button>
            ))}
        </div>
    );
}

export function PublicElectionHeader({
    election,
    elections = [],
    selectedElectionId,
    basePath = '/results',
    eyebrow = 'Independent Electoral Commission',
    title,
    description,
    children,
}) {
    const computedTitle = title || publicElectionTitle(election);
    const status = STATUS_LABELS[election?.status] || null;
    const year = electionYear(election);

    return (
        <section className="bg-gradient-to-br from-white via-slate-50 to-sky-50 border-b border-slate-200">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
                <div className="max-w-4xl">
                    <p className="text-xs font-bold uppercase tracking-[0.22em] text-iec-pink-600">
                        {eyebrow}
                    </p>
                    <h1 className="mt-4 text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-normal text-slate-950 leading-tight">
                        {computedTitle}
                    </h1>
                    {election && (
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        {status && (
                            <span className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
                                {status}
                            </span>
                        )}
                        {year && (
                            <span className="rounded-md border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-600">
                                Election year: {year}
                            </span>
                        )}
                        <span className="rounded-md border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-600">
                            {electionTypeLabel(election)}
                        </span>
                    </div>
                    )}
                    {description && (
                        <p className="mt-5 max-w-2xl text-base sm:text-lg leading-8 text-slate-600">
                            {description}
                        </p>
                    )}
                </div>

                <div className="mt-7">
                    <ElectionSelector
                        elections={elections}
                        selectedElectionId={selectedElectionId}
                        basePath={basePath}
                    />
                </div>

                {children && (
                    <div className="mt-8">
                        {children}
                    </div>
                )}
            </div>
        </section>
    );
}
