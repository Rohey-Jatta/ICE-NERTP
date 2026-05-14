<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\PollingStation;
use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PollingStationSeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running PollingStationSeeder.');
        }

        // Skip if stations already exist for this election
        if (PollingStation::where('election_id', $electionId)->exists()) {
            $this->command->info('PollingStationSeeder: stations already exist, skipping.');
            return;
        }

        $wards = AdministrativeHierarchy::where('level', 'ward')->get();
        $totalStations = 1555;
        $perWard = (int) ceil($totalStations / max(1, $wards->count()));
        $created = 0;

        foreach ($wards as $ward) {
            for ($i = 1; $i <= $perWard && $created < $totalStations; $i++, $created++) {
                $code = 'PS-' . ($created + 1);

                $station = PollingStation::firstOrCreate(
                    ['code' => $code],
                    [
                        'election_id'       => $electionId,
                        'ward_id'           => $ward->id,
                        'name'              => $ward->name . ' Polling Station ' . $i,
                        'address'           => 'Address for station ' . ($created + 1),
                        'registered_voters' => rand(200, 1200),
                        'latitude'          => 13.45 + (rand(-500, 500) / 10000),
                        'longitude'         => -16.66 + (rand(-500, 500) / 10000),
                        'is_active'         => true,
                    ]
                );

                // Set PostGIS location if using PostgreSQL
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement(
                        "UPDATE polling_stations SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                        [$station->longitude, $station->latitude, $station->id]
                    );
                }

                // Create / assign polling officer
                $officer = User::firstOrCreate(
                    ['email' => 'officer.' . ($created + 1) . '@iec.local'],
                    [
                        'name'     => $station->name . ' Officer',
                        'password' => bcrypt('password123'),
                        'status'   => 'active',
                    ]
                );

                if (!$officer->hasRole('polling-officer')) {
                    $officer->assignRole('polling-officer');
                }

                if (!$station->assigned_officer_id) {
                    $station->assigned_officer_id = $officer->id;
                    $station->saveQuietly();
                }
            }
        }

        $this->command->info("PollingStationSeeder: created {$created} stations.");
    }
}