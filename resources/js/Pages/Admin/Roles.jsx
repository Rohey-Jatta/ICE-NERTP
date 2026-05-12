import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Roles({ auth, roles = [], allPermissions = [] }) {
    const [expandedRole, setExpandedRole] = useState(null);
    const [saving, setSaving] = useState(null);
    const [rolePermissions, setRolePermissions] = useState(
        roles.reduce((acc, role) => {
            acc[role.id] = role.permissions.map(p => p.name);
            return acc;
        }, {})
    );

    const togglePermission = (roleId, permName) => {
        setRolePermissions(prev => {
            const current = prev[roleId] || [];
            return {
                ...prev,
                [roleId]: current.includes(permName)
                    ? current.filter(p => p !== permName)
                    : [...current, permName],
            };
        });
    };

    const savePermissions = (roleId) => {
        setSaving(roleId);
        router.post(`/admin/roles/${roleId}/permissions`, {
            permissions: rolePermissions[roleId] || [],
        }, {
            preserveScroll: true,
            onFinish: () => setSaving(null),
        });
    };

    // Group permissions by prefix (submit, view, approve, etc.)
    const groupedPermissions = allPermissions.reduce((acc, perm) => {
        const parts = perm.name.split('-');
        const group = parts.length > 1 ? parts.slice(-2).join('-') : perm.name;
        const module = parts[0] || 'other';
        if (!acc[module]) acc[module] = [];
        acc[module].push(perm);
        return acc;
    }, {});

    const roleColors = {
        'polling-officer': 'border-iec-pink-200 bg-iec-pink-50',
        'ward-approver': 'border-pink-500/50 bg-pink-900/20',
        'constituency-approver': 'border-iec-pink-200 bg-iec-pink-50',
        'admin-area-approver': 'border-orange-500/50 bg-orange-900/20',
        'iec-chairman': 'border-red-500/50 bg-red-900/20',
        'iec-administrator': 'border-teal-500/50 bg-teal-900/20',
        'party-representative': 'border-green-500/50 bg-green-900/20',
        'election-monitor': 'border-yellow-500/50 bg-yellow-900/20',
    };

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8">
                <div className="mb-6">
                    <Link href="/admin/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-block">
                        ← Back to Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Roles & Permissions</h1>
                    <p className="text-slate-500 mt-1">Click a role to expand and manage its permissions.</p>
                </div>

                {roles.length === 0 ? (
                    <div className="bg-white rounded-xl p-12 border border-pink-300/50 text-center">
                        <p className="text-slate-500 mb-4">No roles found in database.</p>
                        <p className="text-slate-500 text-sm">Run: <code className="bg-white px-2 py-1 rounded">php artisan db:seed --class=RoleSeeder</code></p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {roles.map((role) => {
                            const isExpanded = expandedRole === role.id;
                            const currentPerms = rolePermissions[role.id] || [];
                            const colorClass = roleColors[role.name] || 'border-slate-500/50 bg-white';

                            return (
                                <div key={role.id} className={`rounded-xl border ${colorClass} overflow-hidden`}>
                                    {/* Role Header */}
                                    <button
                                        onClick={() => setExpandedRole(isExpanded ? null : role.id)}
                                        className="w-full flex items-center justify-between p-6 text-left"
                                    >
                                        <div>
                                            <h3 className="text-xl font-bold text-iec-navy capitalize">
                                                {role.name.replace(/-/g, ' ')}
                                            </h3>
                                            <p className="text-slate-500 text-sm mt-1">
                                                {currentPerms.length} permission{currentPerms.length !== 1 ? 's' : ''} assigned
                                            </p>
                                        </div>
                                        <span className="text-slate-500 text-2xl">
                                            {isExpanded ? '▲' : '▼'}
                                        </span>
                                    </button>

                                    {/* Permissions Panel */}
                                    {isExpanded && (
                                        <div className="border-t border-slate-200 p-6">
                                            {allPermissions.length === 0 ? (
                                                <p className="text-slate-500 text-sm">No permissions in database. Run the RoleAndPermissionSeeder.</p>
                                            ) : (
                                                <>
                                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                                                        {allPermissions.map((perm) => (
                                                            <label
                                                                key={perm.name}
                                                                className={`flex items-center gap-3 p-3 rounded-lg cursor-pointer border transition-colors ${
                                                                    currentPerms.includes(perm.name)
                                                                        ? 'bg-teal-900/30 border-teal-500/50'
                                                                        : 'bg-slate-50 border-slate-200 hover:bg-white'
                                                                }`}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={currentPerms.includes(perm.name)}
                                                                    onChange={() => togglePermission(role.id, perm.name)}
                                                                    className="h-4 w-4 text-iec-pink-600 bg-white border-slate-200 rounded"
                                                                />
                                                                <span className="text-iec-navy text-sm">{perm.name}</span>
                                                            </label>
                                                        ))}
                                                    </div>
                                                    <div className="flex items-center gap-4">
                                                        <button
                                                            onClick={() => savePermissions(role.id)}
                                                            disabled={saving === role.id}
                                                            className="px-8 py-3 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white font-bold rounded-lg"
                                                        >
                                                            {saving === role.id ? 'Saving…' : 'Save Permissions'}
                                                        </button>
                                                        <span className="text-slate-500 text-sm">
                                                            {currentPerms.length} selected
                                                        </span>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}