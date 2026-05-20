import AppLayout from '@/Layouts/AppLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { ElectionSelector, PublicElectionHeader } from '@/Components/PublicElectionHeader';
import { electionTypeLabel, publicElectionTitle } from '@/Utils/publicElection';

// ── Helpers ───────────────────────────────────────────────────────────────────
function numeric(v) { return Number(v || 0); }

function formatVotes(v) { return numeric(v).toLocaleString(); }

// ── Candidate avatar: photo or colored initial fallback ───────────────────────
function CandidateAvatar({ candidate, size = 'lg' }) {
    const [imgError, setImgError] = useState(false);
    const color = (candidate.party_color || '#64748b').split(',')[0].trim();
    const initial = candidate.name?.charAt(0)?.toUpperCase() || '?';

    const sizeClass = size === 'lg'
        ? 'w-16 h-16 text-2xl'
        : 'w-10 h-10 text-sm';

    if (candidate.photo_url && !imgError) {
        return (
            <img
                src={candidate.photo_url}
                alt={candidate.name}
                onError={() => setImgError(true)}
                className={`${sizeClass} rounded-full object-cover flex-shrink-0 ring-2 ring-offset-1`}
                style={{ ringColor: color }}
            />
        );
    }

    return (
        <div
            className={`${sizeClass} rounded-full flex items-center justify-center font-extrabold text-white flex-shrink-0 ring-2 ring-offset-1`}
            style={{ backgroundColor: color, ringColor: color }}
        >
            {initial}
        </div>
    );
}

// ── Candidate leaderboard entry ───────────────────────────────────────────────
function CandidateRow({ candidate, rank, totalValidVotes }) {
    const pct        = totalValidVotes > 0
        ? ((numeric(candidate.total_votes) / totalValidVotes) * 100)
        : 0;
    const pctDisplay = pct.toFixed(2);
    const isLeading  = rank === 1;
    const color      = (candidate.party_color || '#64748b').split(',')[0].trim();

    return (
        <div className={`flex items-center gap-4 p-4 rounded-2xl border transition-all ${
            isLeading
                ? 'bg-gradient-to-r from-emerald-50 to-teal-50 border-emerald-200 shadow-sm'
                : 'bg-white border-slate-200 hover:border-slate-300 hover:shadow-sm'
        }`}>
            {/* Rank */}
            <div className={`w-8 text-center font-black text-xl flex-shrink-0 ${
                isLeading ? 'text-emerald-600' : 'text-slate-300'
            }`}>
                {isLeading ? '🏆' : rank}
            </div>

            {/* Avatar */}
            <CandidateAvatar candidate={candidate} size="lg" />

            {/* Info + bar */}
            <div className="flex-1 min-w-0">
                <div className="flex flex-wrap items-center gap-2 mb-0.5">
                    <span className="text-base sm:text-lg font-extrabold text-slate-950 truncate">
                        {candidate.name}
                    </span>
                    {isLeading && (
                        <span className="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 flex-shrink-0">
                            Leading
                        </span>
                    )}
                </div>
                <span className="text-sm text-slate-500">
                    {candidate.party_abbr !== 'IND'
                        ? `${candidate.party_abbr} — ${candidate.party_name}`
                        : 'Independent'}
                </span>

                {/* Progress bar */}
                <div className="mt-2.5 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                    <div
                        className="h-full rounded-full transition-all duration-700"
                        style={{ width: `${Math.min(100, pct)}%`, backgroundColor: color }}
                    />
                </div>
            </div>

            {/* Vote count */}
            <div className="text-right flex-shrink-0">
                <div className="text-xl sm:text-2xl font-black text-slate-950">
                    {formatVotes(candidate.total_votes)}
                </div>
                <div className="text-sm font-bold text-slate-500">
                    {pctDisplay}%
                </div>
            </div>
        </div>
    );
}

// ── Stats bar ─────────────────────────────────────────────────────────────────
function StatPill({ label, value, accent = 'text-slate-950', sub }) {
    return (
        <div className="flex flex-col items-center sm:items-start bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div className={`text-2xl sm:text-3xl font-extrabold ${accent}`}>{value}</div>
            <div className="mt-1 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">{label}</div>
            {sub && <div className="text-xs text-slate-400 mt-0.5">{sub}</div>}
        </div>
    );
}

