<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * INTENTIONALLY NO-OP.
 * See RegionSeeder for explanation.
 */
class ConstituencySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('ConstituencySeeder: skipped — hierarchy is managed by PollingStationSeeder.');
    }
}
