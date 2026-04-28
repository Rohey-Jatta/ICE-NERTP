<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ConstituencySeeder extends Seeder
{
    public function run()
    {
        $regions = AdministrativeHierarchy::where('level', 'admin_area')->get();

        Role::firstOrCreate(['name' => 'constituency-approver']);

        // create 53 constituencies distributed across regions deterministically
        $total = 53;
        $perRegion = (int) ceil($total / max(1, $regions->count()));
        $created = 0;

        foreach ($regions as $region) {
            for ($i = 1; $i <= $perRegion && $created < $total; $i++, $created++) {
                $name = $region->name . ' - Constituency ' . ($i);
                $node = AdministrativeHierarchy::create([
                    'election_id' => 1,
                    'level' => 'constituency',
                    'parent_id' => $region->id,
                    'name' => $name,
                    'code' => strtoupper('C' . ($created + 1)),
                ]);

                $user = User::factory()->create([
                    'name' => $name . ' Approver',
                    'email' => 'constituency.' . ($created + 1) . '@iec.local'
                ]);
                $user->assignRole('constituency-approver');
                $node->assigned_approver_id = $user->id;
                $node->saveQuietly();
            }
        }
    }
}
