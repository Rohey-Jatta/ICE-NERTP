import AppLayout from '@/Layouts/AppLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';
import { electionTypeLabel, publicElectionTitle } from '@/Utils/publicElection';

// ── Helpers ───────────────────────────────────────────────────────────────────
function numeric(v) { return Number(v || 0); }

function pct(value, total, precision = 2) {
    return total > 0 ? ((numeric(value) / numeric(total)) * 100).toFixed(precision) : (0).toFixed(precision);
}

function candidateInitials(name = '') {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

function firstColor(value, fallback = '#6b7280') {
    return (value || fallback).split(',')[0].trim();
}

// ── Candidate tile ──────────────────────────────────────────────────────────
function CandidateTile({ candidate, rank, totalValidVotes }) {
    const color = firstColor(candidate.party_color, '#155AA6');
    const isLeading = rank === 1;
    const share = pct(candidate.total_votes, totalValidVotes);
    const [imgError, setImgError] = useState(false);

    return (
        <article
            className={`relative flex flex-col overflow-hidden rounded-[14px] border bg-white transition hover:-translate-y-0.5 ${
                isLeading
                    ? 'border-[#e61a6e] shadow-[0_0_0_3px_#fff5fa,0_12px_30px_-10px_rgba(230,26,110,.22)]'
                    : 'border-[#e6e8ec] hover:border-[#353b45] hover:shadow-[0_12px_30px_-10px_rgba(15,17,21,.14)]'
            }`}
        >
            {/* Rank / leading pin */}
            {isLeading ? (
                <span className="absolute right-3.5 top-3.5 z-20 rounded-full bg-[#e61a6e] px-2.5 py-1 text-[0.66rem] font-bold uppercase tracking-[0.06em] text-white">
                    Leading
                </span>
            ) : (
                <span className="absolute right-3.5 top-3.5 z-20 grid h-[26px] w-[26px] place-items-center rounded-full border border-[#e6e8ec] bg-white/70 font-serif text-[13px] font-bold text-[#8b95a3]">
                    #{rank}
                </span>
            )}

            {/* Head */}
            <div className="relative flex items-center gap-[18px] overflow-hidden border-b border-[#e6e8ec] bg-[#fafafb] px-[22px] py-[18px] pt-[22px]">
                <div
                    className="pointer-events-none absolute inset-0"
                    style={{
                        background: `linear-gradient(120deg, ${color} 0%, transparent 65%)`,
                        opacity: isLeading ? 0.18 : 0.1,
                    }}
                />
                {candidate.photo_url && !imgError ? (
                    <img
                        src={candidate.photo_url}
                        alt={candidate.name}
                        onError={() => setImgError(true)}
                        className="relative z-10 h-[84px] w-[84px] flex-shrink-0 rounded-full object-cover shadow-[0_0_0_4px_#fff,0_0_0_5px_#e6e8ec,0_6px_14px_rgba(15,17,21,.08)]"
                        style={isLeading ? { boxShadow: `0 0 0 4px #fff, 0 0 0 6px ${color}, 0 6px 14px rgba(15,17,21,.10)` } : undefined}
                    />
                ) : (
                    <div
                        className="relative z-10 grid h-[84px] w-[84px] flex-shrink-0 place-items-center rounded-full font-serif text-[28px] font-bold tracking-tight text-white shadow-[0_0_0_4px_#fff,0_0_0_5px_#e6e8ec,0_6px_14px_rgba(15,17,21,.08)]"
                        style={{
                            backgroundColor: color,
                            ...(isLeading ? { boxShadow: `0 0 0 4px #fff, 0 0 0 6px ${color}, 0 6px 14px rgba(15,17,21,.10)` } : {}),
                        }}
                    >
                        {candidateInitials(candidate.name)}
                    </div>
                )}

                <div className="relative z-10 min-w-0 flex-1 pr-12">
                    <div className="font-serif text-[19px] font-bold leading-tight tracking-tight text-[#0e1014]">
                        {candidate.name}
                    </div>
                    <div className="mt-2 flex items-center gap-2">
                        <span
                            className="grid h-7 min-w-7 flex-shrink-0 place-items-center rounded-md px-1.5 text-[10px] font-bold tracking-wide text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,.18)]"
                            style={{ backgroundColor: color }}
                        >
                            {candidate.party_abbr || 'IND'}
                        </span>
                        <span className="line-clamp-2 text-[12.5px] leading-snug text-[#5f6773]">
                            <b className="font-semibold text-[#1f2329]">{candidate.party_abbr || 'IND'}</b>
                            {candidate.party_name && candidate.party_name !== 'Independent' && <> · {candidate.party_name}</>}
                        </span>
                    </div>
                </div>
            </div>

            {/* Body */}
            <div className="flex flex-col gap-3.5 px-[22px] py-5">
                <div className="flex items-baseline justify-between gap-3.5">
                    <div>
                        <div className="font-serif text-[36px] font-bold leading-none tracking-tight tabular-nums text-[#0e1014]">
                            {numeric(candidate.total_votes).toLocaleString()}
                        </div>
                        <div className="mt-1 text-[11px] font-bold uppercase tracking-[0.08em] text-[#5f6773]">Votes</div>
                    </div>
                    <div className="text-right">
                        <div className="font-serif text-[22px] font-bold leading-none tracking-tight tabular-nums" style={{ color }}>
                            {share}%
                        </div>
                        <div className="mt-1 text-[11px] font-bold uppercase tracking-[0.08em] text-[#5f6773]">Share</div>
                    </div>
                </div>
                <div className="relative h-2 overflow-hidden rounded-[4px] bg-[#f1f3f5]">
                    <div
                        className="absolute inset-y-0 left-0 rounded-[4px] transition-[width] duration-700"
                        style={{ width: `${Math.max(2, Number(share))}%`, backgroundColor: color }}
                    />
                </div>
                <div className="flex justify-between text-[11.5px] tabular-nums text-[#5f6773]">
                    <span>{candidate.incumbent || isLeading ? 'Leading candidate' : 'Challenger'}</span>
                    <span>Rank #{rank}</span>
                </div>
            </div>
        </article>
    );
}

// ── Certification workflow step ───────────────────────────────────────────────
function WorkflowStep({ step, index }) {
    const isDone = step.state === 'done';
    const isActive = step.state === 'active';

    return (
        <div className="border-b border-r border-[#e6e8ec] px-[18px] py-[18px] pb-5 last:border-r-0">
            <div
                className={`mb-3 grid h-6 w-6 place-items-center rounded-full border text-xs font-bold ${
                    isDone
                        ? 'border-[#0e8c5a] bg-[#0e8c5a] text-white'
                        : isActive
                        ? 'border-[#e61a6e] bg-[#e61a6e] text-white'
                        : 'border-[#e6e8ec] bg-[#f5f6f8] text-[#5f6773]'
                }`}
            >
                {isDone ? '✓' : index + 1}
            </div>
            <div className={`text-[13.5px] font-semibold ${isActive ? 'text-[#e61a6e]' : 'text-[#0e1014]'}`}>
                {step.title}
            </div>
            <div className="mt-1 text-[12px] leading-snug text-[#5f6773]">{step.description}</div>
            {step.meta && <div className="mt-1.5 text-[11px] font-medium text-[#5f6773]">{step.meta}</div>}
        </div>
    );
}

// ── Regional leaders bars (derived from admin_area hierarchy) ──────────────────
function RegionalBars({ regions }) {
    const maxStations = Math.max(1, ...regions.map((r) => numeric(r.total_stations)));

    return (
        <div className="flex flex-col gap-2.5">
            {regions.map((region) => {
                const widthPct = (numeric(region.total_stations) / maxStations) * 100;
                const leader = region.leader;
                const color = leader ? firstColor(leader.color) : '#e6e8ec';

                return (
                    <div
                        key={region.id}
                        className="grid items-center gap-3 text-[13px] sm:grid-cols-[120px_1fr_92px]"
                    >
                        <span className="truncate font-semibold text-[#1f2329]">{region.name}</span>
                        <div className="relative h-6 overflow-hidden rounded-[4px] bg-[#f1f3f5]">
                            <div
                                className="absolute inset-y-0 left-0 rounded-[4px] opacity-90 transition-[width] duration-700"
                                style={{ width: `${Math.max(leader ? 6 : 0, widthPct)}%`, backgroundColor: color }}
                            />
                            {leader ? (
                                <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-semibold tracking-[0.02em] text-white">
                                    {leader.party_abbr} · {numeric(region.leader_pct).toFixed(1)}%
                                </span>
                            ) : (
                                <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-[#8b95a3]">
                                    Awaiting certified results
                                </span>
                            )}
                        </div>
                        <span className="text-right text-[12px] tabular-nums text-[#5f6773]">
                            {numeric(region.total_stations).toLocaleString()} stations
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

// ── Pipeline breakdown widget (from main) ─────────────────────────────────────
function PipelineBreakdown({ pipeline = {}, totalStations = 0 }) {
    const submitted    = Number(pipeline.submitted    ?? 0);
    const underReview  = Number(pipeline.under_review ?? 0);
    const certified    = Number(pipeline.certified    ?? 0);
    const notReported  = Math.max(0, totalStations - submitted - underReview - certified);
    const items = [
        { label: 'Submitted',   value: submitted,   color: 'text-blue-700',    bg: 'bg-blue-50',    border: 'border-blue-200'    },
        { label: 'Under Review',value: underReview,  color: 'text-amber-700',   bg: 'bg-amber-50',   border: 'border-amber-200'   },
        { label: 'Certified',   value: certified,    color: 'text-emerald-700', bg: 'bg-emerald-50', border: 'border-emerald-200' },
        { label: 'Not Reported',value: notReported,  color: 'text-slate-500',   bg: 'bg-slate-50',   border: 'border-slate-200'   },
    ];
    return (
        <div className="rounded-[14px] border border-[#e6e8ec] bg-white p-5">
            <p className="mb-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[#5f6773]">Certification Pipeline</p>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {items.map((item) => (
                    <div key={item.label} className={`rounded-lg border px-3 py-2 text-center ${item.bg} ${item.border}`}>
                        <div className={`text-lg font-extrabold tabular-nums ${item.color}`}>{item.value.toLocaleString()}</div>
                        <div className="text-xs text-slate-500">{item.label}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Dashboard ─────────────────────────────────────────────────────────────────
function HomeResultsPage({ election, elections, selectedElectionId, stats, pipeline, candidates, regions, message, basePath, param }) {
    const totalValidVotes = numeric(stats?.valid_votes);
    const totalStations = numeric(stats?.total_stations);
    const stationsReported = numeric(stats?.stations_reported);
    const totalRegistered = numeric(stats?.total_registered);
    const totalCast = numeric(stats?.total_cast);
    const rejectedVotes = numeric(stats?.rejected_votes);
    const reportingPct = totalStations > 0 ? Math.round((stationsReported / totalStations) * 100) : 0;
    const turnoutPct = totalRegistered > 0 ? ((totalCast / totalRegistered) * 100).toFixed(1) : '0.0';
    const validPct = totalCast > 0 ? ((totalValidVotes / totalCast) * 100).toFixed(2) : '0.00';
    const rejectedPct = totalCast > 0 ? ((rejectedVotes / totalCast) * 100).toFixed(2) : '0.00';

    // stats is always present as an aggregate row even when no results are certified
    // yet, so we gate the empty-state on whether any stations have actually reported.
    const hasPublishedResults = stationsReported > 0;
    const hasStats = hasPublishedResults;
    const hasCandidates = Array.isArray(candidates) && candidates.length > 0;
    const regionList = Array.isArray(regions) ? regions : [];
    const isCertified = election?.status === 'certified';
    const statusLabel = isCertified ? 'Official Certified Results' : 'Certification in Progress';

    const workflow = [
        { title: 'Stations Submit', description: 'Polling officers submit results', state: stationsReported > 0 ? 'done' : 'active', meta: stationsReported > 0 ? `${stationsReported.toLocaleString()} received` : 'Awaiting submissions' },
        { title: 'Validation', description: 'System checks math and signatures', state: stationsReported > 0 ? 'done' : 'todo', meta: hasStats ? `${validPct}% valid of cast` : null },
        { title: 'Regional Review', description: 'Returning officers compare and sign', state: hasStats && !isCertified ? 'active' : hasStats ? 'done' : 'todo', meta: hasStats ? `${reportingPct}% reporting` : null },
        { title: 'Audit & Recount', description: 'Independent observers verify flagged data', state: isCertified ? 'done' : 'todo' },
        { title: 'Final Certification', description: 'IEC commissioners certify and publish', state: isCertified ? 'done' : 'todo' },
    ];

    return (
        <AppLayout>
            <div className="min-h-screen overflow-x-hidden bg-white font-sans text-[#0e1014]">
                {/* ── Hero ─────────────────────────────────────────────────── */}
                <section className="border-b border-[#e6e8ec] bg-white">
                    <div className="mx-auto max-w-[1240px] px-7 py-9">
                        <div className="grid items-end gap-8 lg:grid-cols-[1fr_auto]">
                            <div className="min-w-0">
                                <div className="mb-[18px] flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-transparent bg-[#fef3c7] px-[11px] py-[5px] text-xs font-semibold text-[#b45309]">
                                        <span className="h-1.5 w-1.5 rounded-full bg-[#b45309]" />
                                        {statusLabel}
                                    </span>
                                    <span className="rounded-full border border-[#e6e8ec] bg-[#f5f6f8] px-[11px] py-[5px] text-xs font-semibold text-[#1f2329]">
                                        {electionTypeLabel(election)}
                                    </span>
                                    <span className="rounded-full border border-[#e6e8ec] bg-[#f5f6f8] px-[11px] py-[5px] text-xs font-semibold tabular-nums text-[#1f2329]">
                                        {stationsReported.toLocaleString()} / {totalStations.toLocaleString()} stations reporting · {reportingPct}%
                                    </span>
                                </div>
                                <h1 className="m-0 max-w-[calc(100vw-2rem)] break-words font-serif text-[clamp(40px,5.6vw,64px)] font-bold leading-[1.02] tracking-[-0.025em] text-[#0e1014]">
                                    {publicElectionTitle(election)}
                                </h1>
                                <p className="mt-2.5 max-w-[580px] text-base leading-7 text-[#5f6773]">
                                    Independent Electoral Commission · The Gambia · Updated as certified polling station results are published.
                                </p>
                            </div>

                            {elections.length > 1 && (
                                <div className="min-w-0 lg:w-80">
                                    <p className="mb-1 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Select election</p>
                                    <select
                                        value={selectedElectionId || ''}
                                        onChange={(event) => router.get(basePath, { election: event.target.value }, { preserveScroll: false })}
                                        className="w-full rounded-[10px] border border-[#e6e8ec] bg-white px-4 py-3 text-sm font-semibold text-[#0e1014] outline-none transition focus:border-[#e61a6e] focus:ring-4 focus:ring-[#e61a6e]/10"
                                    >
                                        {elections.map((item) => (
                                            <option key={item.id} value={item.id}>{publicElectionTitle(item)}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {!hasStats && (
                    <div className="mx-auto max-w-[1240px] px-7 py-16">
                        <div className="mx-auto max-w-xl rounded-[14px] border border-dashed border-[#e6e8ec] bg-white p-10 text-center">
                            <h2 className="font-serif text-3xl font-bold text-[#0e1014]">Awaiting Published Results</h2>
                            <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-[#5f6773]">
                                {message || 'Results will appear here as polling stations are officially certified and published.'}
                            </p>

                            <p className="text-xs text-gray-400 mt-2">
                                Page auto-refreshes every 60 seconds.
                            </p>
                        </div>
                    </div>
                )}

                {hasStats && (
                    <>
                        {/* ── Candidate results ─────────────────────────────── */}
                        {hasCandidates && (
                            <section className="py-6">
                                <div className="mx-auto max-w-[1240px] px-7">
                                    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                        <div>
                                            <div className="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#e61a6e]">Candidate Results</div>
                                            <h2 className="mt-1.5 font-serif text-[clamp(24px,2.4vw,30px)] font-bold tracking-[-0.02em] text-[#0e1014]">
                                                Vote totals by candidate
                                            </h2>
                                            <p className="mt-1 text-sm text-[#5f6773]">
                                                {hasPublishedResults ? 'Published totals' : 'Candidate slate'} from {stationsReported.toLocaleString()} reporting stations ·
                                                <span className="font-semibold text-[#0e8c5a]"> {totalValidVotes.toLocaleString()} valid votes </span>·
                                                <span className="font-semibold text-[#b45309]"> {rejectedVotes.toLocaleString()} rejected</span>
                                            </p>
                                        </div>
                                        <Link
                                            href={`/results/stations${param}`}
                                            className="inline-flex items-center gap-2 self-start rounded-lg border border-[#e6e8ec] px-4 py-2.5 text-sm font-medium text-[#1f2329] transition hover:border-[#353b45] hover:bg-[#f5f6f8]"
                                        >
                                            Browse by station <span aria-hidden>→</span>
                                        </Link>
                                    </div>
                                    <div className="grid grid-cols-1 gap-[18px] sm:grid-cols-2 lg:grid-cols-3">
                                        {candidates.map((candidate, index) => (
                                            <CandidateTile
                                                key={candidate.id}
                                                candidate={candidate}
                                                rank={index + 1}
                                                totalValidVotes={totalValidVotes}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </section>
                        )}

                        {/* ── Stat strip ────────────────────────────────────── */}
                        <section className="grid border-y border-[#e6e8ec] bg-white sm:grid-cols-2 lg:grid-cols-5">
                            {[
                                ['Registered Voters', totalRegistered.toLocaleString(), 'National roll'],
                                ['Votes Cast', totalCast.toLocaleString(), `${turnoutPct}% of registered`],
                                ['Valid Votes', totalValidVotes.toLocaleString(), `${validPct}% of cast`, 'text-[#0e8c5a]'],
                                ['Rejected', rejectedVotes.toLocaleString(), `${rejectedPct}% rejection rate`],
                                ['Turnout', `${turnoutPct}%`, 'National average', 'text-[#e61a6e]'],
                            ].map(([label, value, helper, accent]) => (
                                <div key={label} className="border-r border-[#e6e8ec] px-6 py-7 last:border-r-0">
                                    <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">{label}</div>
                                    <div className={`mt-3 font-serif text-[clamp(28px,3vw,38px)] font-bold leading-none tracking-[-0.025em] tabular-nums ${accent || 'text-[#0e1014]'}`}>
                                        {value}
                                    </div>
                                    <div className="mt-2 text-[12.5px] tabular-nums text-[#5f6773]">{helper}</div>
                                </div>
                            ))}
                        </section>

                        {/* ── Reporting + certification workflow ────────────── */}
                        <section className="py-9">
                            <div className="mx-auto grid max-w-[1240px] gap-5 px-7 lg:grid-cols-[1fr_1.6fr]">
                                <div className="rounded-[14px] border border-[#e6e8ec] bg-white px-6 py-5">
                                    <div className="mb-3.5 flex items-baseline justify-between gap-4">
                                        <div>
                                            <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5f6773]">Reporting Progress</div>
                                            <div className="mt-1 text-[13px] tabular-nums text-[#5f6773]">{stationsReported.toLocaleString()} of {totalStations.toLocaleString()} stations</div>
                                        </div>
                                        <div className="font-serif text-[32px] font-bold leading-none tracking-[-0.02em] tabular-nums text-[#0e1014]">{reportingPct}%</div>
                                    </div>
                                    <div className="relative h-2.5 overflow-hidden rounded-[5px] bg-[#f1f3f5]">
                                        <div
                                            className="absolute inset-y-0 left-0 rounded-[5px] bg-gradient-to-r from-[#e61a6e] to-[#b81259] transition-[width] duration-700"
                                            style={{ width: `${reportingPct}%` }}
                                        />
                                    </div>
                                    <div className="mt-3.5 flex flex-wrap justify-between gap-2 text-xs text-[#5f6773]">
                                        <span>Certification window open</span>
                                        <span className="tabular-nums">{stationsReported.toLocaleString()} published results</span>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-4">
                                    <div className="grid overflow-hidden rounded-[14px] border border-[#e6e8ec] bg-white sm:grid-cols-2 lg:grid-cols-5">
                                        {workflow.map((step, index) => (
                                            <WorkflowStep key={step.title} step={step} index={index} />
                                        ))}
                                    </div>
                                    {pipeline && <PipelineBreakdown pipeline={pipeline} totalStations={totalStations} />}
                                </div>
                            </div>
                        </section>

                        {/* ── Regional leaders ──────────────────────────────── */}
                        {regionList.length > 0 && (
                            <section className="border-t border-[#e6e8ec] bg-[#fafafb] py-9">
                                <div className="mx-auto max-w-[1240px] px-7">
                                    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                        <div>
                                            <div className="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#e61a6e]">By region</div>
                                            <h2 className="mt-1.5 font-serif text-[clamp(24px,2.4vw,30px)] font-bold tracking-[-0.02em] text-[#0e1014]">
                                                Regional leaders
                                            </h2>
                                            <p className="mt-1 max-w-2xl text-sm text-[#5f6773]">
                                                Leading candidate by certified votes in each administrative region. Open the map for full regional results.
                                            </p>
                                        </div>
                                        <Link
                                            href={`/results/map${param}`}
                                            className="inline-flex items-center gap-2 self-start rounded-lg border border-[#e6e8ec] bg-white px-4 py-2.5 text-sm font-medium text-[#1f2329] transition hover:border-[#353b45] hover:bg-[#f5f6f8]"
                                        >
                                            Open map <span aria-hidden>→</span>
                                        </Link>
                                    </div>
                                    <div className="rounded-[14px] border border-[#e6e8ec] bg-white p-6">
                                        <RegionalBars regions={regionList} />
                                    </div>
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

// ── Main component ──────────────────────────────────────────────────────────
export default function Results({ election, elections = [], selectedElectionId, stats, pipeline, candidates, regions = [], message }) {
    const param = selectedElectionId ? `?election=${selectedElectionId}` : '';
    const basePath = '/results';

    useInertiaPrefetch([`/results/map${param}`, `/results/stations${param}`]);

    if (election) {
        return (
            <HomeResultsPage
                election={election}
                elections={elections}
                selectedElectionId={selectedElectionId}
                stats={stats}
                pipeline={pipeline}
                candidates={candidates}
                regions={regions}
                message={message}
                basePath={basePath}
                param={param}
            />
        );
    }

    // ── No election configured ────────────────────────────────────────────────
    return (
        <AppLayout>
            <div className="flex min-h-screen items-center justify-center bg-[#fafafb] p-8">
                <div className="max-w-sm rounded-[14px] border border-[#e6e8ec] bg-white p-10 text-center">
                    <div className="mb-4 text-4xl">🏛️</div>
                    <h1 className="mb-2 text-xl font-bold text-[#0e1014]">No Active Election</h1>
                    <p className="text-sm text-[#5f6773]">
                        No election is currently configured for public display.
                    </p>
                    {elections.length > 0 && (
                        <div className="mt-4 flex flex-wrap justify-center gap-2">
                            {elections.map((el) => (
                                <button
                                    key={el.id}
                                    onClick={() => router.get(basePath, { election: el.id })}
                                    className="rounded-md border border-[#e6e8ec] px-3 py-1.5 text-xs text-[#5f6773] transition-colors hover:border-[#353b45]"
                                >
                                    {publicElectionTitle(el)}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
