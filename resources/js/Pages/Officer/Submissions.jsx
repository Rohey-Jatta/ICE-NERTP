import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function Submissions({ auth, submissions = [] }) {
    const getStatusColor = (status) => {
        if (status?.includes('Pending')) return 'bg-amber-500/20 text-amber-300 border-amber-500/50';
        if (status?.includes('Approved') || status?.includes('Certified')) return 'bg-teal-500/20 text-teal-300 border-teal-500/50';
        if (status?.includes('Rejected')) return 'bg-red-500/20 text-red-300 border-red-500/50';
        return 'bg-gray-500/20 text-gray-300 border-gray-500/50';
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">My Submissions</h1>
                    <a
                        href="/officer/results/submit"
                        className="px-6 py-3 bg-pink-600 hover:bg-pink-700 text-white font-bold rounded-lg"
                    >
                        + New Submission
                    </a>
                </div>

                <div className="space-y-4">
                    {submissions.length > 0 ? (
                        submissions.map((submission) => (
                            <div key={submission.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 className="text-xl font-bold text-white">{submission.polling_station}</h3>
                                        <p className="text-gray-400 text-sm">Submitted: {submission.submitted_at}</p>
                                    </div>
                                    <span className={`px-4 py-2 rounded-lg border ${getStatusColor(submission.status)}`}>
                                        {submission.status}
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <div className="text-gray-400 text-sm">Total Votes</div>
                                        <div className="text-white font-bold text-xl">{submission.total_votes}</div>
                                    </div>
                                    <div>
                                        <div className="text-gray-400 text-sm">Turnout</div>
                                        <div className="text-white font-bold text-xl">{submission.turnout}%</div>
                                    </div>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-300 text-lg">No submissions yet</p>
                            <a
                                href="/officer/results/submit"
                                className="inline-block mt-4 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg">
                                Submit Your First Result
                            </a>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
