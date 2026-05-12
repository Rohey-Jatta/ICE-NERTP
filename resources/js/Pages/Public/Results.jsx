import AppLayout from '@/Layouts/AppLayout';
import { Link, usePage } from '@inertiajs/react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { ElectionSelector, PublicElectionHeader } from '@/Components/PublicElectionHeader';
import { electionTypeLabel, publicElectionTitle } from '@/Utils/publicElection';

function EmptyState({ title, message, action }) {
    return (
        <div className="max-w-3xl mx-auto rounded-xl border border-slate-200 bg-white p-8 sm:p-10 text-center shadow-sm">
            <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-iec-pink-50 text-iec-pink-600">
                <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 7h16M6 7v12h12V7M9 11h6M9 15h4M8 7l1-3h6l1 3" />
                </svg>
            </div>
            <h2 className="text-2xl font-bold text-slate-950">{title}</h2>
            <p className="mt-3 text-base leading-7 text-slate-600">{message}</p>
            {action}
        </div>
    );
}

function StatCard({ label, value, accent = 'text-slate-950' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className={`text-3xl font-extrabold ${accent}`}>{value}</div>
            <div className="mt-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">{label}</div>
        </div>
    );
}

function ProgressCard({ stats }) {
    const progress = stats?.total_stations > 0
        ? Math.round((stats.stations_reported / stats.total_stations) * 100)
        : 0;

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Stations reporting</p>
                    <p className="mt-2 text-3xl font-extrabold text-slate-950">
                        {parseInt(stats?.stations_reported || 0).toLocaleString()}
                        <span className="text-slate-400"> / {parseInt(stats?.total_stations || 0).toLocaleString()}</span>
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Completion</p>
                    <p className="mt-2 text-3xl font-extrabold text-iec-pink-600">{progress}%</p>
                </div>
            </div>
            <div className="mt-5 h-3 overflow-hidden rounded-full bg-slate-100">
                <div
                    className="h-full rounded-full bg-gradient-to-r from-iec-pink-500 to-emerald-500 transition-all duration-500"
                    style={{ width: `${progress}%` }}
                />
            </div>
        </div>
    );
}

