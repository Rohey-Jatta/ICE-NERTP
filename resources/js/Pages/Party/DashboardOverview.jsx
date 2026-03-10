import AppLayout from '@/Layouts/AppLayout';

export default function DashboardOverview({ auth }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Party Dashboard Overview</h1>
                
                <div className="bg-slate-800/40 rounded-xl p-8 border border-slate-700/50">
                    <p className="text-gray-300">Party-wide results overview and statistics.</p>
                </div>
            </div>
        </AppLayout>
    );
}
