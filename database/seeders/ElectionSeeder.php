<?php

namespace Database\Seeders;

use App\Models\Election;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ElectionSeeder extends Seeder
{
    public function run()
    {

    // Ensure role exists FIRST
    $role = \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => 'iec-administrator'
    ]);

    // Seeder admin (used internally)
    $admin = User::firstOrCreate(
        ['email' => 'seeder-admin@iec.gm'],
        [
            'name' => 'Seeder Admin',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]
    );

    // Visible IEC Admin (for login)
    $visibleAdmin = User::updateOrCreate(
        ['email' => 'admina@iec.gm'], // ONLY unique field here
        [
            'name'        => 'System Administrator',
            'password'    => Hash::make('password123'),
            'phone'       => '+2205872319',
            'employee_id' => 'IEC-ADMIN-002',
            'status'      => 'active',
        ]
    );

    // Assign roles
    $admin->syncRoles([$role->name]);
    $visibleAdmin->syncRoles([$role->name]);

    Election::firstOrCreate([
        'slug' => 'gambia-2021-presidential',
        'name' => '2021 Gambian Presidential Election',
        'type' => 'presidential',
        'status' => 'active',
        'created_by' => 1,
    ]);
    }
}

// namespace Database\Seeders;

// use Illuminate\Database\Seeder;
// use App\Models\Election;
// use App\Models\AdministrativeHierarchy;
// use App\Models\PollingStation;
// use App\Models\PoliticalParty;
// use App\Models\Candidate;
// use Illuminate\Support\Str;

// class ElectionSeeder extends Seeder
// {
//     public function run(): void
//     {

//     $admin = \App\Models\User::firstOrCreate(
//     ['email' => 'seeder-admin@iec.gm'],
//     [
//         'name' => 'Seeder Admin',
//         'password' => \Illuminate\Support\Facades\Hash::make('password123'),
//         'status' => 'active',
//     ]
// );
//         $election = Election::firstOrCreate(
//             ['slug' => '2021-general-election'],
//             [
//                 'name' => '2021 General Election',
//                 'slug' => '2021-general-election',
//                 'created_by' => $admin->id,
//                 'type' => 'parliamentary',
//                 'start_date' => '2021-12-04',
//                 'end_date' => '2021-12-04',
//                 'status' => 'archived',
//             ]
//         );

//         // REGIONS
//         $regions = [];
//         $regionNames = [
//             'Banjul',
//             'Kanifing',
//             'West Coast Region',
//             'Lower River Region',
//             'North Bank Region',
//             'Central River Region',
//             'Upper River Region',
//         ];

//         foreach ($regionNames as $name) {
//             $regions[$name] = AdministrativeHierarchy::firstOrCreate([
//                 'election_id' => $election->id,
//                 'level' => 'admin_area',
//                 'name' => $name,
//             ], [
//                 'code' => strtoupper(Str::slug($name))
//             ]);
//         }

//         // CONSTITUENCIES (simplified but real structure)
//         $constituencies = [];

//         $map = [
//             'Kanifing' => ['Serrekunda', 'Bakau', 'Jeshwang'],
//             'Banjul' => ['Banjul North', 'Banjul South'],
//             'West Coast Region' => ['Brikama North', 'Brikama South'],
//         ];

//         foreach ($map as $region => $list) {
//             foreach ($list as $c) {
//                 $constituencies[] = AdministrativeHierarchy::firstOrCreate([
//                     'election_id' => $election->id,
//                     'level' => 'constituency',
//                     'name' => $c,
//                 ], [
//                     'parent_id' => $regions[$region]->id,
//                     'code' => strtoupper(Str::slug($c)),
//                 ]);
//             }
//         }

//         // WARDS
//         $wards = [];
//         foreach ($constituencies as $const) {
//             for ($i = 1; $i <= 2; $i++) {
//                 $wards[] = AdministrativeHierarchy::firstOrCreate([
//                     'election_id' => $election->id,
//                     'level' => 'ward',
//                     'name' => "{$const->name} Ward {$i}",
//                 ], [
//                     'parent_id' => $const->id,
//                 ]);
//             }
//         }

//         // POLLING STATIONS (5 per ward)
//         $stations = [];
//         foreach ($wards as $ward) {
//             for ($i = 1; $i <= 5; $i++) {
//                 $stations[] = PollingStation::firstOrCreate([
//                     'code' => $ward->id . "-S{$i}"
//                 ], [
//                     'election_id' => $election->id,
//                     'ward_id' => $ward->id,
//                     'name' => "{$ward->name} Station {$i}",
//                         'registered_voters' => rand(200, 800),
//                         'latitude' => 13.45 + (rand(-500, 500) / 10000),
//                         'longitude' => -16.66 + (rand(-500, 500) / 10000),
//                     'is_active' => true,
//                 ]);
//             }
//         }

//         // PARTIES
//         $parties = [];
//         $partyData = [
//             'UDP',
//             'NPP',
//             'GDC',
//             'PDOIS',
//         ];

//         foreach ($partyData as $p) {
//             $parties[] = PoliticalParty::firstOrCreate([
//                 'election_id' => $election->id,
//                 'abbreviation' => $p,
//             ], [
//                 'name' => $p . ' Party',
//                 'slug' => Str::slug($p),
//             ]);
//         }

//         // CANDIDATES (1 per party) — assign sequential ballot numbers to avoid unique constraint collisions
//         $candidates = [];
//         foreach ($parties as $i => $party) {
//             $candidates[] = Candidate::firstOrCreate([
//                 'election_id' => $election->id,
//                 'political_party_id' => $party->id,
//             ], [
//                 'name' => $party->abbreviation . ' Candidate',
//                 // ensure unique ballot_number per election
//                 'ballot_number' => (string) ($i + 1),
//                 'is_active' => true,
//             ]);
//         }
//     }
// }