// ── Map teaser ────────────────────────────────────────────────────────────────
function MapTeaser({ election, stats, param }) {
    if (!election) return null;

    const total    = numeric(stats?.total_stations);
    const reported = numeric(stats?.stations_reported);
    const pct      = total > 0 ? Math.round((reported / total) * 100) : 0;

    return (
        <Link
            href={`/results/map${param}`}
            className="group block relative overflow-hidden rounded-2xl bg-slate-900 border border-slate-700 hover:border-slate-600 transition-all hover:shadow-2xl"
        >
            {/* Grid overlay */}
            <div className="absolute inset-0 opacity-10 pointer-events-none">
                <svg width="100%" height="100%">
                    <defs>
                        <pattern id="mapgrid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" strokeWidth="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#mapgrid)" />
                </svg>
            </div>
            <div className="absolute inset-0 bg-gradient-to-r from-slate-900/70 to-transparent pointer-events-none" />

            <div className="relative z-10 p-6 sm:p-8">
                <div className="flex flex-col sm:flex-row sm:items-center gap-5">
                    <div className="flex-1 min-w-0">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">
                            🗺️ Live Election Map · The Gambia
                        </p>
                        <h3 className="text-white text-xl sm:text-2xl font-extrabold leading-snug mb-3">
                            {total > 0
                                ? `${reported.toLocaleString()} of ${total.toLocaleString()} Stations Certified`
                                : 'National Polling Station Coverage'}
                        </h3>
                        {total > 0 && (
                            <div className="mb-4 max-w-xs">
                                <div className="flex justify-between text-xs text-slate-400 mb-1">
                                    <span>{pct}% certified</span>
                                    <span className="text-green-400 font-bold">✓ Live Data</span>
                                </div>
                                <div className="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                    <div
                                        className="h-full rounded-full bg-gradient-to-r from-[var(--iec-pink)] to-emerald-500 transition-all"
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            </div>
                        )}
                        <div className="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                            {[
                                { color: '#22c55e', label: 'Certified by Chairman' },
                                { color: '#f59e0b', label: 'Under review' },
                                { color: '#94a3b8', label: 'Not yet submitted' },
                            ].map(item => (
                                <div key={item.color} className="flex items-center gap-1.5">
                                    <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: item.color }} />
                                    {item.label}
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="flex-shrink-0">
                        <span className="inline-flex items-center gap-2 bg-[var(--iec-pink)] group-hover:bg-[var(--iec-pink-dark)] text-white font-bold px-6 py-3 rounded-xl transition-colors text-sm whitespace-nowrap">
                            View Full Map
                            <svg className="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
        </Link>
    );
}

// ── Empty / no results state ──────────────────────────────────────────────────
function AwaitingResults({ election, message }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-10 sm:p-16 text-center shadow-sm">
            <div className="mx-auto mb-5 w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-3xl">
                🗳️
            </div>
            <h2 className="text-2xl font-extrabold text-slate-950 mb-3">
                Results not yet published
            </h2>
            <p className="text-slate-600 max-w-md mx-auto leading-relaxed">
                {message || 'The IEC Chairman has not yet published any results for this election. Results will appear here as they are officially certified and published.'}
            </p>
            {election && (
                <div className="mt-6 inline-flex items-center gap-2 bg-slate-100 rounded-full px-4 py-2 text-sm font-semibold text-slate-600">
                    <span className="w-2 h-2 rounded-full bg-amber-400 animate-pulse" />
                    Certification in progress
                </div>
            )}
        </div>
    );
}

// ── No election configured ────────────────────────────────────────────────────
function NoElection() {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-10 sm:p-16 text-center shadow-sm">
            <div className="mx-auto mb-5 w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-3xl">
                🏛️
            </div>
            <h2 className="text-2xl font-extrabold text-slate-950 mb-3">
                No election is currently active
            </h2>
            <p className="text-slate-600 max-w-md mx-auto">
                This platform publishes official election results from the Independent Electoral Commission of The Gambia. Check back once an election is underway.
            </p>
        </div>
    );
}

