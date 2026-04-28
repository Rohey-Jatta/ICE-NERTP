<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            ElectionSeeder::class,
            RegionSeeder::class,
            ConstituencySeeder::class,
            WardSeeder::class,
            PollingStationSeeder::class,
            PartySeeder::class,
            CandidateSeeder::class,
            UserSeeder::class,
            ResultSeeder::class,
            WorkflowSeeder::class,
        ]);
    }
}
// <?php

// namespace Database\Seeders;

// use Illuminate\Database\Seeder;

// class DatabaseSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $this->call([
//             RoleSeeder::class, 
//             ElectionSeeder::class,    // MUST COME FIRST
//             TestDataSeeder::class,
//             UserAssignmentSeeder::class  // THEN USERS
//         ]);
//     }


// }
