import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

function ColorStripe({ colorsArray }) {
    if (!colorsArray || colorsArray.length === 0) {
        return <div className="w-full h-2 rounded-full bg-slate-600" />;
    }
    if (colorsArray.length === 1) {
        return <div className="w-full h-2 rounded-full" style={{ backgroundColor: colorsArray[0] }} />;
    }
    const gradient = `linear-gradient(to right, ${colorsArray.join(', ')})`;
    return <div className="w-full h-2 rounded-full" style={{ background: gradient }} />;
}

function ColorDots({ colorsArray }) {
    if (!colorsArray || colorsArray.length === 0) return null;
    return (
        <div className="flex gap-1 items-center">
            {colorsArray.map((color, i) => (
                <span
                    key={i}
                    className="w-5 h-5 rounded-full border-2 border-white/20 flex-shrink-0"
                    style={{ backgroundColor: color }}
                    title={color}
                />
            ))}
            <span className="text-gray-500 text-xs ml-1">{colorsArray.join(' · ')}</span>
        </div>
    );
}

export default function Parties({ auth, parties = [], flash, activeElection }) {
    const [deletingId, setDeletingId] = useState(null);

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

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="mb-6">
                    <Link href="/admin/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to Dashboard
                    </Link>
                    <div className="flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-white">Political Party Management</h1>
                            {activeElection ? (
                                <p className="text-teal-300 text-sm mt-1">
                                    Showing parties for: <strong>{activeElection.name}</strong>
                                </p>
                            ) : (
                                <p className="text-amber-400 text-sm mt-1">
                                    ⚠ No active election — activate an election to manage parties and candidates.
                                </p>
                            )}
                        </div>
                        <button onClick={handleRegister} className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                            + Register Party
                        </button>
                    </div>
                </div>

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
                    <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                        <div className="text-5xl mb-4">🏛️</div>
                        <p className="text-gray-400 mb-2">
                            {activeElection
                                ? `No parties registered for ${activeElection.name} yet.`
                                : 'No active election found.'}
                        </p>
                        {activeElection && (
                            <button
                                onClick={handleRegister}
                                className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg mt-2"
                            >
                                Register First Party
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {parties.map((party) => {
                            const primaryColor = party.colors_array?.[0] || '#6b7280';

                            return (
                                <div
                                    key={party.id}
                                    className="bg-slate-800/40 rounded-xl overflow-hidden border border-slate-700/50 flex flex-col"
                                >
                                    <ColorStripe colorsArray={party.colors_array} />

                                    <div className="p-6 flex-1 flex flex-col">
                                        <div className="flex items-start gap-4 mb-4">
                                            <div className="flex-shrink-0">
                                                {party.symbol_url ? (
                                                    <img
                                                        src={party.symbol_url}
                                                        alt={`${party.name} symbol`}
                                                        className="w-16 h-16 object-contain rounded-lg bg-white p-1 border border-slate-600"
                                                    />
                                                ) : (
                                                    <div
                                                        className="w-16 h-16 rounded-lg flex items-center justify-center text-white font-bold text-xl border border-slate-600"
                                                        style={{ backgroundColor: primaryColor + '44' }}
                                                    >
                                                        {party.abbreviation?.slice(0, 2)}
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex-1 min-w-0">
                                                <h3 className="text-xl font-bold text-white leading-tight">{party.name}</h3>
                                                <p className="text-gray-400 text-sm font-mono mt-0.5">{party.abbreviation}</p>
                                                {party.motto && (
                                                    <p className="text-gray-500 text-xs italic mt-1">"{party.motto}"</p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="mb-4">
                                            <p className="text-gray-500 text-xs uppercase tracking-wide mb-1">Party Colors</p>
                                            <ColorDots colorsArray={party.colors_array} />
                                        </div>

                                        {(party.leader_name || party.leader_photo_url) && (
                                            <div className="flex items-center gap-3 mb-4 p-3 bg-slate-900/50 rounded-lg border border-slate-700/30">
                                                {party.leader_photo_url ? (
                                                    <img
                                                        src={party.leader_photo_url}
                                                        alt={party.leader_name}
                                                        className="w-12 h-12 rounded-full object-cover border-2 flex-shrink-0"
                                                        style={{ borderColor: primaryColor }}
                                                    />
                                                ) : (
                                                    <div
                                                        className="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                                                        style={{ backgroundColor: primaryColor + '44' }}
                                                    >
                                                        {party.leader_name?.charAt(0) || '?'}
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="text-gray-400 text-xs uppercase tracking-wide">Party Leader</p>
                                                    <p className="text-white font-semibold text-sm truncate">{party.leader_name || '—'}</p>
                                                </div>
                                            </div>
                                        )}

                                        <div className="mb-4">
                                            <p className="text-gray-500 text-xs uppercase tracking-wide mb-2">
                                                Candidates ({party.candidates?.length ?? 0})
                                                {activeElection && (
                                                    <span className="text-gray-600 normal-case ml-1">for {activeElection.name}</span>
                                                )}
                                            </p>
                                            {party.candidates && party.candidates.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {party.candidates.map((candidate) => (
                                                        <div key={candidate.id} className="flex items-center gap-2 bg-slate-900/50 rounded-lg px-2 py-1.5 border border-slate-700/30">
                                                            {candidate.photo_url ? (
                                                                <img
                                                                    src={candidate.photo_url}
                                                                    alt={candidate.name}
                                                                    className="w-7 h-7 rounded-full object-cover flex-shrink-0 border border-slate-600"
                                                                />
                                                            ) : (
                                                                <div
                                                                    className="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                                                    style={{ backgroundColor: primaryColor + '66' }}
                                                                >
                                                                    {candidate.name?.charAt(0) || '?'}
                                                                </div>
                                                            )}
                                                            <div>
                                                                <span className="text-gray-300 text-xs font-medium truncate max-w-[120px] block">
                                                                    {candidate.name}
                                                                </span>
                                                                {candidate.ballot_number && (
                                                                    <span className="text-gray-600 text-xs">#{candidate.ballot_number}</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-gray-600 text-xs italic">
                                                    No candidates yet — add them via Edit Party.
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-1 mb-4 text-sm">
                                            {party.headquarters && (
                                                <p className="text-gray-400">
                                                    <span className="text-gray-500">HQ:</span> {party.headquarters}
                                                </p>
                                            )}
                                            {party.website && (
                                                <p className="text-gray-400">
                                                    <span className="text-gray-500">Web:</span>{' '}
                                                    <a href={party.website} target="_blank" rel="noopener noreferrer" className="text-teal-400 hover:text-teal-300 underline">
                                                        {party.website.replace(/^https?:\/\//, '')}
                                                    </a>
                                                </p>
                                            )}
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="mt-auto flex gap-2">
                                            <button
                                                onClick={() => handleEdit(party.id)}
                                                className="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-colors text-sm"
                                            >
                                                Edit Party & Candidates
                                            </button>
                                            <button
                                                onClick={() => handleDelete(party)}
                                                disabled={deletingId === party.id}
                                                className="px-4 py-2.5 bg-red-600/80 hover:bg-red-600 disabled:opacity-50 text-white rounded-lg font-semibold transition-colors text-sm"
                                            >
                                                {deletingId === party.id ? '…' : '🗑'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
