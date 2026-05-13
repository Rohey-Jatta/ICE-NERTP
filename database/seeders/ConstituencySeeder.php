<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ConstituencySeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running ConstituencySeeder.');
        }

        $regions = AdministrativeHierarchy::where('level', 'admin_area')->get();

        // 53 constituencies
        $total    = 53;
        $perRegion = (int) ceil($total / max(1, $regions->count()));
        $created  = 0;

        foreach ($regions as $region) {
            for ($i = 1; $i <= $perRegion && $created < $total; $i++, $created++) {
                $name = $region->name . ' - Constituency ' . ($i);
                $code = strtoupper('C' . ($created + 1));

                $node = AdministrativeHierarchy::firstOrCreate(
                    [
                        'election_id' => $electionId,
                        'level'       => 'constituency',
                        'code'        => $code,
                    ],
                    [
                        'parent_id' => $region->id,
                        'name'      => $name,
                        'slug'      => Str::slug($name),
                        'depth'     => 1,
                    ]
                );

                if (!$node->assigned_approver_id) {
                    $user = User::firstOrCreate(
                        ['email' => 'constituency.' . ($created + 1) . '@iec.local'],
                        [
                            'name'     => $name . ' Approver',
                            'password' => bcrypt('password123'),
                        ]
                    );
                    if (!$user->hasRole('constituency-approver')) {
                        $user->assignRole('constituency-approver');
                    }
                    $node->assigned_approver_id = $user->id;
                    $node->saveQuietly();
                }
            }
        }
    }
}