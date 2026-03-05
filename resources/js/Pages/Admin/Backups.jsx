import AppLayout from '@/Layouts/AppLayout';

export default function Backups({ auth, backups = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">Backup Management</h1>
                    <button className="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg">
                        Create Backup Now
                    </button>
                </div>

                <div className="space-y-4">
                    {backups.length > 0 ? (
                        backups.map((backup, i) => (
                            <div key={i} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <div className="flex justify-between items-center">
                                    <div>
                                        <h3 className="text-white font-bold">{backup.name}</h3>
                                        <p className="text-gray-400 text-sm">{backup.date} - {backup.size}</p>
                                    </div>
                                    <button className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                                        Download
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400">No backups available</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
