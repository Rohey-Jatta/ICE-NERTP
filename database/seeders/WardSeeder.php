<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WardSeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running WardSeeder.');
        }

        $constituencies = AdministrativeHierarchy::where('level', 'constituency')->get();

        $totalWards = 120;
        $perConst = (int) ceil($totalWards / max(1, $constituencies->count()));
        $created = 0;

        foreach ($constituencies as $const) {
            for ($i = 1; $i <= $perConst && $created < $totalWards; $i++, $created++) {
                $name = $const->name . ' - Ward ' . ($i);
                $node = AdministrativeHierarchy::create([
                    'election_id' => $electionId,
                    'level' => 'ward',
                    'parent_id' => $const->id,
                    'name' => $name,
                    'slug' => Str::slug($name),           // REQUIRED
                    'depth' => 2,                         // REQUIRED
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
