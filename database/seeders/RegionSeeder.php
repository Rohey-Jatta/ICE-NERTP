<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RegionSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $regions = [
                'Greater Banjul',
                'Brikama (West Coast)',
                'Kerewan (North Bank)',
                'Kuntaur (Central River West)',
                'Janjanbureh (Central River East)',
                'Mansa Konko (Lower River)',
                'Basse (Upper River)'
            ];

            Role::firstOrCreate(['name' => 'iec-administrator']);

            foreach ($regions as $name) {
                $node = AdministrativeHierarchy::create([
                    'election_id' => 1,
                    'level' => 'admin_area',
                    'parent_id' => null,
                    'name' => $name,
                    'code' => strtoupper(substr(preg_replace('/[^A-Z]/', '', $name), 0, 6)) ?: strtoupper(substr($name,0,3)),
                ]);

                // create one regional approver
                $user = User::factory()->create([
                    'name' => $name . ' Approver',
                    'email' => strtolower(str_replace([' ', '(', ')', '\\', '/'], '-', $name)) . '.admin@iec.local'
                ]);
                $user->assignRole('iec-administrator');
                $node->assigned_approver_id = $user->id;
                $node->saveQuietly();
            }
        });
    }
}