function CandidateResults({ candidates = [], totalValidVotes = 0 }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
            <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 pb-5">
                <div>
                    <h2 className="text-2xl font-extrabold text-slate-950">Candidate results</h2>
                    <p className="mt-1 text-sm text-slate-500">Vote totals from publicly displayable certified stations.</p>
                </div>
            </div>
            <div className="mt-6 space-y-4">
                {candidates.map((candidate, index) => {
                    const percentage = totalValidVotes > 0
                        ? ((candidate.total_votes / totalValidVotes) * 100).toFixed(2)
                        : 0;
                    const primaryColor = candidate.party_color?.split(',')[0]?.trim() || '#64748b';
                    const isLeading = index === 0;

                    return (
                        <div
                            key={candidate.id}
                            className={`rounded-xl border p-5 ${
                                isLeading
                                    ? 'border-emerald-200 bg-emerald-50/60'
                                    : 'border-slate-200 bg-slate-50/70'
                            }`}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="flex min-w-0 items-start gap-3">
                                    <span className="mt-1 h-3 w-3 flex-shrink-0 rounded-full" style={{ backgroundColor: primaryColor }} />
                                    <div className="min-w-0">
                                        <div className="truncate text-lg font-bold text-slate-950">{candidate.name}</div>
                                        <div className="text-sm text-slate-500">
                                            {candidate.party_abbr} - {candidate.party_name}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-2xl font-extrabold text-slate-950">
                                        {parseInt(candidate.total_votes || 0).toLocaleString()}
                                    </div>
                                    <div className="text-sm font-semibold text-slate-500">{percentage}%</div>
                                </div>
                            </div>
                            <div className="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div
                                    className="h-full rounded-full transition-all duration-700"
                                    style={{ width: `${percentage}%`, backgroundColor: primaryColor }}
                                />
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function HomeCandidateSnapshot({ candidates = [], totalValidVotes = 0, param = '' }) {
    const visibleCandidates = candidates.slice(0, 3);

    if (visibleCandidates.length === 0) {
        return (
            <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Result snapshot</p>
                <h2 className="mt-3 text-2xl font-extrabold text-slate-950">Awaiting public totals</h2>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    Candidate totals will appear here as soon as submitted results are available for the selected election.
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Leading candidates</p>
                    <h2 className="mt-2 text-2xl font-extrabold text-slate-950">Current public snapshot</h2>
                </div>
                <Link href={`/results${param}`} prefetch className="text-sm font-bold text-iec-pink-600 hover:text-iec-pink-700">
                    View full results
                </Link>
            </div>
            <div className="mt-5 space-y-4">
                {visibleCandidates.map((candidate) => {
                    const pct = totalValidVotes > 0
                        ? ((candidate.total_votes / totalValidVotes) * 100).toFixed(1)
                        : '0.0';
                    const color = candidate.party_color?.split(',')[0]?.trim() || '#64748b';

                    return (
                        <div key={candidate.id}>
                            <div className="mb-1.5 flex items-center justify-between gap-3">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span className="h-2.5 w-2.5 flex-shrink-0 rounded-full" style={{ backgroundColor: color }} />
                                    <span className="truncate text-sm font-bold text-slate-900">{candidate.name}</span>
                                    <span className="text-xs font-semibold text-slate-400">{candidate.party_abbr}</span>
                                </div>
                                <div className="flex flex-shrink-0 items-baseline gap-2">
                                    <span className="text-sm font-extrabold text-slate-950">{parseInt(candidate.total_votes || 0).toLocaleString()}</span>
                                    <span className="text-xs text-slate-500">{pct}%</span>
                                </div>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full rounded-full" style={{ width: `${pct}%`, backgroundColor: color }} />
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function CertificationStatusCard({ election, stats, message }) {
    const hasSubmittedResults = (stats?.stations_reported || 0) > 0;
    const stages = [
        { label: 'Result submission', done: hasSubmittedResults },
        { label: 'Certification review', done: ['certifying', 'certified'].includes(election?.status) },
        { label: 'Public result totals', done: election?.status === 'certified' },
    ];
    const statusMessage = election?.status === 'certified'
        ? 'The election has completed the certification workflow and official public results are available.'
        : hasSubmittedResults
            ? 'Submitted results are feeding the public dashboard while certification continues through the IEC workflow.'
            : 'The IEC certification workflow will update as polling station results are submitted and reviewed.';

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Certification status</p>
            <h2 className="mt-2 text-2xl font-extrabold text-slate-950">
                {election?.status === 'certified' ? 'Results certified' : 'Certification in progress'}
            </h2>
            <p className="mt-3 text-sm leading-6 text-slate-600">
                {statusMessage}
            </p>

            <div className="mt-5 space-y-3">
                {stages.map((stage) => (
                    <div key={stage.label} className="flex items-center gap-3">
                        <span className={`flex h-7 w-7 items-center justify-center rounded-full border text-xs font-extrabold ${
                            stage.done
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-slate-200 bg-slate-50 text-slate-400'
                        }`}>
                            {stage.done ? '✓' : '•'}
                        </span>
                        <span className={`text-sm font-semibold ${stage.done ? 'text-slate-900' : 'text-slate-500'}`}>
                            {stage.label}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function HomePage({ election, elections, selectedElectionId, stats, candidates, message, param }) {
    const totalValidVotes = stats?.valid_votes || 0;
    const progress = stats?.total_stations > 0
        ? Math.round((stats.stations_reported / stats.total_stations) * 100)
        : 0;
    const turnout = stats?.total_registered > 0
        ? ((stats.total_cast / stats.total_registered) * 100).toFixed(1)
        : 0;

    if (!election) {
        return (
            <AppLayout>
                <div className="bg-slate-50">
                    <PublicElectionHeader
                        title="Election results portal"
                        description="The public homepage will show the current election overview once an administrator creates an election and enables it for public display."
                    />
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <EmptyState
                            title="No election is currently public"
                            message="Create an election in the admin area and enable it for public homepage display to publish the summary dashboard here."
                        />
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="bg-slate-50">
                <section className="bg-gradient-to-br from-white via-slate-50 to-sky-50 border-b border-slate-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
                        <div className="grid items-center gap-8 lg:grid-cols-[1.15fr_0.85fr]">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.22em] text-iec-pink-600">
                                    Current public election
                                </p>
                                <h1 className="mt-4 text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-normal text-slate-950 leading-tight">
                                    {publicElectionTitle(election)}
                                </h1>
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    <span className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
                                        {election.status === 'certified' ? 'Official results' : 'Ongoing results'}
                                    </span>
                                    <span className="rounded-md border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-600">
                                        {electionTypeLabel(election)}
                                    </span>
                                </div>
                                <p className="mt-5 max-w-2xl text-base sm:text-lg leading-8 text-slate-600">
                                    A summarized public view of the active election, including turnout, reporting progress, and the latest result snapshot.
                                </p>
                                <div className="mt-7 flex flex-wrap gap-3">
                                    <Link
                                        href={`/results${param}`}
                                        prefetch
                                        className="inline-flex rounded-md bg-iec-pink-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-iec-pink-700"
                                    >
                                        View full results
                                    </Link>
                                </div>
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Reporting progress</p>
                                        <p className="mt-2 text-4xl font-extrabold text-slate-950">{progress}%</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Stations</p>
                                        <p className="mt-2 text-2xl font-extrabold text-slate-950">
                                            {parseInt(stats?.stations_reported || 0).toLocaleString()}
                                            <span className="text-slate-400"> / {parseInt(stats?.total_stations || 0).toLocaleString()}</span>
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-5 h-3 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full rounded-full bg-gradient-to-r from-iec-pink-500 to-emerald-500"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>
                                <p className="mt-4 text-sm leading-6 text-slate-600">
                                    {message || 'Summary figures update from publicly displayable results as certification progresses.'}
                                </p>
                            </div>
                        </div>

                        <div className="mt-8">
                            <ElectionSelector elections={elections} selectedElectionId={selectedElectionId} basePath="/" />
                        </div>
                    </div>
                </section>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Registered voters" value={parseInt(stats?.total_registered || 0).toLocaleString()} />
                        <StatCard label="Votes cast" value={parseInt(stats?.total_cast || 0).toLocaleString()} />
                        <StatCard label="Valid votes" value={parseInt(stats?.valid_votes || 0).toLocaleString()} accent="text-emerald-600" />
                        <StatCard label="Turnout" value={`${turnout}%`} accent="text-sky-700" />
                    </div>

                    <div className="mt-6 grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                        <HomeCandidateSnapshot candidates={candidates || []} totalValidVotes={totalValidVotes} param={param} />
                        <CertificationStatusCard election={election} stats={stats} message={message} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default function Results({ election, elections = [], selectedElectionId, stats, candidates, message }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';
    const { url } = usePage();
    const isHomePage = url?.split('?')[0] === '/';
    useInertiaPrefetch([`/results/map${param}`, `/results/stations${param}`]);

    if (isHomePage) {
        return (
            <HomePage
                election={election}
                elections={elections}
                selectedElectionId={selectedElectionId}
                stats={stats}
                candidates={candidates}
                message={message}
                param={param}
            />
        );
    }

    if (!election) {
        return (
            <AppLayout>
                <div className="bg-slate-50">
                    <PublicElectionHeader
                        title="Public election results"
                        description="Official public results will appear here after an election is configured for public display by the IEC administrator."
                    >
                        <ElectionSelector elections={elections} selectedElectionId={selectedElectionId} />
                    </PublicElectionHeader>
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <EmptyState
                            title="No public election selected"
                            message="There is no active election configured for the public homepage yet. Once an administrator creates an election and enables public display, the results portal will use that election data."
                        />
                    </div>
                </div>
            </AppLayout>
        );
    }

    if (!stats || !candidates || candidates.length === 0) {
        return (
            <AppLayout>
                <div className="bg-slate-50">
                    <PublicElectionHeader
                        election={election}
                        elections={elections}
                        selectedElectionId={selectedElectionId}
                        description="The public portal is ready, but this election has not reached publication. Station status remains available for transparency while certification continues."
                    />
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <EmptyState
                            title="Results pending publication"
                            message={message || 'Election results are currently being certified through the IEC approval pipeline.'}
                            action={(
                                <Link
                                    href={`/results/stations${param}`}
                                    prefetch
                                    className="mt-6 inline-flex items-center justify-center rounded-md bg-iec-pink-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-iec-pink-700"
                                >
                                    View station status
                                </Link>
                            )}
                        />
                    </div>
                </div>
            </AppLayout>
        );
    }

    const turnout = stats?.total_registered > 0
        ? ((stats.total_cast / stats.total_registered) * 100).toFixed(1)
        : 0;

    const totalValidVotes = stats?.valid_votes || 0;

    return (
        <AppLayout>
            <div className="bg-slate-50">
                <PublicElectionHeader
                    election={election}
                    elections={elections}
                    selectedElectionId={selectedElectionId}
                    description="Official public results are presented from the election configured by the IEC administrator for homepage display."
                />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <StatCard label="Registered voters" value={parseInt(stats?.total_registered || 0).toLocaleString()} />
                        <StatCard label="Votes cast" value={parseInt(stats?.total_cast || 0).toLocaleString()} />
                        <StatCard label="Valid votes" value={parseInt(stats?.valid_votes || 0).toLocaleString()} accent="text-emerald-600" />
                        <StatCard label="Rejected votes" value={parseInt(stats?.rejected_votes || 0).toLocaleString()} accent="text-amber-600" />
                        <StatCard label="Turnout" value={`${turnout}%`} accent="text-sky-700" />
                    </div>

                    <div className="mt-6">
                        <ProgressCard stats={stats} />
                    </div>

                    <div className="mt-6">
                        <CandidateResults candidates={candidates} totalValidVotes={totalValidVotes} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
