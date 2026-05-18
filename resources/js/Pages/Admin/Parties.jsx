import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button, DataTable, PageHeader, Panel } from '@/Components/AdminUI';

function ColorDots({ colorsArray }) {
    if (!colorsArray || colorsArray.length === 0) return null;
    return (
        <div className="flex gap-1 items-center">
            {colorsArray.map((color, i) => (
                <span
                    key={i}
                    className="w-5 h-5 rounded-full border-2 border-slate-200 flex-shrink-0"
                    style={{ backgroundColor: color }}
                    title={color}
                />
            ))}
            <span className="ws-row-muted ml-1">{colorsArray.join(' · ')}</span>
        </div>
    );
}

export default function Parties({ auth, parties = [], flash, activeElection }) {
    const [deletingId, setDeletingId] = useState(null);
    const [view, setView] = useState('table');

    const handleRegister = () => router.visit('/admin/parties/create');
    const handleEdit = (id) => router.visit(`/admin/parties/${id}/edit`);

    const handleDelete = (party) => {
        if (!window.confirm(`DELETE party "${party.name}"?\n\nThis will permanently remove the party, all its candidates, and related data. This cannot be undone.`)) return;
        setDeletingId(party.id);
        router.delete(`/admin/parties/${party.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingId(null),
            onError: (errors) => {
                setDeletingId(null);
                alert(errors?.error || 'Failed to delete party.');
            },
            onFinish: () => setDeletingId(null),
        });
    };

    const columns = [
        {
            key: 'party',
            header: 'Party',
            render: (party) => (
                <div className="flex items-center gap-3">
                    {party.symbol_url ? (
                        <img
                            src={party.symbol_url}
                            alt={`${party.name} symbol`}
                            className="h-10 w-10 rounded-md border border-gray-200 bg-white object-contain p-1"
                        />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-sm font-bold text-slate-600">
                            {party.abbreviation?.slice(0, 2)}
                        </div>
                    )}
                    <div>
                        <div className="ws-row-strong">{party.name}</div>
                        <div className="ws-row-muted">{party.abbreviation}</div>
                    </div>
                </div>
            ),
        },
        {
            key: 'colors',
            header: 'Colors',
            render: (party) => <ColorDots colorsArray={party.colors_array} />,
        },
        {
            key: 'candidates',
            header: 'Candidates',
            align: 'center',
            render: (party) => party.candidates?.length ?? 0,
        },
        {
            key: 'leader',
            header: 'Leader',
            render: (party) => {
                if (!party.leader_name && !party.leader_photo_url) {
                    return <span className="text-slate-400">Not set</span>;
                }
                const primaryColor = party.colors_array?.[0] || '#6b7280';
                return (
                    <div className="flex items-center gap-2">
                        {party.leader_photo_url ? (
                            <img
                                src={party.leader_photo_url}
                                alt={party.leader_name}
                                className="w-8 h-8 rounded-full object-cover border border-slate-200 flex-shrink-0"
                            />
                        ) : (
                            <div
                                className="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                style={{ backgroundColor: primaryColor }}
                            >
                                {party.leader_name?.charAt(0) || '?'}
                            </div>
                        )}
                        <span className="text-sm">{party.leader_name}</span>
                    </div>
                );
            },
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (party) => (
                <div className="flex justify-end gap-2">
                    <Button onClick={() => handleEdit(party.id)} variant="secondary">Edit</Button>
                    <Button onClick={() => handleDelete(party)} disabled={deletingId === party.id} variant="danger">
                        {deletingId === party.id ? 'Deleting...' : 'Delete'}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout user={auth?.user}>
            <div className="ws-container">
                <PageHeader
                    title="Political Party Management"
                    description={activeElection
                        ? `Showing parties for ${activeElection.name}`
                        : 'No active election. Activate an election to manage parties and candidates.'}
                    actions={(
                        <>
                            <div className="inline-flex rounded-md border border-gray-200 bg-white p-1">
                                <button
                                    type="button"
                                    onClick={() => setView('table')}
                                    className={`rounded px-3 py-1.5 text-sm font-semibold ${view === 'table' ? 'bg-iec-pink text-white' : 'text-slate-600 hover:bg-gray-50'}`}
                                >
                                    Table
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setView('cards')}
                                    className={`rounded px-3 py-1.5 text-sm font-semibold ${view === 'cards' ? 'bg-iec-pink text-white' : 'text-slate-600 hover:bg-gray-50'}`}
                                >
                                    Cards
                                </button>
                            </div>
                            <Button onClick={handleRegister}>Register Party</Button>
                        </>
                    )}
                />

                {flash?.success && (
                    <div className="mb-6 p-4 bg-green-500/20 border border-green-500/50 rounded-lg text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300">
                        {flash.error}
                    </div>
                )}

                {parties.length === 0 ? (
                    <Panel className="p-12 text-center">
                        <p className="mb-4 text-slate-400">
                            {activeElection
                                ? `No parties registered for ${activeElection.name} yet.`
                                : 'No active election found.'}
                        </p>
                        {activeElection && (
                            <Button onClick={handleRegister}>Register First Party</Button>
                        )}
                    </Panel>
                ) : view === 'table' ? (
                    <DataTable columns={columns} rows={parties} empty="No parties registered for the active election." />
                ) : (
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        {parties.map((party) => {
                            const primaryColor = party.colors_array?.[0] || '#6b7280';

                            return (
                                <Panel
                                    key={party.id}
                                    className="p-5"
                                >
                                    <div className="flex min-h-full flex-col">
                                        <div className="mb-5 flex items-start gap-4">
                                            <div className="flex-shrink-0">
                                                {party.symbol_url ? (
                                                    <img
                                                        src={party.symbol_url}
                                                        alt={`${party.name} symbol`}
                                                        className="h-14 w-14 rounded-lg border border-gray-200 bg-white object-contain p-1"
                                                    />
                                                ) : (
                                                    <div
                                                        className="flex h-14 w-14 items-center justify-center rounded-lg border border-gray-200 text-lg font-bold text-slate-600"
                                                        style={{ backgroundColor: primaryColor + '22' }}
                                                    >
                                                        {party.abbreviation?.slice(0, 2)}
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex-1 min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="text-lg font-bold leading-tight text-slate-900">{party.name}</h3>
                                                    <Badge tone="slate">{party.abbreviation}</Badge>
                                                </div>
                                                {party.motto && (
                                                    <p className="mt-1 text-xs italic text-slate-500">{party.motto}</p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="mb-5">
                                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Party Colors</p>
                                            <ColorDots colorsArray={party.colors_array} />
                                        </div>

                                        {(party.leader_name || party.leader_photo_url) && (
                                            <div className="mb-5 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                                {party.leader_photo_url ? (
                                                    <img
                                                        src={party.leader_photo_url}
                                                        alt={party.leader_name}
                                                        className="w-12 h-12 rounded-full object-cover border-2 flex-shrink-0"
                                                        style={{ borderColor: primaryColor }}
                                                    />
                                                ) : (
                                                    <div
                                                        className="w-12 h-12 rounded-full flex items-center justify-center text-iec-navy font-bold text-sm flex-shrink-0"
                                                        style={{ backgroundColor: primaryColor + '44' }}
                                                    >
                                                        {party.leader_name?.charAt(0) || '?'}
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Party Leader</p>
                                                    <p className="truncate text-sm font-semibold text-slate-900">{party.leader_name || '-'}</p>
                                                </div>
                                            </div>
                                        )}

                                        <div className="mb-5">
                                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Candidates ({party.candidates?.length ?? 0})
                                                {activeElection && (
                                                    <span className="ml-1 normal-case text-slate-500">for {activeElection.name}</span>
                                                )}
                                            </p>
                                            {party.candidates && party.candidates.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {party.candidates.map((candidate) => (
                                                        <div key={candidate.id} className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5">
                                                            {candidate.photo_url ? (
                                                                <img
                                                                    src={candidate.photo_url}
                                                                    alt={candidate.name}
                                                                    className="w-7 h-7 rounded-full object-cover flex-shrink-0 border border-slate-200"
                                                                />
                                                            ) : (
                                                                <div
                                                                    className="w-7 h-7 rounded-full flex items-center justify-center text-iec-navy text-xs font-bold flex-shrink-0"
                                                                    style={{ backgroundColor: primaryColor + '66' }}
                                                                >
                                                                    {candidate.name?.charAt(0) || '?'}
                                                                </div>
                                                            )}
                                                            <div>
                                                                <span className="block max-w-[160px] truncate text-xs font-medium text-slate-700">
                                                                    {candidate.name}
                                                                </span>
                                                                {candidate.ballot_number && (
                                                                    <span className="text-xs text-slate-600">#{candidate.ballot_number}</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-xs italic text-slate-600">
                                                    No candidates yet. Add them from Edit Party.
                                                </p>
                                            )}
                                        </div>

                                        <div className="mb-5 space-y-1 text-sm">
                                            {party.headquarters && (
                                                <p className="text-slate-400">
                                                    <span className="text-slate-500">HQ:</span> {party.headquarters}
                                                </p>
                                            )}
                                            {party.website && (
                                                <p className="text-slate-400">
                                                    <span className="text-slate-500">Web:</span>{' '}
                                                    <a href={party.website} target="_blank" rel="noopener noreferrer" className="text-iec-pink-300 hover:text-iec-pink-200">
                                                        {party.website.replace(/^https?:\/\//, '')}
                                                    </a>
                                                </p>
                                            )}
                                        </div>

                                        <div className="mt-auto flex gap-2">
                                            <Button onClick={() => handleEdit(party.id)} variant="secondary" className="flex-1">Edit Party & Candidates</Button>
                                            <Button onClick={() => handleDelete(party)} disabled={deletingId === party.id} variant="danger">
                                                {deletingId === party.id ? 'Deleting...' : 'Delete'}
                                            </Button>
                                        </div>
                                    </div>
                                </Panel>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
