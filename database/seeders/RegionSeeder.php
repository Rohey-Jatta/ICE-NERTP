<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * INTENTIONALLY NO-OP.
 *
 * The administrative hierarchy (national → admin_area → constituency → ward)
 * is created entirely by PollingStationSeeder using real IEC Gambia ward registry data.
 * Running this seeder separately would create duplicate, conflicting nodes.
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('RegionSeeder: skipped — hierarchy is managed by PollingStationSeeder.');
    }
}
