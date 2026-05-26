<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * INTENTIONALLY NO-OP.
 * See RegionSeeder for explanation.
 */
class WardSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('WardSeeder: skipped — hierarchy is managed by PollingStationSeeder.');
    }
}
