<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class WardSeeder extends Seeder
{
    public function run()
    {
        $constituencies = AdministrativeHierarchy::where('level', 'constituency')->get();

        Role::firstOrCreate(['name' => 'ward-approver']);

        $totalWards = 120;
        $perConst = (int) ceil($totalWards / max(1, $constituencies->count()));
        $created = 0;

        foreach ($constituencies as $const) {
            for ($i = 1; $i <= $perConst && $created < $totalWards; $i++, $created++) {
                $name = $const->name . ' - Ward ' . ($i);
                $node = AdministrativeHierarchy::create([
                    'election_id' => 1,
                    'level' => 'ward',
                    'parent_id' => $const->id,
                    'name' => $name,
                    'code' => strtoupper('W' . ($created + 1)),
                ]);

                $user = User::factory()->create([
                    'name' => $name . ' Approver',
                    'email' => 'ward.' . ($created + 1) . '@iec.local'
                ]);
                $user->assignRole('ward-approver');
                $node->assigned_approver_id = $user->id;
                $node->saveQuietly();
            }
        }
    }
}
