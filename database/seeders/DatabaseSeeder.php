<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed order matters:
     *  1. Roles & Permissions
     *  2. Election record
     *  3. PollingStationSeeder — creates the FULL hierarchy tree:
     *     national → admin_area → constituency → ward → polling_station
     *     (RegionSeeder / ConstituencySeeder / WardSeeder are no-ops; this is the single source of truth)
     *  4. Parties & Candidates
     *  5. Users (approvers assigned by PollingStationSeeder; this adds named test accounts)
     *  6. Results (distributes official 2021 results to every polling station)
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            ElectionSeeder::class,
            PollingStationSeeder::class,
            PartySeeder::class,
            CandidateSeeder::class,
            UserSeeder::class,
            ResultSeeder::class,
        ]);
    }
}
