<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
            if (!$electionId) {
                throw new \RuntimeException('Election gambia-2021-presidential must exist before running RegionSeeder.');
            }

            $regions = [
                'Greater Banjul',
                'Brikama (West Coast)',
                'Kerewan (North Bank)',
                'Kuntaur (Central River West)',
                'Janjanbureh (Central River East)',
                'Mansa Konko (Lower River)',
                'Basse (Upper River)'
            ];

            foreach ($regions as $name) {
                $code = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($name)), 0, 6)) ?: strtoupper(substr($name, 0, 3));

                $node = AdministrativeHierarchy::firstOrCreate(
                    [
                        'election_id' => $electionId,
                        'level'       => 'admin_area',
                        'code'        => $code,
                    ],
                    [
                        'parent_id' => null,
                        'name'      => $name,
                        'slug'      => Str::slug($name),
                        'depth'     => 0,
                    ]
                );

                // Only create/assign approver if not already set
                if (!$node->assigned_approver_id) {
                    $email = strtolower(str_replace([' ', '(', ')', '\\', '/'], '-', $name)) . '.admin@iec.local';

                    $user = User::firstOrCreate(
                        ['email' => $email],
                        [
                            'name' => $name . ' Approver',
                            'password' => Hash::make(Str::random(40)),
                            'status' => 'active',
                        ]
                    );

                    if (empty($user->password)) {
                        $user->password = Hash::make(Str::random(40));
                        $user->saveQuietly();
                    }

                    if (!$user->hasRole('iec-administrator')) {
                        $user->assignRole('iec-administrator');
                    }

                    $node->assigned_approver_id = $user->id;
                    $node->saveQuietly();
                }
            }
        });
    }
}
