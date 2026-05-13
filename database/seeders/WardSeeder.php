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
        $perConst   = (int) ceil($totalWards / max(1, $constituencies->count()));
        $created    = 0;

        foreach ($constituencies as $const) {
            for ($i = 1; $i <= $perConst && $created < $totalWards; $i++, $created++) {
                $name = $const->name . ' - Ward ' . ($i);
                $code = strtoupper('W' . ($created + 1));

                $node = AdministrativeHierarchy::firstOrCreate(
                    [
                        'election_id' => $electionId,
                        'level'       => 'ward',
                        'code'        => $code,
                    ],
                    [
                        'parent_id' => $const->id,
                        'name'      => $name,
                        'slug'      => Str::slug($name),
                        'depth'     => 2,
                    ]
                );

                if (!$node->assigned_approver_id) {
                    $user = User::firstOrCreate(
                        ['email' => 'ward.' . ($created + 1) . '@iec.local'],
                        [
                            'name'     => $name . ' Approver',
                            'password' => bcrypt('password123'),
                        ]
                    );
                    if (!$user->hasRole('ward-approver')) {
                        $user->assignRole('ward-approver');
                    }
                    $node->assigned_approver_id = $user->id;
                    $node->saveQuietly();
                }
            }
        }
    }
}