// ── Main component ────────────────────────────────────────────────────────────
export default function Results({ election, elections = [], selectedElectionId, stats, candidates, message }) {
    const { url }  = usePage();
    const param    = selectedElectionId ? `?election=${selectedElectionId}` : '';
    const isHome   = url?.split('?')[0] === '/';

    useInertiaPrefetch([`/results/map${param}`, `/results/stations${param}`]);

    const totalValidVotes = numeric(stats?.valid_votes);
    const turnout         = numeric(stats?.total_registered) > 0
        ? ((numeric(stats?.total_cast) / numeric(stats?.total_registered)) * 100).toFixed(1)
        : '0.0';

    const hasCandidates = Array.isArray(candidates) && candidates.length > 0;
    const hasStats      = stats && numeric(stats?.stations_reported) > 0;

    return (
        <AppLayout>
            <div className="min-h-screen bg-slate-50">

                {/* ── Page header ───────────────────────────────────────────── */}
                <section className="bg-gradient-to-br from-white via-slate-50 to-sky-50 border-b border-slate-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
                        <div className="max-w-4xl">
                            <p className="text-xs font-bold uppercase tracking-[0.22em] text-[var(--iec-pink)]">
                                Independent Electoral Commission · Official Results
                            </p>
                            <h1 className="mt-4 text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-slate-950 leading-tight">
                                {election ? publicElectionTitle(election) : 'Election Results Portal'}
                            </h1>
                            {election && (
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    <span className={`rounded-md border px-3 py-1 text-sm font-semibold ${
                                        election.status === 'certified'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : 'border-amber-200 bg-amber-50 text-amber-700'
                                    }`}>
                                        {election.status === 'certified' ? '✓ Official Results' : 'Certification in Progress'}
                                    </span>
                                    <span className="rounded-md border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-600">
                                        {electionTypeLabel(election)}
                                    </span>
                                    {hasStats && (
                                        <span className="rounded-md border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-600">
                                            {numeric(stats?.stations_reported).toLocaleString()} / {numeric(stats?.total_stations).toLocaleString()} stations certified
                                        </span>
                                    )}
                                </div>
                            )}
                            {election && (
                                <p className="mt-4 text-base text-slate-600 max-w-2xl leading-relaxed">
                                    Results officially certified by the IEC Chairman are displayed below. Figures update as each polling station result is certified.
                                </p>
                            )}
                        </div>

                        {/* Election switcher */}
                        <div className="mt-8">
                            <ElectionSelector
                                elections={elections}
                                selectedElectionId={selectedElectionId}
                                basePath={isHome ? '/' : '/results'}
                            />
                        </div>
                    </div>


                    {/* ── Main content ──────────────────────────────────────────── */}
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12 space-y-8">

                        {/* No election */}
                        {!election && <NoElection />}

                        {/* Election exists but no published results */}
                        {election && !hasStats && (
                            <AwaitingResults election={election} message={message} />
                        )}

                        {/* Published results exist */}
                        {election && hasStats && (
                            <>
                                {/* ── CANDIDATE LEADERBOARD ─────────────────────── */}

                                    <div className="flex flex-wrap items-end justify-between gap-3 mb-5">
                                        <div>
                                            <h2 className="text-2xl sm:text-3xl font-extrabold text-slate-950">
                                                Candidate Results
                                            </h2>
                                            <p className="mt-1 text-sm text-slate-500">
                                                Official vote totals from {numeric(stats?.stations_reported).toLocaleString()} certified polling stations
                                            </p>
                                        </div>
                                        <Link
                                            href={`/results/stations${param}`}
                                            className="text-sm font-bold text-[var(--iec-pink)] hover:text-[var(--iec-pink-dark)] flex items-center gap-1"
                                        >
                                            View by station →
                                        </Link>
                                    </div>

                                    {hasCandidates ? (
                                        <div className="space-y-3">
                                            {candidates.map((candidate, idx) => (
                                                <CandidateRow
                                                    key={candidate.id}
                                                    candidate={candidate}
                                                    rank={idx + 1}
                                                    totalValidVotes={totalValidVotes}
                                                />
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center">
                                            <p className="text-slate-500">Candidate vote totals will appear as results are certified.</p>
                                        </div>
                                    )}

                                    {/* Valid / rejected summary */}
                                    {hasCandidates && (
                                        <div className="mt-4 flex flex-wrap gap-4 text-sm text-slate-500">
                                            <span>
                                                <strong className="text-emerald-700">{formatVotes(stats?.valid_votes)}</strong> valid votes
                                            </span>
                                            <span>
                                                <strong className="text-amber-600">{formatVotes(stats?.rejected_votes)}</strong> rejected ballots
                                            </span>
                                        </div>
                                    )}

                                        {/* Stats row */}
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                                        <StatPill
                                            label="Registered Voters"
                                            value={formatVotes(stats?.total_registered)}
                                        />
                                        <StatPill
                                            label="Votes Cast"
                                            value={formatVotes(stats?.total_cast)}
                                            accent="text-[var(--iec-pink)]"
                                        />
                                        <StatPill
                                            label="Voter Turnout"
                                            value={`${turnout}%`}
                                            accent="text-sky-700"
                                        />
                                        <StatPill
                                            label="Stations Certified"
                                            value={`${numeric(stats?.stations_reported).toLocaleString()} / ${numeric(stats?.total_stations).toLocaleString()}`}
                                            accent="text-emerald-600"
                                            sub={`${numeric(stats?.total_stations) > 0 ? Math.round((numeric(stats?.stations_reported) / numeric(stats?.total_stations)) * 100) : 0}% of stations`}
                                        />
                                    </div>



                            {/* ── MAP SECTION ──────────────────────────────── */}
                            <section>
                                <div className="mb-4">
                                    <h2 className="text-2xl sm:text-3xl font-extrabold text-slate-950">
                                        Results Map
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Interactive map showing certified polling station results across The Gambia
                                    </p>
                                </div>
                                <MapTeaser election={election} stats={stats} param={param} />
                            </section>
                        </>
                    )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
