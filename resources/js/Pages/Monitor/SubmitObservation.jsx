import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';

export default function SubmitObservation({ auth }) {
    const { data, setData, post, processing } = useForm({
        station_id: '',
        observation: '',
        severity: 'normal',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        // send to backend if route exists; otherwise show alert
        post('/monitor/observations', {
            onSuccess: () => alert('Observation submitted'),
            onError: () => alert('Failed to submit observation'),
        });
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                <h1 className="text-3xl font-bold text-white mb-6">Submit Observation</h1>

                <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-gray-300 mb-2">Observation Details</label>
                            <textarea
                                value={data.observation}
                                onChange={(e) => setData('observation', e.target.value)}
                                rows="8"
                                className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                placeholder="Describe your observation..."
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg"
                        >
                            Submit Observation
                        </button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
