import { Badge, Button, Field, PageHeader, Panel, inputClass } from '@/Components/AdminUI';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function PartySelector({ allParties = [], selectedIds = [], onChange }) {
    const [expanded, setExpanded] = useState(null);

    const toggle = (partyId) => {
        onChange(selectedIds.includes(partyId)
            ? selectedIds.filter((id) => id !== partyId)
            : [...selectedIds, partyId]);
    };

    if (allParties.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-300 p-6 text-center">
                <p className="text-sm text-slate-500">No parties registered yet.</p>
                <Link href="/admin/parties/create" className="mt-1 inline-block text-sm font-semibold text-iec-pink underline">
                    Register a party first
                </Link>
            </div>
        );
    }

    return (
        <div className="max-h-80 space-y-2 overflow-y-auto pr-1">
            {allParties.map((party) => {
                const isSelected = selectedIds.includes(party.id);
                const isExpanded = expanded === party.id;
                const colorStyle = party.colors_array?.length > 1
                    ? { background: `linear-gradient(135deg, ${party.colors_array.join(', ')})` }
                    : { background: party.colors_array?.[0] || party.color || '#94a3b8' };

                return (
                    <div key={party.id} className={`ws-choice-card ${isSelected ? 'is-selected' : ''}`}>
                        <div className="flex items-center gap-3">
                            <div className="h-8 w-8 flex-shrink-0 rounded-md border border-gray-200" style={colorStyle} />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <span className="truncate text-sm font-semibold text-slate-900">{party.name}</span>
                                    <Badge tone="slate">{party.abbreviation}</Badge>
                                </div>
                                {party.leader_name && <div className="truncate text-xs text-slate-500">Leader: {party.leader_name}</div>}
                            </div>
                            {party.candidate_count > 0 && (
                                <button type="button" onClick={() => setExpanded(isExpanded ? null : party.id)} className="text-xs font-semibold text-slate-500 hover:text-iec-pink">
                                    {party.candidate_count} candidates
                                </button>
                            )}
                            <Button type="button" onClick={() => toggle(party.id)} variant={isSelected ? 'primary' : 'secondary'}>
                                {isSelected ? 'Added' : 'Add'}
                            </Button>
                        </div>
                        {isExpanded && party.candidates?.length > 0 && (
                            <div className="mt-3 border-t border-gray-200 pt-3">
                                <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Candidates</div>
                                <div className="space-y-1">
                                    {party.candidates.map((candidate) => (
                                        <div key={candidate.id} className="flex items-center gap-2 text-sm text-slate-600">
                                            <span className="w-6 flex-shrink-0 text-right font-mono text-xs text-slate-400">{candidate.ballot_number || '-'}</span>
                                            <span className="truncate">{candidate.name}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export default function ElectionCreate({ auth, allParties = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'parliamentary',
        date: '',
        allow_provisional_public_display: true,
        party_ids: [],
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post('/admin/elections');
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container max-w-4xl">
                <PageHeader
                    title="Create Election"
                    description="Set election details and choose the parties that will participate."
                    backHref="/admin/elections"
                    backLabel="Back to Elections"
                />

                <form onSubmit={handleSubmit} className="space-y-5">
                    <Panel className="p-5">
                        <div className="mb-5">
                            <h2 className="ws-section-title">Election Details</h2>
                            <p className="ws-section-desc">Basic information used across admin and public results pages.</p>
                        </div>
                        <div className="space-y-5">
                            <Field label="Election Name">
                                <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className={inputClass} placeholder="e.g., 2025 National Assembly Elections" required />
                                {errors.name && <p className="mt-1 text-sm text-rose-600">{errors.name}</p>}
                            </Field>
                            <div className="ws-form-grid">
                                <Field label="Election Type">
                                    <select value={data.type} onChange={(event) => setData('type', event.target.value)} className={inputClass}>
                                        <option value="presidential">Presidential</option>
                                        <option value="parliamentary">National Assembly</option>
                                        <option value="local">Regional / Local Government</option>
                                        <option value="referendum">Constituency / By-Election</option>
                                    </select>
                                    {errors.type && <p className="mt-1 text-sm text-rose-600">{errors.type}</p>}
                                </Field>
                                <Field label="Election Date">
                                    <input type="date" value={data.date} onChange={(event) => setData('date', event.target.value)} className={inputClass} required />
                                    {errors.date && <p className="mt-1 text-sm text-rose-600">{errors.date}</p>}
                                </Field>
                            </div>
                        </div>
                    </Panel>

                    <Panel className="p-5">
                        <label className="ws-toggle-card">
                            <span>
                                <span className="block font-semibold text-slate-900">Show on public homepage</span>
                                <span className="mt-1 block text-sm text-slate-500">When enabled, this election can be selected for public results, map, and station status pages.</span>
                            </span>
                            <input type="checkbox" checked={data.allow_provisional_public_display} onChange={(event) => setData('allow_provisional_public_display', event.target.checked)} />
                        </label>
                    </Panel>

                    <Panel className="p-5">
                        <div className="mb-5 flex items-start justify-between gap-3">
                            <div>
                                <h2 className="ws-section-title">Participating Parties</h2>
                                <p className="ws-section-desc">Select the parties contesting this election.</p>
                            </div>
                            <Link href="/admin/parties/create" className="text-sm font-semibold text-iec-pink underline">Register party</Link>
                        </div>

                        {data.party_ids.length > 0 && (
                            <p className="mb-3 text-sm font-semibold text-slate-700">{data.party_ids.length} parties selected</p>
                        )}

                        <PartySelector allParties={allParties} selectedIds={data.party_ids} onChange={(ids) => setData('party_ids', ids)} />
                        {errors.party_ids && <p className="mt-2 text-sm text-rose-600">{errors.party_ids}</p>}
                    </Panel>

                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={processing}>{processing ? 'Creating...' : 'Create Election'}</Button>
                        <Button href="/admin/elections" variant="secondary">Cancel</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
