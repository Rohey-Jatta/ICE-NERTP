<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    private array $permissions = [
        'submit-result', 'view-own-result', 'edit-pending-result', 'upload-photo', 'view-own-polling-station',
        'view-ward-results', 'approve-ward-result', 'reject-ward-result', 'reject-ward-result-with-reservation', 'view-ward-queue', 'add-certification-comment', 'view-ward-analytics',
        'view-constituency-results', 'approve-constituency-result', 'approve-constituency-result-with-reservation', 'reject-constituency-result', 'view-constituency-queue', 'view-ward-breakdowns', 'generate-constituency-report', 'view-constituency-analytics',
        'view-admin-area-results', 'approve-admin-area-result', 'approve-admin-area-result-with-reservation', 'reject-admin-area-result', 'view-admin-area-queue', 'view-constituency-breakdowns', 'access-analytics', 'generate-admin-area-report',
        'national-certification', 'view-all-results', 'override-rejection', 'final-approval', 'access-full-analytics', 'publish-results', 'view-national-queue', 'generate-national-report',
        'create-election', 'edit-election', 'manage-users', 'assign-roles', 'configure-workflow', 'system-settings', 'view-audit-logs', 'manage-polling-stations', 'register-parties', 'register-candidates', 'manage-election-monitors', 'deactivate-user',
        'view-assigned-stations', 'accept-result', 'reject-result', 'add-acceptance-comment', 'view-party-dashboard',
        'submit-observation', 'view-observation-history', 'export-observations',
    ];

    private array $roles = [
        'polling-officer'       => ['submit-result', 'view-own-result', 'edit-pending-result', 'upload-photo', 'view-own-polling-station'],
        'ward-approver'         => ['view-ward-results', 'approve-ward-result', 'reject-ward-result', 'reject-ward-result-with-reservation', 'view-ward-queue', 'add-certification-comment', 'view-ward-analytics'],
        'constituency-approver' => ['view-constituency-results', 'approve-constituency-result', 'approve-constituency-result-with-reservation', 'reject-constituency-result', 'view-constituency-queue', 'view-ward-breakdowns', 'generate-constituency-report', 'view-constituency-analytics'],
        'admin-area-approver'   => ['view-admin-area-results', 'approve-admin-area-result', 'approve-admin-area-result-with-reservation', 'reject-admin-area-result', 'view-admin-area-queue', 'view-constituency-breakdowns', 'access-analytics', 'generate-admin-area-report'],
        'iec-chairman'          => ['national-certification', 'view-all-results', 'override-rejection', 'final-approval', 'access-full-analytics', 'publish-results', 'view-national-queue', 'generate-national-report', 'view-ward-results', 'view-constituency-results', 'view-admin-area-results'],
        'iec-administrator'     => ['create-election', 'edit-election', 'manage-users', 'assign-roles', 'configure-workflow', 'system-settings', 'view-audit-logs', 'manage-polling-stations', 'register-parties', 'register-candidates', 'manage-election-monitors', 'deactivate-user', 'view-all-results', 'access-full-analytics'],
        'party-representative'  => ['view-assigned-stations', 'accept-result', 'reject-result', 'add-acceptance-comment', 'view-party-dashboard'],
        'election-monitor'      => ['view-assigned-stations', 'submit-observation', 'view-observation-history', 'export-observations'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->command->info('Creating permissions...');
        foreach ($this->permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $this->command->info('✓ ' . count($this->permissions) . ' permissions created');
        $this->command->info('Creating roles...');
        foreach ($this->roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
            $this->command->info("  ✓ {$roleName} → " . count($rolePermissions) . ' permissions');
        }
        $this->command->info('RBAC complete. Roles: ' . count($this->roles) . ', Permissions: ' . count($this->permissions));
    }
}