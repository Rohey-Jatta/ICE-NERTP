import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

export default function DashboardOverview({ auth, party }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="mb-6">
                    <Link href="/party/dashboard"
                          className="text-slate-500 hover:text-iec-navy text-sm inline-flex items-center gap-1 mb-3">
                        ← Party Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Party Overview</h1>
                    {party?.name && <p className="text-slate-500 mt-1">{party.name}</p>}
                </div>

                <div className="bg-white rounded-xl p-8 border border-slate-200 text-center">
                    <p className="text-slate-600 mb-6">
                        Use the dashboard to manage your party's result acceptances.
                    </p>
                    <div className="flex flex-wrap gap-3 justify-center">
                        <Link href="/party/dashboard"
                              className="px-5 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 text-white font-bold rounded-lg">
                            Go to Dashboard
                        </Link>
                        <Link href="/party/pending-acceptance"
                              className="px-5 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg">
                            Review Pending Results
                        </Link>
                        <Link href="/party/stations"
                              className="px-5 py-3 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg">
                            View My Stations
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}