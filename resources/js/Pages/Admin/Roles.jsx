import AppLayout from '@/Layouts/AppLayout';

export default function Roles({ auth, roles = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-white mb-6">Roles & Permissions</h1>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {roles.length > 0 ? (
                        roles.map((role, i) => (
                            <div key={i} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <h3 className="text-xl font-bold text-white mb-4">{role.name}</h3>
                                <p className="text-gray-400 mb-4">{role.description}</p>
                                <button className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg">
                                    Manage Permissions
                                </button>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-2 bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400">No roles configured. Roles will be loaded from database.</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
