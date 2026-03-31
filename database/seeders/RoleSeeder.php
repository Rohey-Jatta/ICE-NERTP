<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        echo "Creating roles...\n";

        $roles = [
            "polling-officer",
            "ward-approver",
            "constituency-approver",
            "admin-area-approver",
            "iec-chairman",
            "iec-administrator",
            "party-representative",
            "election-monitor",
        ];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(["name" => $roleName, "guard_name" => "web"]);
            echo "   {$roleName}\n";
        }

        echo "\n✅ Roles created!\n";
    }
}
