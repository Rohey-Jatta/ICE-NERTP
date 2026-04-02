<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    private array $permissions = [
        // Polling Officer
        'submit-result',
        'view-own-result',
        'edit-pending-result',
        'upload-photo',
        'view-own-polling-station',

        // Ward Approver
        'view-ward-results',
        'approve-ward-result',
        'approve-ward-result-with-reservation',
        'reject-ward-result',
        'view-ward-queue',
        'add-ward-certification-comment',
        'view-ward-analytics',

        // Constituency Approver
        'view-constituency-results',
        'approve-constituency-result',
        'approve-constituency-result-with-reservation',
        'reject-constituency-result',
        'view-constituency-queue',
        'view-ward-breakdowns',
        'generate-constituency-report',

        // Admin Area Approver
        'view-admin-area-results',
        'approve-admin-area-result',
        'approve-admin-area-result-with-reservation',
        'reject-admin-area-result',
        'view-admin-area-queue',
        'view-constituency-breakdowns',
        'generate-admin-area-report',
        'access-admin-area-analytics',

        // IEC Chairman
        'national-certification',
        'view-all-results',
        'override-rejection',
        'final-approval',
        'access-full-analytics',
        'publish-results',
        'view-national-queue',
        'generate-national-report',

        // IEC Administrator
        'create-election',
        'edit-election',
        'manage-users',
        'assign-roles',
        'configure-workflow',
        'system-settings',
        'view-audit-logs',
        'manage-polling-stations',
        'register-parties',
        'register-candidates',
        'manage-election-monitors',
        'deactivate-user',
        'view-all-results',
        'access-full-analytics',

        // Party Representative
        'view-assigned-stations',
        'accept-result',
        'accept-result-with-reservation',
        'reject-result',
        'add-acceptance-comment',
        'view-party-dashboard',

        // Election Monitor
        'submit-observation',
        'view-observation-history',
        'export-observations',
    ];

    private array $roles = [
        'polling-officer' => [
            'submit-result',
            'view-own-result',
            'edit-pending-result',
            'upload-photo',
            'view-own-polling-station',
        ],

        'ward-approver' => [
            'view-ward-results',
            'approve-ward-result',
            'approve-ward-result-with-reservation',
            'reject-ward-result',
            'view-ward-queue',
            'add-ward-certification-comment',
            'view-ward-analytics',
        ],

        'constituency-approver' => [
            'view-constituency-results',
            'approve-constituency-result',
            'approve-constituency-result-with-reservation',
            'reject-constituency-result',
            'view-constituency-queue',
            'view-ward-breakdowns',
            'generate-constituency-report',
        ],

        'admin-area-approver' => [
            'view-admin-area-results',
            'approve-admin-area-result',
            'approve-admin-area-result-with-reservation',
            'reject-admin-area-result',
            'view-admin-area-queue',
            'view-constituency-breakdowns',
            'generate-admin-area-report',
            'access-admin-area-analytics',
        ],

        'iec-chairman' => [
            'national-certification',
            'view-all-results',
            'override-rejection',
            'final-approval',
            'access-full-analytics',
            'publish-results',
            'view-national-queue',
            'generate-national-report',
            'view-ward-results',
            'view-constituency-results',
            'view-admin-area-results',
        ],

        'iec-administrator' => [
            'create-election',
            'edit-election',
            'manage-users',
            'assign-roles',
            'configure-workflow',
            'system-settings',
            'view-audit-logs',
            'manage-polling-stations',
            'register-parties',
            'register-candidates',
            'manage-election-monitors',
            'deactivate-user',
            'view-all-results',
            'access-full-analytics',
        ],

        'party-representative' => [
            'view-assigned-stations',
            'accept-result',
            'accept-result-with-reservation',
            'reject-result',
            'add-acceptance-comment',
            'view-party-dashboard',
        ],

        'election-monitor' => [
            'submit-observation',
            'view-observation-history',
            'export-observations',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Creating permissions…');
        foreach ($this->permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $this->command->info('✓ ' . count($this->permissions) . ' permissions created');

        $this->command->info('Creating roles and assigning permissions…');
        foreach ($this->roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
            $this->command->info("  ✓ {$roleName} → " . count($rolePermissions) . ' permissions');
        }

        $this->command->info('✅ RBAC complete.');
    }
}
