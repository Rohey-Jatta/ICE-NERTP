import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ResultSubmit({ auth, candidates = [], election = null }) {
    const { data, setData, post, processing, errors } = useForm({
        election_id: election?.id || '',
        turnout: '',
        registered_voters: '',
        total_votes_cast: '',
        valid_votes: '',
        rejected_votes: '',
        photo: null,
        candidate_votes: {},
    });

    const [photoPreview, setPhotoPreview] = useState(null);

    const handlePhotoChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setData('photo', file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setPhotoPreview(reader.result);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/officer/results/submit', {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Submit Results</h1>

                <form onSubmit={handleSubmit} className="max-w-4xl">
                    {/* Turnout Information */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                        <h2 className="text-xl font-bold text-white mb-4">Turnout Information</h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-300 mb-2">Registered Voters</label>
                                <input
                                    type="number"
                                    value={data.registered_voters}
                                    onChange={(e) => setData('registered_voters', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-pink-300 rounded-lg text-white"
                                    placeholder="0"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-gray-300 mb-2">Total Votes Cast</label>
                                <input
                                    type="number"
                                    value={data.total_votes_cast}
                                    onChange={(e) => setData('total_votes_cast', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-pink-300 rounded-lg text-white"
                                    placeholder="0"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-gray-300 mb-2">Valid Votes</label>
                                <input
                                    type="number"
                                    value={data.valid_votes}
                                    onChange={(e) => setData('valid_votes', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-pink-300 rounded-lg text-white"
                                    placeholder="0"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-gray-300 mb-2">Rejected Votes</label>
                                <input
                                    type="number"
                                    value={data.rejected_votes}
                                    onChange={(e) => setData('rejected_votes', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-pink-300 rounded-lg text-white"
                                    placeholder="0"
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    {/* Candidate Vote Counts */}
                    {candidates.length > 0 && (
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                            <h2 className="text-xl font-bold text-white mb-4">Vote Counts by Candidate</h2>

                            <div className="space-y-4">
                                {candidates.map((candidate) => (
                                    <div key={candidate.id} className="flex items-center gap-4 bg-slate-900/50 p-4 rounded-lg">
                                        <div className="flex-1">
                                            <div className="font-bold text-white">{candidate.name}</div>
                                            <div className="text-sm text-gray-400">{candidate.party}</div>
                                        </div>
                                        <input
                                            type="number"
                                            value={data.candidate_votes[candidate.id] || ''}
                                            onChange={(e) => setData('candidate_votes', {
                                                ...data.candidate_votes,
                                                [candidate.id]: e.target.value
                                            })}
                                            className="w-32 px-4 py-3 bg-slate-800 border border-pink-300 rounded-lg text-white text-center"
                                            placeholder="0"
                                            required
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {candidates.length === 0 && (
                        <div className="bg-pink-500/20 border border-pink-500/50 rounded-xl p-6 mb-6">
                            <p className="text-pink-300">No candidates configured. Please contact administrator.</p>
                        </div>
                    )}

                    {/* Photo Upload */}
                    <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50 mb-6">
                        <h2 className="text-xl font-bold text-white mb-4">Upload Result Sheet Photo</h2>

                        <div className="border-2 border-dashed border-pink-300 rounded-lg p-8 text-center">
                            <input
                                type="file"
                                accept="image/*"
                                onChange={handlePhotoChange}
                                className="hidden"
                                id="photo-upload"
                                required
                            />
                            <label htmlFor="photo-upload" className="cursor-pointer">
                                {photoPreview ? (
                                    <div>
                                        <img src={photoPreview} alt="Preview" className="max-w-md mx-auto rounded-lg mb-4" />
                                        <p className="text-pink-400">Click to change photo</p>
                                    </div>
                                ) : (
                                    <div>
                                        <p className="text-white font-semibold mb-2">Click to upload result sheet photo</p>
                                        <p className="text-gray-400 text-sm">PNG, JPG up to 10MB</p>
                                    </div>
                                )}
                            </label>
                        </div>
                        {errors.photo && <p className="text-pink-400 mt-2">{errors.photo}</p>}
                    </div>

                    {/* Submit Button */}
                    <div className="flex gap-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-blue-600 disabled:to-blue-700 text-white font-bold rounded-lg shadow-lg"
                        >
                            {processing ? 'Submitting...' : 'Submit Results'}
                        </button>


                            <a href="/officer/dashboard"
                            className="px-8 py-4 bg-pink-600 hover:bg-pink-700 text-white font-bold rounded-lg">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
