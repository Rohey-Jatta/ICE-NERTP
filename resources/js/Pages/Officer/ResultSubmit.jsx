import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function ResultSubmit({ auth, station, election, candidates = [], editableResult, alreadySubmitted }) {
    const isResubmission = editableResult !== null;

    const { data, setData, post, processing, errors } = useForm({
        election_id:       election?.id || '',
        registered_voters: editableResult?.total_registered_voters || station?.registered_voters || '',
        total_votes_cast:  editableResult?.total_votes_cast || '',
        valid_votes:       editableResult?.valid_votes || '',
        rejected_votes:    editableResult?.rejected_votes || '',
        photo:             null,
        candidate_votes:   Object.fromEntries(
            candidates.map(c => [c.id, ''])
        ),
    });

    const [photoPreview, setPhotoPreview]     = useState(null);
    const [totalsError, setTotalsError]       = useState(null);
    const [candidateError, setCandidateError] = useState(null);

    useEffect(() => {
        const total = parseInt(data.total_votes_cast) || 0;
        const valid = parseInt(data.valid_votes) || 0;
        const rej   = parseInt(data.rejected_votes) || 0;
        const reg   = parseInt(data.registered_voters) || 0;

        if (total > 0 && valid + rej !== total) {
            setTotalsError(`Valid (${valid}) + Rejected (${rej}) = ${valid + rej}, but Total Cast = ${total}`);
        } else if (total > reg && reg > 0) {
            setTotalsError(`Total votes (${total}) cannot exceed registered voters (${reg})`);
        } else {
            setTotalsError(null);
        }

        const candidateSum = Object.values(data.candidate_votes)
            .reduce((sum, v) => sum + (parseInt(v) || 0), 0);
        if (valid > 0 && candidateSum !== valid) {
            setCandidateError(`Candidate votes sum (${candidateSum}) must equal valid votes (${valid})`);
        } else {
            setCandidateError(null);
        }
    }, [data.total_votes_cast, data.valid_votes, data.rejected_votes, data.registered_voters, data.candidate_votes]);

    const handlePhotoChange = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setData('photo', file);
        const reader = new FileReader();
        reader.onloadend = () => setPhotoPreview(reader.result);
        reader.readAsDataURL(file);
    };

    /**
     * BUG FIX: Removed `window.axios.defaults.headers...` from here.
     *
     * The previous code crashed with:
     *   "Cannot read properties of undefined (reading 'defaults')"
     * because window.axios was never defined (bootstrap.js wasn't imported
     * in app.jsx). The fix is two-part:
     *   1. Import bootstrap.js in app.jsx (done above).
     *   2. Remove this axios manipulation entirely — Inertia's useForm post()
     *      sends the X-XSRF-TOKEN cookie automatically, so there is no need
     *      to manually refresh the CSRF token header before every submission.
     */
    const handleSubmit = (e) => {
        e.preventDefault();
        post('/officer/results/submit', { preserveScroll: true });
    };

    const turnout = data.registered_voters && data.total_votes_cast
        ? ((parseInt(data.total_votes_cast) / parseInt(data.registered_voters)) * 100).toFixed(1)
        : 0;

    const candidateSum = Object.values(data.candidate_votes)
        .reduce((sum, v) => sum + (parseInt(v) || 0), 0);

    const canSubmit = !totalsError && !candidateError && !processing
        && data.total_votes_cast !== '' && data.valid_votes !== '' && data.rejected_votes !== ''
        && candidateSum > 0;

    if (alreadySubmitted && !isResubmission) {
        return (
            <AppLayout user={auth?.user}>
                <div className="container mx-auto px-4 py-8 max-w-2xl">
                    <Link href="/officer/dashboard" className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-6">
                        ← Officer Dashboard
                    </Link>
                    <div className="bg-slate-800/40 rounded-xl p-10 border border-teal-500/30 text-center">
                        <div className="text-5xl mb-4">✅</div>
                        <h1 className="text-2xl font-bold text-white mb-2">Results Already Submitted</h1>
                        <p className="text-gray-400 mb-6 text-sm">
                            You have already submitted results for <strong className="text-white">{station?.name}</strong>.
                            Track the certification progress in your submissions.
                        </p>
                        <Link href="/officer/submissions"
                            className="inline-block px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                            View My Submissions →
                        </Link>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-3xl">

                <div className="mb-6">
                    <Link href="/officer/dashboard" className="text-gray-400 hover:text-white text-sm inline-flex items-center gap-1 mb-3">
                        ← Officer Dashboard
                    </Link>
                    <h1 className="text-2xl font-bold text-white">
                        {isResubmission ? '↩ Resubmit Result' : '📋 Submit Election Results'}
                    </h1>
                    {station && (
                        <p className="text-gray-400 mt-1 text-sm">
                            Station: <strong className="text-white">{station.name}</strong>
                            <span className="ml-2 font-mono text-xs text-gray-500 bg-slate-900/60 px-1.5 py-0.5 rounded">{station.code}</span>
                            {election && <> · {election.name}</>}
                        </p>
                    )}
                </div>

                {isResubmission && editableResult?.last_rejection_reason && (
                    <div className="mb-6 p-4 bg-red-500/10 border border-red-500/40 rounded-xl">
                        <div className="text-red-300 font-semibold text-sm mb-1">
                            ⚠ This result was rejected {editableResult.rejection_count} time(s). Reason:
                        </div>
                        <div className="text-red-200 text-sm" dangerouslySetInnerHTML={{ __html: editableResult.last_rejection_reason }} />
                        <div className="text-red-400 text-xs mt-2">Please correct the issues above before resubmitting.</div>
                    </div>
                )}

                {!election && (
                    <div className="mb-6 p-4 bg-amber-500/10 border border-amber-500/40 rounded-xl">
                        <p className="text-amber-300 text-sm">⚠ No active election found. Contact the administrator.</p>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-white font-bold text-lg mb-1">Vote Totals</h2>
                        <p className="text-gray-500 text-xs mb-4">Valid + Rejected must equal Total Votes Cast</p>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="col-span-2 sm:col-span-1">
                                <label className="block text-gray-300 text-sm font-semibold mb-2">
                                    Registered Voters <span className="text-red-400">*</span>
                                </label>
                                <input type="number" min="1"
                                    value={data.registered_voters}
                                    onChange={(e) => setData('registered_voters', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white font-mono focus:outline-none focus:border-blue-500"
                                    placeholder="0" required />
                                {errors.registered_voters && <p className="text-red-400 text-xs mt-1">{errors.registered_voters}</p>}
                            </div>

                            <div className="col-span-2 sm:col-span-1">
                                <label className="block text-gray-300 text-sm font-semibold mb-2">
                                    Total Votes Cast <span className="text-red-400">*</span>
                                </label>
                                <input type="number" min="0"
                                    value={data.total_votes_cast}
                                    onChange={(e) => setData('total_votes_cast', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white font-mono focus:outline-none focus:border-blue-500"
                                    placeholder="0" required />
                                {errors.total_votes_cast && <p className="text-red-400 text-xs mt-1">{errors.total_votes_cast}</p>}
                            </div>

                            <div>
                                <label className="block text-gray-300 text-sm font-semibold mb-2">
                                    Valid Votes <span className="text-red-400">*</span>
                                </label>
                                <input type="number" min="0"
                                    value={data.valid_votes}
                                    onChange={(e) => setData('valid_votes', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-teal-600/50 rounded-lg text-teal-300 font-mono focus:outline-none focus:border-teal-500"
                                    placeholder="0" required />
                            </div>

                            <div>
                                <label className="block text-gray-300 text-sm font-semibold mb-2">
                                    Rejected Ballots <span className="text-red-400">*</span>
                                </label>
                                <input type="number" min="0"
                                    value={data.rejected_votes}
                                    onChange={(e) => setData('rejected_votes', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-red-600/30 rounded-lg text-red-300 font-mono focus:outline-none focus:border-red-500"
                                    placeholder="0" required />
                            </div>
                        </div>

                        {totalsError && (
                            <div className="mt-3 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-300 text-xs">
                                ⚠ {totalsError}
                            </div>
                        )}

                        {data.total_votes_cast && data.registered_voters && !totalsError && (
                            <div className="mt-3 p-3 bg-slate-900/50 rounded-lg">
                                <div className="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Voter Turnout</span>
                                    <span className="font-bold text-white">{turnout}%</span>
                                </div>
                                <div className="w-full bg-slate-700 rounded-full h-2">
                                    <div className="bg-gradient-to-r from-blue-600 to-teal-500 h-2 rounded-full transition-all"
                                        style={{ width: `${Math.min(turnout, 100)}%` }} />
                                </div>
                            </div>
                        )}
                    </div>

                    {candidates.length > 0 && (
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <div className="flex justify-between items-start mb-1">
                                <h2 className="text-white font-bold text-lg">Votes by Candidate</h2>
                                <span className={`text-xs font-mono px-2 py-1 rounded ${
                                    !candidateError && candidateSum > 0
                                        ? 'bg-teal-500/20 text-teal-300'
                                        : 'bg-slate-700 text-gray-400'
                                }`}>
                                    Sum: {candidateSum.toLocaleString()}
                                    {data.valid_votes ? ` / ${parseInt(data.valid_votes).toLocaleString()}` : ''}
                                </span>
                            </div>
                            <p className="text-gray-500 text-xs mb-4">Candidate votes must sum to Valid Votes</p>

                            <div className="space-y-3">
                                {candidates.map((candidate) => (
                                    <div key={candidate.id}
                                        className="flex items-center gap-4 p-4 bg-slate-900/40 rounded-lg border border-slate-700/30">
                                        <div className="w-3 h-3 rounded-full flex-shrink-0"
                                            style={{ backgroundColor: candidate.party_color }} />
                                        <div className="flex-1 min-w-0">
                                            <div className="text-white font-semibold text-sm">{candidate.name}</div>
                                            <div className="text-gray-500 text-xs">{candidate.party_name}</div>
                                        </div>
                                        {data.valid_votes && data.candidate_votes[candidate.id] && (
                                            <div className="w-20 bg-slate-700 rounded-full h-1.5 flex-shrink-0">
                                                <div className="h-1.5 rounded-full transition-all"
                                                    style={{
                                                        width: `${Math.min(100, (parseInt(data.candidate_votes[candidate.id]) / parseInt(data.valid_votes)) * 100)}%`,
                                                        backgroundColor: candidate.party_color,
                                                    }} />
                                            </div>
                                        )}
                                        <input type="number" min="0"
                                            value={data.candidate_votes[candidate.id] ?? ''}
                                            onChange={(e) => setData('candidate_votes', {
                                                ...data.candidate_votes,
                                                [candidate.id]: e.target.value,
                                            })}
                                            className="w-28 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-center font-mono text-sm focus:outline-none focus:border-blue-500"
                                            placeholder="0" required />
                                    </div>
                                ))}
                            </div>

                            {candidateError && (
                                <div className="mt-3 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-300 text-xs">
                                    ⚠ {candidateError}
                                </div>
                            )}
                            {errors.candidate_votes && (
                                <p className="text-red-400 text-xs mt-2">{errors.candidate_votes}</p>
                            )}
                        </div>
                    )}

                    {candidates.length === 0 && (
                        <div className="p-5 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-300 text-sm">
                            ⚠ No candidates configured for this election. Contact the administrator.
                        </div>
                    )}

                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                        <h2 className="text-white font-bold text-lg mb-1">Result Sheet Photo</h2>
                        <p className="text-gray-500 text-xs mb-4">
                            Upload a clear photo of the official result tally sheet. Max 10MB.
                            {isResubmission && ' You may upload a new photo or keep the existing one.'}
                        </p>

                        <label className="block cursor-pointer">
                            <div className={`border-2 border-dashed rounded-xl p-8 text-center transition-colors ${
                                photoPreview
                                    ? 'border-teal-500/50 bg-teal-500/5'
                                    : 'border-slate-600 hover:border-slate-500 bg-slate-900/30'
                            }`}>
                                {photoPreview ? (
                                    <div>
                                        <img src={photoPreview} alt="Preview"
                                            className="max-h-48 mx-auto rounded-lg mb-3 object-contain" />
                                        <p className="text-teal-400 text-sm">✓ Photo selected — click to change</p>
                                    </div>
                                ) : (
                                    <div>
                                        <div className="text-4xl mb-2">📷</div>
                                        <p className="text-gray-300 font-semibold text-sm">Click to upload result sheet photo</p>
                                        <p className="text-gray-500 text-xs mt-1">PNG, JPG up to 10MB</p>
                                        {isResubmission && (
                                            <p className="text-gray-600 text-xs mt-1">Previous photo will be kept if none selected</p>
                                        )}
                                    </div>
                                )}
                            </div>
                            <input type="file" accept="image/*" onChange={handlePhotoChange} className="hidden" />
                        </label>
                        {errors.photo && <p className="text-red-400 text-xs mt-1">{errors.photo}</p>}
                    </div>

                    <div className="flex gap-4">
                        <button type="submit" disabled={!canSubmit}
                            className="flex-1 py-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold rounded-xl text-lg transition-colors">
                            {processing
                                ? 'Submitting…'
                                : isResubmission
                                ? '↩ Resubmit Result'
                                : '✓ Submit Election Results'}
                        </button>
                        <Link href="/officer/dashboard"
                            className="px-6 py-4 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-xl transition-colors">
                            Cancel
                        </Link>
                    </div>

                    {errors.error && (
                        <div className="p-4 bg-red-500/20 border border-red-500/40 rounded-xl text-red-300 text-sm">
                            {errors.error}
                        </div>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}