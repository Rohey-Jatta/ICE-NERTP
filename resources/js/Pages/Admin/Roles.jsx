import AppLayout from '@/Layouts/AppLayout';

export default function Roles({ auth, roles = [] }) {
    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-white">Roles & Permissions</h1>
                    <a href="/admin/dashboard" className="px-4 py-2 bg-slate-700 text-white rounded-lg">
                        ← Back to Admin
                    </a>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {roles.length > 0 ? (
                        roles.map((role) => (
                            <div key={role.id} className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                                <h3 className="text-xl font-bold text-white mb-2 capitalize">
                                    {role.name.replace(/-/g, ' ')}
                                </h3>
                                <p className="text-sm text-gray-400 mb-4">
                                    {role.permissions.length} permissions assigned
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {role.permissions.map((perm, i) => (
                                        <span
                                            key={i}
                                            className="px-2 py-1 bg-teal-500/20 text-teal-300 rounded text-xs"
                                        >
                                            {perm}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-2 bg-slate-800/40 rounded-xl p-12 border border-slate-700/50 text-center">
                            <p className="text-gray-400">No roles configured. Run the database seeder first.</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
