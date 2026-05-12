<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RbacPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    #[DataProvider('permissionProtectedRoutes')]
    public function test_role_users_can_access_routes_when_permission_is_present(
        string $role,
        string $uri,
        array $permissionsToRemove
    ): void {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get($uri)
            ->assertOk();
    }

    #[DataProvider('permissionProtectedRoutes')]
    public function test_role_users_are_forbidden_when_required_permission_is_missing(
        string $role,
        string $uri,
        array $permissionsToRemove
    ): void {
        $roleModel = Role::findByName($role);
        foreach ($permissionsToRemove as $permission) {
            $roleModel->revokePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get($uri)
            ->assertForbidden();
    }

    public static function permissionProtectedRoutes(): array
    {
        return [
            'administrator users' => [
                'iec-administrator',
                '/admin/users',
                ['manage-users'],
            ],
            'chairman national queue' => [
                'iec-chairman',
                '/chairman/national-queue',
                ['view-national-queue'],
            ],
            'ward approval queue' => [
                'ward-approver',
                '/ward/approval-queue',
                ['view-ward-queue', 'view-ward-results'],
            ],
            'constituency approval queue' => [
                'constituency-approver',
                '/constituency/approval-queue',
                ['view-constituency-queue', 'view-constituency-results'],
            ],
            'admin area approval queue' => [
                'admin-area-approver',
                '/admin-area/approval-queue',
                ['view-admin-area-queue', 'view-admin-area-results'],
            ],
            'officer submissions' => [
                'polling-officer',
                '/officer/submissions',
                ['view-own-result'],
            ],
            'party dashboard' => [
                'party-representative',
                '/party/dashboard',
                ['view-party-dashboard'],
            ],
            'monitor observations' => [
                'election-monitor',
                '/monitor/observations',
                ['view-observation-history'],
            ],
        ];
    }
}
