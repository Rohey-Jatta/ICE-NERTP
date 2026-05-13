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

        $wards = AdministrativeHierarchy::where('level', 'ward')->get();
        $totalStations = 1555;
        $perWard = (int) ceil($totalStations / max(1, $wards->count()));
        $created = 0;

        foreach ($wards as $ward) {
            for ($i = 1; $i <= $perWard && $created < $totalStations; $i++, $created++) {
                $station = PollingStation::create([
                    'election_id' => $electionId,
                    'ward_id' => $ward->id,
                    'code' => 'PS-' . ($created + 1),
                    'name' => $ward->name . ' Polling Station ' . ($i),
                    'address' => 'Address for station ' . ($created + 1),
                    'registered_voters' => rand(200, 1200),
                    'latitude' => 13.45 + (rand(-500, 500) / 10000),
                    'longitude' => -16.66 + (rand(-500, 500) / 10000),
                    'is_active' => true,
                ]);

                // Set PostGIS location if using PostgreSQL
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("UPDATE polling_stations SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?", [
                        $station->longitude,
                        $station->latitude,
                        $station->id
                    ]);
                }

                // Create polling officer
                $officer = User::factory()->create([
                    'name' => $station->name . ' Officer',
                    'email' => 'officer.' . ($created + 1) . '@iec.local'
                ]);
                $officer->assignRole('polling-officer');
                $station->assigned_officer_id = $officer->id;
                $station->saveQuietly();

                // No "polling-station-approver" – that role is not used in this table
            }
        }
    }
}
